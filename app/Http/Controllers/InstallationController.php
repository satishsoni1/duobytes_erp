<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Installation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\CrmAlert;
use App\Services\CustomerNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstallationController extends Controller
{
    public function __construct(protected CustomerNotifier $notifier) {}

    public function index(Request $request): View
    {
        return view('installations.index', [
            'installations' => Installation::visibleTo($request->user())->with(['customer', 'technician', 'order'])
                ->when($request->query('status'), fn ($q, $v) => $v === 'open'
                    ? $q->whereNotIn('status', ['completed', 'cancelled'])
                    : $q->where('status', $v))
                ->when($request->query('technician_id'), fn ($q, $v) => $q->where('technician_id', $v))
                ->when($request->query('date'), fn ($q, $v) => $q->whereDate('scheduled_date', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('number', 'like', "%$v%")
                    ->orWhereHas('customer', fn ($c) => $c->search($v))))
                ->orderByRaw('scheduled_date IS NULL DESC')->orderBy('scheduled_date')->paginate(25)->withQueryString(),
            'technicians' => $this->technicians(),
        ]);
    }

    public function calendar(Request $request): View
    {
        $start = CarbonImmutable::parse($request->query('week', today()))->startOfWeek();
        $items = Installation::visibleTo($request->user())->with(['customer', 'technician'])
            ->whereBetween('scheduled_date', [$start, $start->addDays(6)])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('time_slot')->get()
            ->groupBy(fn ($i) => $i->scheduled_date->toDateString());

        return view('installations.calendar', ['start' => $start, 'items' => $items, 'technicians' => $this->technicians()]);
    }

    public function create(Request $request): View
    {
        $order = SalesOrder::with('customer')->find($request->integer('order_id'));
        $customer = $order?->customer ?? Customer::find($request->integer('customer_id'));

        return view('installations.form', [
            'installation' => new Installation([
                'customer_id' => $customer?->id,
                'sales_order_id' => $order?->id,
                'address' => $order?->delivery_address ?? $customer?->address,
                'contact_phone' => $customer?->phone,
                'priority' => 'medium',
            ]),
            'customer' => $customer,
            'orders' => $customer ? $customer->orders()->latest()->get() : collect(),
            'technicians' => $this->technicians(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = ($data['scheduled_date'] ?? null) && ($data['technician_id'] ?? null) ? 'scheduled' : 'pending';
        $installation = Installation::create($data + ['created_by' => $request->user()->id]);
        $installation->logActivity('Installation request created', 'system');

        if ($installation->status === 'scheduled') {
            $this->announceSchedule($installation);
        }

        return redirect()->route('installations.show', $installation)->with('success', 'Installation request created.');
    }

    public function show(Request $request, Installation $installation): View
    {
        $this->authorizeView($request, $installation);
        $installation->load(['customer', 'technician', 'order.items', 'activities.user', 'attachments']);

        return view('installations.show', ['installation' => $installation, 'technicians' => $this->technicians()]);
    }

    public function edit(Installation $installation): View
    {
        $installation->load('customer');

        return view('installations.form', [
            'installation' => $installation,
            'customer' => $installation->customer,
            'orders' => $installation->customer->orders()->latest()->get(),
            'technicians' => $this->technicians(),
        ]);
    }

    public function update(Request $request, Installation $installation): RedirectResponse
    {
        abort_if($request->user()->hasRole('technician'), 403);
        $data = $this->validated($request);
        $scheduleChanged = ($data['scheduled_date'] ?? null) !== $installation->scheduled_date?->toDateString()
            || ($data['time_slot'] ?? null) !== $installation->time_slot
            || (int) ($data['technician_id'] ?? 0) !== (int) $installation->technician_id;

        $installation->fill($data);
        if ($scheduleChanged && $installation->scheduled_date && $installation->technician_id && ! in_array($installation->status, ['completed', 'cancelled', 'in_progress'], true)) {
            $installation->status = $installation->getOriginal('status') === 'pending' ? 'scheduled' : 'rescheduled';
        }
        $installation->save();

        if ($scheduleChanged && in_array($installation->status, ['scheduled', 'rescheduled'], true)) {
            $installation->logActivity('Scheduled for '.$installation->scheduled_date->format('d M Y').' '.$installation->slot_label.' with '.$installation->technician->name, 'status');
            $this->announceSchedule($installation);
        }

        return redirect()->route('installations.show', $installation)->with('success', 'Installation updated.');
    }

    public function destroy(Request $request, Installation $installation): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        $installation->delete();

        return redirect()->route('installations.index')->with('success', 'Installation deleted.');
    }

    /**
     * Field workflow: start → complete (with notes + rating) or cancel.
     */
    public function updateStatus(Request $request, Installation $installation): RedirectResponse
    {
        $this->authorizeView($request, $installation);
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed', 'cancelled', 'pending'])],
            'completion_notes' => ['nullable', 'required_if:status,completed', 'string', 'max:3000'],
            'customer_rating' => ['nullable', 'integer', 'between:1,5'],
            'customer_feedback' => ['nullable', 'string', 'max:255'],
        ]);

        $installation->fill($data);
        if ($data['status'] === 'in_progress') {
            $installation->started_at = now();
        }
        if ($data['status'] === 'completed') {
            $installation->completed_at = now();
        }
        $installation->save();
        $installation->logActivity('Status: '.config("crm.installation_statuses.{$data['status']}.label").(! empty($data['completion_notes']) ? " – {$data['completion_notes']}" : ''), 'status');

        if ($data['status'] === 'completed') {
            $order = $installation->order;
            if ($order && $order->status === 'delivered' && ! $order->installations()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                $order->update(['status' => 'completed']);
                $order->logActivity("Completed after installation {$installation->number}", 'status');
            }
            $this->notifier->send('installation_completed', $installation->customer, ['number' => $installation->number]);
        }

        return back()->with('success', 'Installation status updated.');
    }

    protected function announceSchedule(Installation $installation): void
    {
        $installation->loadMissing(['customer', 'technician']);
        $installation->technician?->notify(new CrmAlert(
            'Installation scheduled',
            "{$installation->number} – {$installation->customer->name} on ".$installation->scheduled_date->format('d M').' '.$installation->slot_label,
            route('installations.show', $installation),
            'tools',
            'info',
        ));

        $this->notifier->send('installation_scheduled', $installation->customer, [
            'number' => $installation->number,
            'date' => $installation->scheduled_date->format('d M Y'),
            'slot' => $installation->slot_label ?? '-',
            'technician' => $installation->technician?->name.($installation->technician?->phone ? " ({$installation->technician->phone})" : ''),
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
            'technician_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'technician')],
            'priority' => ['required', Rule::in(array_keys(config('crm.priorities')))],
            'address' => ['required', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'scheduled_date' => ['nullable', 'date'],
            'time_slot' => ['nullable', Rule::in(array_keys(config('crm.time_slots')))],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    protected function authorizeView(Request $request, Installation $installation): void
    {
        $user = $request->user();
        abort_if($user->hasRole('technician') && $installation->technician_id !== $user->id, 403);
    }

    protected function technicians()
    {
        return User::active()->role('technician')->orderBy('name')->get();
    }
}
