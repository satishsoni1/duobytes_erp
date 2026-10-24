<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\LeadAssignmentService;
use App\Services\LeadCaptureService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function __construct(protected LeadAssignmentService $assignment) {}

    public function index(Request $request): View
    {
        $leads = $this->filtered($request)->with('assignee')->latest()->paginate(25)->withQueryString();

        return view('leads.index', ['leads' => $leads, 'users' => $this->salesUsers()]);
    }

    public function board(Request $request): View
    {
        $columns = collect(config('crm.lead_statuses'))->map(function ($def, $status) use ($request) {
            return $this->filtered($request)->with('assignee')
                ->where('status', $status)
                ->when(in_array($status, ['won', 'lost'], true), fn ($q) => $q->where('updated_at', '>=', now()->subDays(30)))
                ->latest('updated_at')->limit(50)->get();
        });

        return view('leads.board', ['columns' => $columns, 'users' => $this->salesUsers()]);
    }

    public function create(Request $request): View
    {
        // Allows prefilling from links such as the WhatsApp inbox "Create lead" button.
        $prefill = array_filter($request->only(['name', 'phone', 'whatsapp', 'email', 'source']));

        return view('leads.create', ['lead' => new Lead($prefill + ['source' => 'walk_in', 'priority' => 'warm']), 'users' => $this->salesUsers()]);
    }

    public function store(LeadRequest $request, LeadCaptureService $capture): RedirectResponse
    {
        $data = $request->validated();
        $data['assigned_to'] ??= $request->user()->hasRole('sales') ? $request->user()->id : null;

        [$lead, $created] = $capture->capture($data, $data['source'], [], $request->boolean('send_welcome'));

        if (! $created) {
            return redirect()->route('leads.show', $lead)->with('warning', 'A matching open lead already exists – the enquiry was added to its timeline.');
        }

        $lead->update(collect($data)->only(['expected_close_date', 'notes', 'address'])->filter()->all());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead created.');
    }

    public function show(Request $request, Lead $lead): View
    {
        $this->authorizeLead($request, $lead);
        $lead->load(['assignee', 'creator', 'customer', 'activities.user', 'followUps.user', 'attachments', 'quotations']);

        return view('leads.show', [
            'lead' => $lead,
            'users' => $this->salesUsers(),
            'messageCount' => $lead->phone ? WhatsappMessage::where('phone', $lead->phone)->count() : 0,
        ]);
    }

    public function edit(Request $request, Lead $lead): View
    {
        $this->authorizeLead($request, $lead);

        return view('leads.edit', ['lead' => $lead, 'users' => $this->salesUsers()]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validated();
        $newAssignee = $data['assigned_to'] ?? null;
        unset($data['assigned_to']);

        $lead->update($data);
        if ($request->user()->seesAllRecords() && (int) $newAssignee !== (int) $lead->assigned_to) {
            $this->assignment->assign($lead, User::find($newAssignee));
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        $lead->delete();

        return redirect()->route('leads.index')->with('success', 'Lead deleted.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse|JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('crm.lead_statuses')))],
            'lost_reason' => ['nullable', 'required_if:status,lost', Rule::in(array_keys(config('crm.lost_reasons')))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($lead->status !== $data['status']) {
            $old = config("crm.lead_statuses.{$lead->status}.label");
            $lead->update([
                'status' => $data['status'],
                'lost_reason' => $data['status'] === 'lost' ? $data['lost_reason'] : null,
                'converted_at' => $data['status'] === 'won' ? ($lead->converted_at ?? now()) : $lead->converted_at,
            ]);
            if (in_array($data['status'], ['won', 'lost'], true)) {
                $lead->followUps()->pending()->update(['completed_at' => now(), 'outcome' => 'Auto-closed: lead '.$data['status']]);
                $lead->update(['next_follow_up_at' => null]);
            }
            $lead->logActivity(
                "Status: $old → ".config("crm.lead_statuses.{$data['status']}.label")
                .($data['status'] === 'lost' ? ' ('.config("crm.lost_reasons.{$data['lost_reason']}").')' : '')
                .(! empty($data['note']) ? " – {$data['note']}" : ''),
                'status'
            );
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Lead status updated.');
    }

    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($request->user()->seesAllRecords(), 403);
        $data = $request->validate(['assigned_to' => ['nullable', 'exists:users,id']]);
        $this->assignment->assign($lead, User::find($data['assigned_to']));

        return back()->with('success', 'Lead reassigned.');
    }

    /**
     * Convert a lead into a customer (or link an existing one) and continue to a quotation.
     */
    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeLead($request, $lead);

        $customer = DB::transaction(function () use ($lead) {
            $customer = $lead->customer ?? Customer::findByContact($lead->phone, $lead->email) ?? Customer::create([
                'name' => $lead->name,
                'phone' => $lead->phone,
                'whatsapp' => $lead->whatsapp,
                'email' => $lead->email,
                'city' => $lead->city,
                'address' => $lead->address,
                'source' => $lead->source,
                'assigned_to' => $lead->assigned_to,
            ]);

            $lead->update([
                'customer_id' => $customer->id,
                'status' => in_array($lead->status, ['new', 'contacted', 'qualified'], true) ? 'showroom_visit' : $lead->status,
            ]);
            $lead->logActivity("Converted to customer {$customer->code}", 'system');
            $customer->logActivity("Created from lead {$lead->code} ({$lead->source_label})", 'system');

            return $customer;
        });

        return redirect()->route('quotations.create', ['customer_id' => $customer->id, 'lead_id' => $lead->id])
            ->with('success', "Customer {$customer->code} ready – create the quotation.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        abort_unless($request->user()->seesAllRecords(), 403);
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:assign,status,delete'],
            'assigned_to' => ['nullable', 'required_if:action,assign', 'exists:users,id'],
            'status' => ['nullable', 'required_if:action,status', Rule::in(array_keys(config('crm.lead_statuses')))],
        ]);

        $leads = Lead::whereIn('id', $data['ids'])->get();
        foreach ($leads as $lead) {
            match ($data['action']) {
                'assign' => $this->assignment->assign($lead, User::find($data['assigned_to'])),
                'status' => tap($lead)->update(['status' => $data['status']])->logActivity('Bulk status change to '.config("crm.lead_statuses.{$data['status']}.label"), 'status'),
                'delete' => $request->user()->hasRole('admin', 'manager') ? $lead->delete() : null,
            };
        }

        return back()->with('success', $leads->count().' leads updated.');
    }

    public function importForm(): View
    {
        return view('leads.import', ['users' => $this->salesUsers()]);
    }

    public function import(Request $request, LeadCaptureService $capture): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'source' => ['required', Rule::in(array_keys(config('crm.lead_sources')))],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $created = $duplicates = $skipped = 0;
        foreach (Csv::read($request->file('file')->getRealPath()) as $row) {
            if (blank($row['name'] ?? null) || (blank($row['phone'] ?? null) && blank($row['email'] ?? null))) {
                $skipped++;

                continue;
            }
            $source = array_key_exists($row['source'] ?? '', config('crm.lead_sources')) ? $row['source'] : $request->input('source');
            [, $isNew] = $capture->capture($row + ['assigned_to' => $request->input('assigned_to')], $source, ['import' => true], sendWelcome: false);
            $isNew ? $created++ : $duplicates++;
        }

        return redirect()->route('leads.index')->with('success', "Import finished: $created created, $duplicates matched existing leads, $skipped skipped.");
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('assignee')->latest();

        return Csv::download('leads-'.now()->format('Ymd-His').'.csv',
            ['Code', 'Name', 'Phone', 'Email', 'City', 'Source', 'Campaign', 'Status', 'Priority', 'Budget', 'Requirement', 'Assigned To', 'Next Follow-up', 'Created'],
            (function () use ($query) {
                foreach ($query->lazy(500) as $l) {
                    yield [$l->code, $l->name, $l->phone, $l->email, $l->city, $l->source_label, $l->campaign,
                        config("crm.lead_statuses.{$l->status}.label"), $l->priority, $l->budget, $l->requirement,
                        $l->assignee?->name, $l->next_follow_up_at?->format('Y-m-d H:i'), $l->created_at->format('Y-m-d H:i')];
                }
            })()
        );
    }

    protected function filtered(Request $request): Builder
    {
        return Lead::visibleTo($request->user())
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $v) => $v === 'open' ? $q->open() : $q->where('status', $v))
            ->when($request->query('source'), fn ($q, $v) => $q->where('source', $v))
            ->when($request->query('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->when($request->query('assigned_to'), fn ($q, $v) => $v === 'none' ? $q->whereNull('assigned_to') : $q->where('assigned_to', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->query('follow_up') === 'overdue', fn ($q) => $q->open()->where('next_follow_up_at', '<', now()))
            ->when($request->query('follow_up') === 'today', fn ($q) => $q->open()->whereDate('next_follow_up_at', today()));
    }

    protected function authorizeLead(Request $request, Lead $lead): void
    {
        $user = $request->user();
        abort_unless($user->seesAllRecords() || $lead->assigned_to === $user->id, 403);
    }

    protected function salesUsers()
    {
        return User::active()->role(['admin', 'manager', 'sales', 'support'])->orderBy('name')->get();
    }
}
