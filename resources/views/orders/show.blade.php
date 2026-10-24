@extends('layouts.app')
@section('title', $order->number)
@section('content')
@php($statuses = config('crm.order_statuses'))
<x-page-header :title="'Order '.$order->number" :back="route('orders.index')"
    :subtitle="status_badge('order_statuses', $order->status).' · '.e($order->customer->name).' · '.$order->order_date->format('d M Y').' · '.e($order->user?->name).($order->tally_guid ? ' · <span class=\'badge badge-soft-info\'>Tally '.e($order->tally_voucher_type.' '.$order->tally_voucher_number).'</span>' : '')">
    <a href="{{ route('orders.invoice', $order) }}" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-printer"></i> Invoice</a>
    <button class="btn btn-light btn-sm" onclick="navigator.clipboard.writeText(@js($publicUrl)).then(() => this.innerHTML = '<i class=\'bi bi-check\'></i> Copied')"><i class="bi bi-link-45deg"></i> Invoice link</button>
    @if(auth()->user()->canAccess('installations'))<a href="{{ route('installations.create', ['order_id' => $order->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-tools"></i> Installation</a>@endif
    @if(auth()->user()->canAccess('service'))<a href="{{ route('tickets.create', ['order_id' => $order->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-wrench"></i> Service</a>@endif
    @unless($order->isLocked())<a href="{{ route('orders.edit', $order) }}" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endunless
</x-page-header>

{{-- Progress tracker --}}
@if($order->status !== 'cancelled')
    @php($flow = ['pending', 'confirmed', 'in_production', 'ready', 'dispatched', 'delivered', 'completed'])
    @php($pos = array_search($order->status, $flow))
    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 justify-content-between">
        @foreach($flow as $i => $s)
            <div class="text-center flex-fill small">
                <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center {{ $i <= $pos ? 'bg-success text-white' : 'bg-light text-muted' }}" style="width:30px;height:30px">
                    <i class="bi bi-{{ $i < $pos ? 'check-lg' : 'circle' }}"></i></div>
                <span class="{{ $i === $pos ? 'fw-bold' : 'text-muted' }}">{{ $statuses[$s]['label'] }}</span>
            </div>
        @endforeach
    </div></div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Disc</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                <tr><td>{{ $loop->iteration }}</td><td>{{ $item->description }}@if($item->product)<div class="small text-muted">{{ $item->product->sku }}</div>@endif</td>
                    <td class="text-end">{{ $item->quantity }}</td><td class="text-end">{{ money($item->unit_price, false) }}</td>
                    <td class="text-end">{{ (float) $item->discount_percent }}%</td><td class="text-end">{{ (float) $item->tax_rate }}%</td><td class="text-end">{{ money($item->total, false) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr><td colspan="6" class="text-end">Subtotal</td><td class="text-end">{{ money($order->subtotal, false) }}</td></tr>
                <tr><td colspan="6" class="text-end">Tax</td><td class="text-end">{{ money($order->tax_total, false) }}</td></tr>
                @if($order->discount > 0)<tr><td colspan="6" class="text-end">Discount</td><td class="text-end">− {{ money($order->discount, false) }}</td></tr>@endif
                @if($order->delivery_charge > 0)<tr><td colspan="6" class="text-end">Delivery</td><td class="text-end">{{ money($order->delivery_charge, false) }}</td></tr>@endif
                <tr class="fw-bold"><td colspan="6" class="text-end">Total</td><td class="text-end">{{ money($order->total) }}</td></tr>
            </tfoot>
        </table></div></div>

        <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Payments</span>
            <span class="small">Paid {{ money($order->paid_amount) }} · <span class="{{ $order->balance > 0 ? 'text-danger' : 'text-success' }}">Balance {{ money($order->balance) }}</span></span></div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <tbody>
                @forelse($order->payments as $p)
                    <tr><td>{{ $p->paid_on->format('d M Y') }}</td><td>{{ config("crm.payment_methods.{$p->method}") }}</td><td class="small text-muted">{{ $p->reference }}</td>
                        <td class="small">{{ $p->receiver?->name }}</td><td class="text-end fw-semibold">{{ money($p->amount) }}</td>
                        <td class="text-end">@if(auth()->user()->hasRole('admin', 'manager'))
                            <form method="POST" action="{{ route('payments.destroy', $p) }}" data-confirm="Delete this payment?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i></button></form>@endif</td></tr>
                @empty
                    <tr><td class="text-muted small p-3">No payments recorded.</td></tr>
                @endforelse
                </tbody></table></div>
            @if($order->balance > 0 && $order->status !== 'cancelled' && auth()->user()->canAccess('payments'))
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('payments.store', $order) }}" class="row g-2">
                        @csrf
                        <div class="col-md-2"><input type="number" step="0.01" min="0.01" max="{{ $order->balance }}" name="amount" class="form-control form-control-sm" placeholder="Amount" value="{{ $order->balance }}" required></div>
                        <div class="col-md-2"><select name="method" class="form-select form-select-sm">@foreach(config('crm.payment_methods') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                        <div class="col-md-2"><input type="date" name="paid_on" class="form-control form-control-sm" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                        <div class="col-md-3"><input name="reference" class="form-control form-control-sm" placeholder="Txn / cheque ref."></div>
                        <div class="col-md-3"><button class="btn btn-sm btn-success w-100"><i class="bi bi-cash"></i> Record payment</button></div>
                    </form>
                </div>
            @endif
        </div>

        @include('partials.timeline', ['subject' => $order])
    </div>

    <div class="col-lg-4">
        @if(! in_array($order->status, ['completed', 'cancelled']))
            <div class="card mb-3"><div class="card-header">Update status</div><div class="card-body">
                <form method="POST" action="{{ route('orders.status', $order) }}" class="row g-2">
                    @csrf @method('PATCH')
                    <div class="col-12"><select name="status" class="form-select form-select-sm">
                        @foreach($statuses as $k => $d)
                            @if($k !== 'cancelled' || auth()->user()->hasRole('admin', 'manager'))<option value="{{ $k }}" @selected($order->status === $k)>{{ $d['label'] }}</option>@endif
                        @endforeach</select></div>
                    <div class="col-12"><input name="note" class="form-control form-control-sm" placeholder="Note (e.g. vehicle no.)"></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary w-100">Update</button></div>
                </form>
                <div class="small text-muted mt-2">
                    @if($order->stock_deducted)<i class="bi bi-check-circle text-success"></i> Stock allocated from {{ $order->warehouse->name }}.
                    @else<i class="bi bi-info-circle"></i> Stock is deducted from {{ $order->warehouse->name }} when confirmed.@endif
                </div>
            </div></div>
        @endif

        <div class="card mb-3"><div class="card-body"><dl class="dl-grid small">
            <dt>Customer</dt><dd><a href="{{ route('customers.show', $order->customer) }}">{{ $order->customer->name }}</a><br>{{ $order->customer->phone }}</dd>
            <dt>Delivery</dt><dd>{{ $order->delivery_date?->format('d M Y') ?? '—' }}</dd>
            <dt>Address</dt><dd>{{ $order->delivery_address ?? '—' }}</dd>
            <dt>Installation</dt><dd>{{ $order->requires_installation ? 'Required' : 'Not required' }}</dd>
            @if($order->quotation)<dt>Quotation</dt><dd><a href="{{ route('quotations.show', $order->quotation) }}">{{ $order->quotation->number }}</a></dd>@endif
            @if($order->notes)<dt>Notes</dt><dd style="white-space:pre-line">{{ $order->notes }}</dd>@endif
        </dl></div></div>

        @if($order->installations->isNotEmpty())
            <div class="card mb-3"><div class="card-header">Installations</div><div class="list-group list-group-flush">
                @foreach($order->installations as $i)
                    <a href="{{ route('installations.show', $i) }}" class="list-group-item list-group-item-action small d-flex justify-content-between">
                        <span>{{ $i->number }}<br><span class="text-muted">{{ $i->scheduled_date?->format('d M') ?? 'Not scheduled' }} · {{ $i->technician?->name ?? '—' }}</span></span>{!! status_badge('installation_statuses', $i->status) !!}</a>
                @endforeach
            </div></div>
        @endif
        @if($order->tickets->isNotEmpty())
            <div class="card mb-3"><div class="card-header">Service tickets</div><div class="list-group list-group-flush">
                @foreach($order->tickets as $t)
                    <a href="{{ route('tickets.show', $t) }}" class="list-group-item list-group-item-action small d-flex justify-content-between"><span>{{ $t->number }} · {{ $t->subject }}</span>{!! status_badge('ticket_statuses', $t->status) !!}</a>
                @endforeach
            </div></div>
        @endif
        @if($order->status === 'pending' && $order->payments->isEmpty() && auth()->user()->hasRole('admin', 'manager'))
            <form method="POST" action="{{ route('orders.destroy', $order) }}" data-confirm="Delete this order?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger w-100">Delete order</button></form>
        @endif
    </div>
</div>
@endsection
