@extends('layouts.app')
@section('title', $installation->exists ? 'Edit '.$installation->number : 'New Installation')
@section('content')
<x-page-header :title="$installation->exists ? 'Edit '.$installation->number : 'New installation request'" :back="$installation->exists ? route('installations.show', $installation) : route('installations.index')" />
<form method="POST" action="{{ $installation->exists ? route('installations.update', $installation) : route('installations.store') }}" class="card"><div class="card-body row g-3">
    @csrf @if($installation->exists) @method('PUT') @endif
    @include('partials.customer-picker', ['col' => 'col-md-6'])
    <x-field name="sales_order_id" type="select" label="Against order" :options="$orders->pluck('number', 'id')" :value="$installation->sales_order_id" col="col-md-3" empty="— None —" />
    <x-field name="priority" type="select" label="Priority" :options="config('crm.priorities')" :value="$installation->priority" col="col-md-3" :empty="false" />
    <x-field name="address" type="textarea" label="Installation address" :value="$installation->address" required col="col-md-8" rows="2" />
    <x-field name="contact_phone" label="Site contact phone" :value="$installation->contact_phone" col="col-md-4" />
    <x-field name="scheduled_date" type="date" label="Date" :value="$installation->scheduled_date" col="col-md-4" />
    <x-field name="time_slot" type="select" label="Time slot" :options="config('crm.time_slots')" :value="$installation->time_slot" col="col-md-4" />
    <x-field name="technician_id" type="select" label="Technician" :options="$technicians->pluck('name', 'id')" :value="$installation->technician_id" col="col-md-4" />
    <x-field name="instructions" type="textarea" label="Instructions for technician" :value="$installation->instructions" col="col-12" placeholder="Wall-mount TV unit, 3rd floor no lift, carry drill bits for concrete…" />
    <div class="col-12 small text-muted">Setting a date + technician marks the request <strong>Scheduled</strong> and notifies the technician and customer (if enabled).</div>
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save</button></div></form>
@endsection
