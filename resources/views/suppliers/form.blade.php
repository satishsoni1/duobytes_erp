@extends('layouts.app')
@section('title', $supplier->exists ? 'Edit Supplier' : 'New Supplier')
@section('content')
<x-page-header :title="$supplier->exists ? 'Edit '.$supplier->name : 'New supplier'" :back="route('suppliers.index')" />
<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card" style="max-width:800px"><div class="card-body row g-3">
    @csrf @if($supplier->exists) @method('PUT') @endif
    <x-field name="name" label="Supplier / Manufacturer" :value="$supplier->name" required />
    <x-field name="contact_person" label="Contact person" :value="$supplier->contact_person" />
    <x-field name="phone" label="Phone" :value="$supplier->phone" col="col-md-4" />
    <x-field name="email" type="email" label="Email" :value="$supplier->email" col="col-md-4" />
    <x-field name="tax_number" label="GSTIN" :value="$supplier->tax_number" col="col-md-4" />
    <x-field name="address" type="textarea" label="Address" :value="$supplier->address" rows="2" />
    <x-field name="notes" type="textarea" label="Notes (lead times, payment terms…)" :value="$supplier->notes" rows="2" />
    <div class="col-12"><button class="btn btn-primary">Save</button></div>
</div></form>
@endsection
