@extends('layouts.app')
@section('title', 'Products')
@section('content')
@php($canEdit = auth()->user()->canAccess('inventory'))
<x-page-header title="Products" :subtitle="$products->total().' products'">
    <a href="{{ route('products.export') }}" class="btn btn-light btn-sm"><i class="bi bi-download"></i> Export</a>
    @if($canEdit)<a href="{{ route('products.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Product</a>@endif
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, SKU, material, brand…"></div>
    <div class="col-6 col-md-3"><select name="category" class="form-select form-select-sm"><option value="">All categories</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><select name="stock" class="form-select form-select-sm"><option value="">All stock</option><option value="low" @selected(request('stock') === 'low')>Low stock</option></select></div>
    <div class="col-6 col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Go</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th style="width:56px"></th><th>Product</th><th>Category</th><th>Specs</th><th class="text-end">Price</th><th class="text-end">Stock</th></tr></thead>
    <tbody>
    @forelse($products as $p)
        <tr>
            <td>@if($p->image_url)<img src="{{ $p->image_url }}" alt="" class="rounded" style="width:44px;height:44px;object-fit:cover">@else<div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="bi bi-image text-muted"></i></div>@endif</td>
            <td><a href="{{ route('products.show', $p) }}" class="fw-semibold">{{ $p->name }}</a><div class="small text-muted">{{ $p->sku }}</div></td>
            <td class="small">{{ $p->category?->name ?? '—' }}</td>
            <td class="small text-muted">{{ collect([$p->material, $p->color, $p->dimensions])->filter()->implode(' · ') }}</td>
            <td class="text-end">{{ money($p->selling_price) }}@if($p->mrp && $p->mrp > $p->selling_price)<div class="small text-muted text-decoration-line-through">{{ money($p->mrp) }}</div>@endif</td>
            <td class="text-end">@if($p->track_stock)<span class="badge {{ $p->isLowStock() ? 'badge-soft-danger' : 'badge-soft-success' }}">{{ $p->total_stock }} {{ $p->unit }}</span>@else<span class="text-muted small">made to order</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="6"><x-empty message="No products found." icon="box-seam" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
