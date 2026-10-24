@extends('layouts.app')
@section('title', 'Stock')
@section('content')
@php($canEdit = auth()->user()->canAccess('inventory'))
<x-page-header title="Stock overview" :subtitle="'Valuation: '.money($valuation->cost ?? 0).' at cost · '.money($valuation->retail ?? 0).' at retail'">
    @if($canEdit)
        <a href="{{ route('stock.movements') }}" class="btn btn-light btn-sm"><i class="bi bi-journal-text"></i> Ledger</a>
        <a href="{{ route('stock.transfer') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left-right"></i> Transfer</a>
        <a href="{{ route('stock.adjust') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-slash-minus"></i> Adjust</a>
    @endif
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search product…"></div>
    <div class="col-md-3"><select name="stock" class="form-select form-select-sm"><option value="">All</option><option value="low" @selected(request('stock') === 'low')>At/below reorder level</option><option value="out" @selected(request('stock') === 'out')>Out of stock</option></select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Product</th>@foreach($warehouses as $w)<th class="text-end">{{ $w->name }}</th>@endforeach<th class="text-end">Total</th><th class="text-end">Reorder</th></tr></thead>
    <tbody>
    @forelse($products as $p)
        @php($byWh = $p->stocks->pluck('quantity', 'warehouse_id'))
        <tr><td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a><div class="small text-muted">{{ $p->sku }} · {{ $p->category?->name }}</div></td>
            @foreach($warehouses as $w)<td class="text-end">{{ $byWh[$w->id] ?? 0 }}</td>@endforeach
            <td class="text-end fw-bold {{ $p->isLowStock() ? 'text-danger' : '' }}">{{ $p->total_stock }}</td><td class="text-end text-muted">{{ $p->reorder_level }}</td></tr>
    @empty
        <tr><td colspan="{{ $warehouses->count() + 3 }}"><x-empty message="No stocked products." icon="boxes" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
