@extends('layouts.app')
@section('title', 'Transfer Stock')
@section('content')
<x-page-header title="Transfer stock" :back="route('stock.index')" subtitle="Move pieces between godown and showrooms" />
<form method="POST" action="{{ route('stock.transfer.store') }}" class="card" style="max-width:720px"><div class="card-body row g-3">
    @csrf
    @include('partials.product-picker')
    <x-field name="from_warehouse_id" type="select" label="From" :options="$warehouses->pluck('name', 'id')" required />
    <x-field name="to_warehouse_id" type="select" label="To" :options="$warehouses->pluck('name', 'id')" required />
    <x-field name="quantity" type="number" label="Quantity" required min="1" />
    <x-field name="note" label="Note" placeholder="Challan / vehicle no." />
    <div class="col-12"><button class="btn btn-primary">Transfer</button></div>
</div></form>
@endsection
