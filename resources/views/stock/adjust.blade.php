@extends('layouts.app')
@section('title', 'Adjust Stock')
@section('content')
<x-page-header title="Stock adjustment" :back="route('stock.index')" subtitle="Physical count corrections, damages, opening stock and customer returns" />
<form method="POST" action="{{ route('stock.adjust.store') }}" class="card" style="max-width:720px"><div class="card-body row g-3">
    @csrf
    @include('partials.product-picker')
    <x-field name="warehouse_id" type="select" label="Location" :options="$warehouses->pluck('name', 'id')" :value="$warehouses->firstWhere('is_default', true)?->id" required :empty="false" />
    <x-field name="type" type="select" label="Reason" :options="collect(config('crm.stock_movement_types'))->only(['adjustment', 'damage', 'opening', 'sale_return'])" value="adjustment" required :empty="false" />
    <x-field name="direction" type="select" label="Action" :options="['add' => 'Add to stock', 'remove' => 'Remove from stock', 'set' => 'Set exact quantity (stock count)']" value="add" required :empty="false" />
    <x-field name="quantity" type="number" label="Quantity" required min="0" />
    <x-field name="note" label="Note" required col="col-12" placeholder="e.g. Monthly stock count, transit damage…" />
    <div class="col-12"><button class="btn btn-primary">Save adjustment</button></div>
</div></form>
@endsection
