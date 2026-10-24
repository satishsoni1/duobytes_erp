@extends('layouts.app')
@section('title', 'Stock Ledger')
@section('content')
<x-page-header title="Stock ledger" :back="route('stock.index')" />
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-3"><select name="warehouse_id" class="form-select form-select-sm"><option value="">All locations</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>{{ $w->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="type" class="form-select form-select-sm"><option value="">All types</option>@foreach(config('crm.stock_movement_types') as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="From"></div>
    <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="To"></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Date</th><th>Product</th><th>Location</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Reference</th><th>By</th></tr></thead>
    <tbody>
    @forelse($movements as $m)
        <tr><td class="small text-nowrap">{{ $m->created_at->format('d M y H:i') }}</td>
            <td><a href="{{ route('products.show', $m->product_id) }}">{{ $m->product->name }}</a></td><td class="small">{{ $m->warehouse->name }}</td>
            <td class="small">{{ config("crm.stock_movement_types.{$m->type}") }}</td>
            <td class="text-end fw-semibold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
            <td class="text-end">{{ $m->balance_after }}</td>
            <td class="small">
                @if($m->reference instanceof \App\Models\SalesOrder)<a href="{{ route('orders.show', $m->reference) }}">{{ $m->reference->number }}</a>
                @elseif($m->reference instanceof \App\Models\PurchaseOrder)<a href="{{ route('purchase-orders.show', $m->reference) }}">{{ $m->reference->number }}</a>
                @else{{ $m->note }}@endif</td>
            <td class="small text-muted">{{ $m->user?->name }}</td></tr>
    @empty
        <tr><td colspan="8"><x-empty message="No movements." icon="journal" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $movements->links() }}</div>
@endsection
