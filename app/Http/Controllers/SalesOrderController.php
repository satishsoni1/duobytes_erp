<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesDocumentRequest;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('orders.index', [
            'orders' => SalesOrder::visibleTo($request->user())->with(['customer', 'user'])
                ->when($request->query('status'), fn ($q, $v) => $v === 'open'
                    ? $q->whereNotIn('status', ['delivered', 'completed', 'cancelled'])
                    : $q->where('status', $v))
                ->when($request->query('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
                ->when($request->query('from'), fn ($q, $v) => $q->whereDate('order_date', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->whereDate('order_date', '<=', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('number', 'like', "%$v%")->orWhere('tally_voucher_number', 'like', "%$v%")
                    ->orWhereHas('customer', fn ($c) => $c->search($v))))
                ->latest('order_date')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $customer = Customer::find($request->integer('customer_id'));
        $order = new SalesOrder([
            'customer_id' => $customer?->id,
            'order_date' => today(),
            'warehouse_id' => Warehouse::default()?->id,
            'delivery_address' => $customer?->address,
            'discount' => 0,
            'delivery_charge' => 0,
        ]);

        return view('orders.form', $this->formData($order, []));
    }

    public function store(SalesDocumentRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request) {
            $order = SalesOrder::create($request->documentAttributes() + ['user_id' => $request->user()->id, 'status' => 'pending']);
            $order->syncItems($request->items());
            $order->logActivity('Order created', 'system');

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('success', 'Sales order created.');
    }

    public function show(Request $request, SalesOrder $order): View
    {
        $this->authorizeOrder($request, $order);
        $order->load(['customer', 'user', 'warehouse', 'quotation', 'items.product', 'payments.receiver', 'installations.technician', 'tickets', 'activities.user', 'attachments']);

        return view('orders.show', [
            'order' => $order,
            'publicUrl' => URL::signedRoute('public.invoice', $order, now()->addDays(90)),
            'technicians' => User::active()->role('technician')->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, SalesOrder $order): View|RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        if ($order->isLocked()) {
            return back()->with('error', 'Orders cannot be edited once stock is allocated. Cancel and re-create instead.');
        }
        $order->load('items');

        return view('orders.form', $this->formData($order, $order->items->toArray()));
    }

    public function update(SalesDocumentRequest $request, SalesOrder $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        abort_if($order->isLocked(), 422, 'Order is locked.');

        DB::transaction(function () use ($request, $order) {
            $order->update($request->documentAttributes());
            $order->syncItems($request->items());
            $order->refreshPaymentStatus();
            $order->logActivity('Order revised – new total '.money($order->total), 'system');
        });

        return redirect()->route('orders.show', $order)->with('success', 'Order updated.');
    }

    public function destroy(Request $request, SalesOrder $order): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        if ($order->status !== 'pending' || $order->payments()->exists()) {
            return back()->with('error', 'Only pending orders without payments can be deleted. Cancel the order instead.');
        }
        $order->delete();

        return redirect()->route('orders.index')->with('success', 'Order deleted.');
    }

    public function updateStatus(Request $request, SalesOrder $order, SalesService $sales): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('crm.order_statuses')))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['status'] === 'cancelled' && ! $request->user()->hasRole('admin', 'manager')) {
            abort(403, 'Only managers can cancel orders.');
        }

        $sales->changeStatus($order, $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Order status updated.');
    }

    public function invoice(SalesOrder $order): View
    {
        return view('orders.invoice', ['order' => $order->load(['customer', 'items', 'payments', 'user'])]);
    }

    public function publicInvoice(SalesOrder $order): View
    {
        return $this->invoice($order);
    }

    protected function authorizeOrder(Request $request, SalesOrder $order): void
    {
        $user = $request->user();
        abort_unless($user->seesAllRecords() || $user->hasRole('inventory') || $order->user_id === $user->id, 403);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    protected function formData(SalesOrder $order, array $lines): array
    {
        return [
            'order' => $order,
            'lines' => old('items', $lines),
            'customer' => Customer::find(old('customer_id', $order->customer_id)),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ];
    }
}
