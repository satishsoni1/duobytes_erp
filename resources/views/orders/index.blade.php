@extends('layouts.app')
@section('title', 'Sales Orders')
@section('content')
<x-page-header title="Sales orders" :subtitle="$orders->total().' orders'">
    <a href="{{ route('orders.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Order</a>
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Order no. or customer…"></div>
    <div class="col-6 col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open</option>
        @foreach(config('crm.order_statuses') as $k => $d)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $d['label'] }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><select name="payment_status" class="form-select form-select-sm"><option value="">Payment</option>
        @foreach(['unpaid', 'partial', 'paid'] as $s)<option value="{{ $s }}" @selected(request('payment_status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="From"></div>
    <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="To"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Go</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Delivery</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
    @forelse($orders as $o)
        <tr><td><a href="{{ route('orders.show', $o) }}" class="fw-semibold">{{ $o->number }}</a>@if($o->tally_guid) <span class="badge badge-soft-info" title="Imported from Tally">Tally {{ $o->tally_voucher_number }}</span>@endif<div class="small text-muted">{{ $o->user?->name }}</div></td>
            <td>{{ $o->customer->name }}<div class="small text-muted">{{ $o->customer->phone }}</div></td>
            <td class="small">{{ $o->order_date->format('d M Y') }}</td>
            <td class="small {{ $o->delivery_date?->isPast() && ! in_array($o->status, ['delivered', 'completed', 'cancelled']) ? 'overdue' : '' }}">{{ $o->delivery_date?->format('d M Y') ?? '—' }}</td>
            <td>{!! status_badge('order_statuses', $o->status) !!}</td>
            <td><span class="badge badge-soft-{{ ['unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success'][$o->payment_status] }}">{{ ucfirst($o->payment_status) }}</span></td>
            <td class="text-end">{{ money($o->total) }}</td><td class="text-end {{ $o->balance > 0 ? 'text-danger' : '' }}">{{ money($o->balance) }}</td></tr>
    @empty
        <tr><td colspan="8"><x-empty message="No orders found." icon="bag" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
