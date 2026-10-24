@extends('layouts.app')
@section('title', $order->exists ? 'Edit '.$order->number : 'New Order')
@section('content')
<x-page-header :title="$order->exists ? 'Edit '.$order->number : 'New sales order'" :back="$order->exists ? route('orders.show', $order) : route('orders.index')" />
<form method="POST" action="{{ $order->exists ? route('orders.update', $order) : route('orders.store') }}">
    @csrf @if($order->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body row g-3">
        @include('partials.customer-picker', ['col' => 'col-md-5'])
        <x-field name="order_date" type="date" label="Order date" :value="$order->order_date" required col="col-md-2" />
        <x-field name="delivery_date" type="date" label="Promised delivery" :value="$order->delivery_date" col="col-md-2" />
        <x-field name="warehouse_id" type="select" label="Fulfil from" :options="$warehouses->pluck('name', 'id')" :value="$order->warehouse_id" required col="col-md-3" :empty="false" />
        <x-field name="delivery_address" type="textarea" label="Delivery address" :value="$order->delivery_address" col="col-md-8" rows="2" />
        <x-field name="requires_installation" type="checkbox" label="Requires installation / assembly" :value="$order->requires_installation" col="col-md-4" />
    </div></div>
    @include('partials.line-items', ['lines' => $lines, 'discount' => $order->discount, 'delivery' => $order->delivery_charge, 'showDelivery' => true])
    <div class="card mb-3"><div class="card-body"><x-field name="notes" type="textarea" label="Notes" :value="$order->notes" col="col-12" /></div></div>
    <div class="text-end"><button class="btn btn-primary">{{ $order->exists ? 'Save order' : 'Create order' }}</button></div>
</form>
@endsection
