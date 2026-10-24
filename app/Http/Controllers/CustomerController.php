<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::search($request->query('q'))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('city'), fn ($q, $v) => $q->where('city', $v))
            ->withCount('orders')
            ->withSum(['orders as revenue' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->latest()->paginate(25)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'cities' => Customer::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function create(): View
    {
        return view('customers.create', ['customer' => new Customer(['type' => 'individual', 'whatsapp_opt_in' => true]), 'users' => $this->users()]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        if ($existing = Customer::findByContact($request->input('phone'))) {
            return redirect()->route('customers.show', $existing)->with('warning', "A customer with this phone already exists ({$existing->code}).");
        }

        $customer = Customer::create($request->validated());
        $customer->logActivity('Customer created', 'system');

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'assignee', 'activities.user', 'followUps.user', 'attachments',
            'leads' => fn ($q) => $q->latest(),
            'quotations' => fn ($q) => $q->latest(),
            'orders' => fn ($q) => $q->latest(),
            'installations' => fn ($q) => $q->with('technician')->latest(),
            'tickets' => fn ($q) => $q->latest(),
        ]);

        $active = $customer->orders->where('status', '!=', 'cancelled');

        return view('customers.show', [
            'customer' => $customer,
            'users' => $this->users(),
            'stats' => [
                'revenue' => $active->sum('total'),
                'paid' => $active->sum('paid_amount'),
                'due' => $active->sum('total') - $active->sum('paid_amount'),
                'orders' => $active->count(),
            ],
            'messageCount' => $customer->phone ? WhatsappMessage::where('phone', $customer->phone)->count() : 0,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', ['customer' => $customer, 'users' => $this->users()]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        if ($customer->orders()->exists()) {
            return back()->with('error', 'Customers with orders cannot be deleted.');
        }
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Customer::search($request->query('q'))->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))->orderBy('id');

        return Csv::download('customers-'.now()->format('Ymd').'.csv',
            ['Code', 'Name', 'Company', 'Type', 'Phone', 'WhatsApp', 'Email', 'City', 'State', 'Pincode', 'Tax No.', 'Birthday', 'Created'],
            (function () use ($query) {
                foreach ($query->lazy(500) as $c) {
                    yield [$c->code, $c->name, $c->company_name, config("crm.customer_types.{$c->type}"), $c->phone, $c->whatsapp,
                        $c->email, $c->city, $c->state, $c->pincode, $c->tax_number, $c->birthday?->format('Y-m-d'), $c->created_at->format('Y-m-d')];
                }
            })()
        );
    }

    protected function users()
    {
        return User::active()->role(['admin', 'manager', 'sales'])->orderBy('name')->get();
    }
}
