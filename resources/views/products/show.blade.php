@extends('layouts.app')
@section('title', $product->name)
@section('content')
@php($canEdit = auth()->user()->canAccess('inventory'))
<x-page-header :title="$product->name" :back="route('products.index')" :subtitle="$product->sku.($product->category ? ' · '.e($product->category->name) : '').($product->is_active ? '' : ' · <span class=\'badge badge-soft-danger\'>Inactive</span>')">
    @if($canEdit)
        <a href="{{ route('stock.adjust', ['product_id' => $product->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-plus-slash-minus"></i> Adjust stock</a>
        <a href="{{ route('stock.transfer', ['product_id' => $product->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left-right"></i> Transfer</a>
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
    @endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            @if($product->image_url)<img src="{{ $product->image_url }}" class="card-img-top" alt="" style="max-height:260px;object-fit:cover">@endif
            <div class="card-body"><dl class="dl-grid small">
                <dt>Selling price</dt><dd class="fw-bold">{{ money($product->selling_price) }}</dd>
                @if($product->mrp)<dt>MRP</dt><dd>{{ money($product->mrp) }}</dd>@endif
                @if($canEdit)<dt>Cost price</dt><dd>{{ money($product->cost_price) }} <span class="text-muted">({{ $product->selling_price > 0 ? round(($product->selling_price - $product->cost_price) / $product->selling_price * 100, 1) : 0 }}% margin)</span></dd>@endif
                <dt>GST</dt><dd>{{ (float) $product->tax_rate }}% {{ $product->hsn_code ? '· HSN '.$product->hsn_code : '' }}</dd>
                <dt>Material</dt><dd>{{ $product->material ?? '—' }}</dd>
                <dt>Colour / finish</dt><dd>{{ collect([$product->color, $product->finish])->filter()->implode(' / ') ?: '—' }}</dd>
                <dt>Dimensions</dt><dd>{{ $product->dimensions ?? '—' }}</dd>
                <dt>Warranty</dt><dd>{{ $product->warranty_months }} months</dd>
                <dt>Installation</dt><dd>{{ $product->requires_installation ? 'Required' : 'No' }}</dd>
                <dt>Reorder level</dt><dd>{{ $product->reorder_level }}</dd>
            </dl>
            @if($product->description)<p class="small mt-3 mb-0" style="white-space:pre-line">{{ $product->description }}</p>@endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header">Stock by location <span class="float-end">Total: <strong>{{ $product->total_stock }} {{ $product->unit }}</strong></span></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                @forelse($product->stocks as $s)
                    <tr><td>{{ $s->warehouse->name }} <span class="text-muted small">({{ ucfirst($s->warehouse->type) }})</span></td><td class="text-end fw-semibold">{{ $s->quantity }}</td></tr>
                @empty
                    <tr><td class="text-muted small p-3">No stock recorded.</td></tr>
                @endforelse
            </tbody></table></div></div>
        @if($canEdit)
        <div class="card"><div class="card-header">Stock ledger</div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Date</th><th>Type</th><th>Location</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Note</th><th>By</th></tr></thead>
                <tbody>
                @forelse($movements as $m)
                    <tr><td class="small text-nowrap">{{ $m->created_at->format('d M y H:i') }}</td><td class="small">{{ config("crm.stock_movement_types.{$m->type}") }}</td>
                        <td class="small">{{ $m->warehouse->name }}</td><td class="text-end fw-semibold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                        <td class="text-end">{{ $m->balance_after }}</td><td class="small">{{ $m->note }}</td><td class="small text-muted">{{ $m->user?->name }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-muted small p-3">No movements yet.</td></tr>
                @endforelse
                </tbody></table></div>
            <div class="card-body py-2">{{ $movements->links() }}</div>
        </div>
        @endif
    </div>
</div>
@endsection
