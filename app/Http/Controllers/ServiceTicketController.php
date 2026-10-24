<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\CrmAlert;
use App\Services\CustomerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceTicketController extends Controller
{
    public function __construct(protected CustomerNotifier $notifier) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('tickets.index', [
            'tickets' => ServiceTicket::visibleTo($request->user())->with(['customer', 'assignee', 'product'])
                ->when($status === 'open', fn ($q) => $q->open())
                ->when($status === 'overdue', fn ($q) => $q->open()->where('due_at', '<', now()))
                ->when(! in_array($status, ['open', 'overdue', 'all'], true), fn ($q) => $q->where('status', $status))
                ->when($request->query('priority'), fn ($q, $v) => $q->where('priority', $v))
                ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
                ->when($request->query('assigned_to'), fn ($q, $v) => $q->where('assigned_to', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('number', 'like', "%$v%")
                    ->orWhere('subject', 'like', "%$v%")->orWhereHas('customer', fn ($c) => $c->search($v))))
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
                ->orderBy('due_at')->paginate(25)->withQueryString(),
            'staff' => $this->staff(),
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $order = SalesOrder::with('customer')->find($request->integer('order_id'));
        $customer = $order?->customer ?? Customer::find($request->integer('customer_id'));

        return view('tickets.form', [
            'ticket' => new ServiceTicket([
                'customer_id' => $customer?->id,
                'sales_order_id' => $order?->id,
                'priority' => 'medium',
                'type' => 'repair',
                'source' => 'phone',
            ]),
            'customer' => $customer,
            ...$this->formOptions($customer),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ticket = new ServiceTicket($this->validated($request) + ['created_by' => $request->user()->id]);
        $ticket->status = $ticket->assigned_to ? 'assigned' : 'open';
        $ticket->evaluateWarranty();
        $ticket->save();
        $ticket->logActivity('Ticket created'.($ticket->is_under_warranty ? ' (under warranty until '.$ticket->warranty_expires_on->format('d M Y').')' : ''), 'system');

        $this->notifyAssignee($ticket);
        $this->notifier->send('ticket_created', $ticket->customer, ['number' => $ticket->number, 'subject' => $ticket->subject]);
        if ($ticket->visit_date) {
            $this->announceVisit($ticket);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', "Ticket {$ticket->number} created.");
    }

    public function show(Request $request, ServiceTicket $ticket): View
    {
        abort_if($request->user()->hasRole('technician') && $ticket->assigned_to !== $request->user()->id, 403);
        $ticket->load(['customer', 'order', 'product', 'assignee', 'activities.user', 'attachments', 'followUps.user']);

        return view('tickets.show', ['ticket' => $ticket, 'staff' => $this->staff()]);
    }

    public function edit(ServiceTicket $ticket): View
    {
        $ticket->load('customer');

        return view('tickets.form', ['ticket' => $ticket, 'customer' => $ticket->customer, ...$this->formOptions($ticket->customer)]);
    }

    public function update(Request $request, ServiceTicket $ticket): RedirectResponse
    {
        $data = $this->validated($request);
        $previousAssignee = $ticket->assigned_to;
        $previousVisit = $ticket->visit_date?->toDateString().$ticket->visit_slot;

        $ticket->fill($data);
        if ($ticket->isDirty(['sales_order_id', 'product_id'])) {
            $ticket->evaluateWarranty();
        }
        if ($ticket->isDirty('priority')) {
            $ticket->due_at = $ticket->created_at->copy()->addHours(config("crm.sla_hours.{$ticket->priority}", 72));
        }
        if ($ticket->assigned_to && $ticket->status === 'open') {
            $ticket->status = 'assigned';
        }
        $ticket->save();

        if ($ticket->assigned_to && $ticket->assigned_to !== $previousAssignee) {
            $ticket->logActivity('Assigned to '.$ticket->assignee->name, 'system');
            $this->notifyAssignee($ticket);
        }
        if ($ticket->visit_date && $ticket->visit_date->toDateString().$ticket->visit_slot !== $previousVisit) {
            $ticket->logActivity('Visit scheduled for '.$ticket->visit_date->format('d M Y'), 'system');
            $this->announceVisit($ticket);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    public function destroy(Request $request, ServiceTicket $ticket): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket deleted.');
    }

    public function updateStatus(Request $request, ServiceTicket $ticket): RedirectResponse
    {
        abort_if($request->user()->hasRole('technician') && $ticket->assigned_to !== $request->user()->id, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('crm.ticket_statuses')))],
            'resolution' => ['nullable', 'required_if:status,resolved', 'string', 'max:3000'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->fill(collect($data)->except('note')->filter(fn ($v) => $v !== null)->all());
        if ($data['status'] === 'resolved') {
            $ticket->resolved_at = now();
        }
        if ($data['status'] === 'closed') {
            $ticket->closed_at = now();
            $ticket->resolved_at ??= now();
        }
        $ticket->save();
        $ticket->logActivity('Status: '.config("crm.ticket_statuses.{$data['status']}.label").($data['resolution'] ?? $data['note'] ?? null ? ' – '.($data['resolution'] ?? $data['note']) : ''), 'status');

        if ($data['status'] === 'resolved') {
            $this->notifier->send('ticket_resolved', $ticket->customer, ['number' => $ticket->number]);
        }

        return back()->with('success', 'Ticket updated.');
    }

    protected function notifyAssignee(ServiceTicket $ticket): void
    {
        $ticket->loadMissing(['assignee', 'customer']);
        if ($ticket->assignee && $ticket->assigned_to !== auth()->id()) {
            $ticket->assignee->notify(new CrmAlert(
                'Service ticket assigned',
                "{$ticket->number} – {$ticket->subject} ({$ticket->customer->name})",
                route('tickets.show', $ticket),
                'wrench',
                $ticket->priority === 'urgent' ? 'danger' : 'warning',
            ));
        }
    }

    protected function announceVisit(ServiceTicket $ticket): void
    {
        $this->notifier->send('ticket_visit_scheduled', $ticket->customer, [
            'number' => $ticket->number,
            'date' => $ticket->visit_date->format('d M Y'),
            'slot' => config("crm.time_slots.{$ticket->visit_slot}", 'during business hours'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(config('crm.ticket_types')))],
            'source' => ['required', Rule::in(array_keys(config('crm.ticket_sources')))],
            'priority' => ['required', Rule::in(array_keys(config('crm.priorities')))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visit_date' => ['nullable', 'date'],
            'visit_slot' => ['nullable', Rule::in(array_keys(config('crm.time_slots')))],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(?Customer $customer): array
    {
        $orders = $customer ? $customer->orders()->with('items.product')->latest()->get() : collect();

        return [
            'orders' => $orders,
            'products' => $orders->isNotEmpty()
                ? $orders->flatMap->items->pluck('product')->filter()->unique('id')->values()
                : Product::active()->orderBy('name')->limit(500)->get(),
            'staff' => $this->staff(),
        ];
    }

    protected function staff()
    {
        return User::active()->role(['technician', 'support', 'manager'])->orderBy('name')->get();
    }
}
