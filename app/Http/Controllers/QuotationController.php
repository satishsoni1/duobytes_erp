<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesDocumentRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Services\CustomerNotifier;
use App\Services\SalesService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('quotations.index', [
            'quotations' => Quotation::with(['customer', 'user'])
                ->when(! $user->seesAllRecords(), fn ($q) => $q->where('user_id', $user->id))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('number', 'like', "%$v%")
                    ->orWhereHas('customer', fn ($c) => $c->search($v))))
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $quotation = new Quotation([
            'customer_id' => $request->integer('customer_id') ?: null,
            'lead_id' => $request->integer('lead_id') ?: null,
            'quote_date' => today(),
            'valid_until' => today()->addDays((int) setting('quotation_validity_days', 15)),
            'terms' => setting('quotation_terms'),
            'discount' => 0,
        ]);

        return view('quotations.form', $this->formData($quotation, []));
    }

    public function store(SalesDocumentRequest $request): RedirectResponse
    {
        $quotation = DB::transaction(function () use ($request) {
            $quotation = Quotation::create($request->documentAttributes() + ['user_id' => $request->user()->id, 'status' => 'draft']);
            $quotation->syncItems($request->items());
            $quotation->logActivity('Quotation created', 'system');

            if ($quotation->lead && in_array($quotation->lead->status, ['new', 'contacted', 'qualified', 'showroom_visit'], true)) {
                $quotation->lead->update(['status' => 'quotation_sent']);
                $quotation->lead->logActivity("Quotation {$quotation->number} prepared for ".money($quotation->total), 'status');
            }

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['customer', 'lead', 'user', 'items.product', 'order', 'activities.user']);

        return view('quotations.show', [
            'quotation' => $quotation,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'publicUrl' => $this->publicUrl($quotation),
        ]);
    }

    public function edit(Quotation $quotation): View|RedirectResponse
    {
        if ($quotation->status === 'converted') {
            return back()->with('error', 'Converted quotations cannot be edited.');
        }
        $quotation->load('items.product');

        return view('quotations.form', $this->formData($quotation, $quotation->items->toArray()));
    }

    public function update(SalesDocumentRequest $request, Quotation $quotation): RedirectResponse
    {
        abort_if($quotation->status === 'converted', 422, 'Converted quotations cannot be edited.');

        DB::transaction(function () use ($request, $quotation) {
            $quotation->update($request->documentAttributes());
            $quotation->syncItems($request->items());
            $quotation->logActivity('Quotation revised – new total '.money($quotation->total), 'system');
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        abort_if($quotation->status === 'converted', 422, 'Converted quotations cannot be deleted.');
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    public function print(Quotation $quotation): View
    {
        return view('quotations.print', ['quotation' => $quotation->load(['customer', 'items', 'user'])]);
    }

    /**
     * Customer-facing view via a signed URL (shared over WhatsApp / email).
     */
    public function publicShow(Quotation $quotation): View
    {
        return $this->print($quotation);
    }

    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,sent,accepted,rejected,expired']]);
        abort_if($quotation->status === 'converted', 422);
        $quotation->update($data);
        $quotation->logActivity('Status changed to '.$data['status'], 'status');

        if ($data['status'] === 'rejected' && $quotation->lead && ! $quotation->lead->isClosed()) {
            $quotation->lead->update(['status' => 'negotiation']);
        }

        return back()->with('success', 'Quotation status updated.');
    }

    public function send(Quotation $quotation, CustomerNotifier $notifier, WhatsAppService $whatsapp): RedirectResponse
    {
        $quotation->load('customer');
        $vars = [
            'number' => $quotation->number,
            'total' => money($quotation->total),
            'valid_until' => $quotation->valid_until?->format('d M Y') ?? '-',
            'link' => $this->publicUrl($quotation),
        ];

        // Sending manually always goes out, even if the automated event is switched off.
        $text = "Hi {$quotation->customer->name}, please find your quotation {$vars['number']} for {$vars['total']} (valid until {$vars['valid_until']}): {$vars['link']}";
        $message = setting('notify_quotation_sent_template')
            ? $notifier->send('quotation_sent', $quotation->customer, $vars)
            : $whatsapp->queueText($quotation->customer->whatsapp ?? $quotation->customer->phone, $text, $quotation->customer);

        if (! $message) {
            return back()->with('error', 'Quotation could not be sent – check the customer phone and WhatsApp settings.');
        }

        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
        }
        $quotation->logActivity('Quotation shared on WhatsApp', 'whatsapp');

        return back()->with('success', 'Quotation queued for WhatsApp delivery.');
    }

    public function convert(Request $request, Quotation $quotation, SalesService $sales): RedirectResponse
    {
        $data = $request->validate(['warehouse_id' => ['required', 'exists:warehouses,id']]);
        $order = $sales->convertQuotation($quotation, (int) $data['warehouse_id']);

        return redirect()->route('orders.show', $order)->with('success', "Sales order {$order->number} created.");
    }

    public function duplicate(Quotation $quotation): RedirectResponse
    {
        $copy = DB::transaction(function () use ($quotation) {
            $quotation->load('items');
            $copy = $quotation->replicate(['number', 'status']);
            $copy->fill(['status' => 'draft', 'quote_date' => today(), 'valid_until' => today()->addDays((int) setting('quotation_validity_days', 15)), 'user_id' => auth()->id()]);
            $copy->number = null;
            $copy->save();
            $copy->syncItems($quotation->items->toArray());

            return $copy;
        });

        return redirect()->route('quotations.edit', $copy)->with('success', "Copied to {$copy->number}.");
    }

    protected function publicUrl(Quotation $quotation): string
    {
        return URL::signedRoute('public.quotation', $quotation, now()->addDays(60));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    protected function formData(Quotation $quotation, array $lines): array
    {
        return [
            'quotation' => $quotation,
            'lines' => old('items', $lines),
            'customer' => Customer::find(old('customer_id', $quotation->customer_id)),
            'lead' => $quotation->lead_id ? Lead::find($quotation->lead_id) : null,
        ];
    }
}
