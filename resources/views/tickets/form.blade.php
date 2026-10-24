@extends('layouts.app')
@section('title', $ticket->exists ? 'Edit '.$ticket->number : 'New Service Ticket')
@section('content')
<x-page-header :title="$ticket->exists ? 'Edit '.$ticket->number : 'New service ticket'" :back="$ticket->exists ? route('tickets.show', $ticket) : route('tickets.index')" />
@unless($customer)
    <div class="alert alert-info small">Pick the customer first, then reload with their orders: choose below and press <em>Load orders</em>.</div>
    <form class="card card-body mb-3"><div class="row g-2 align-items-end">
        <div class="col-md-8"><label class="form-label small fw-semibold">Customer</label>
            <select name="customer_id" data-remote="{{ route('lookup.customers') }}" class="form-select" required placeholder="Search by name or phone…"></select></div>
        <div class="col-md-4"><button class="btn btn-light w-100">Load orders</button></div>
    </div></form>
@endunless
<form method="POST" action="{{ $ticket->exists ? route('tickets.update', $ticket) : route('tickets.store') }}" class="card"><div class="card-body row g-3">
    @csrf @if($ticket->exists) @method('PUT') @endif
    @include('partials.customer-picker', ['col' => 'col-md-6'])
    <x-field name="sales_order_id" type="select" label="Order" :options="$orders->mapWithKeys(fn ($o) => [$o->id => $o->number.' · '.$o->order_date->format('d M Y')])" :value="$ticket->sales_order_id" col="col-md-3" empty="— None —" />
    <x-field name="product_id" type="select" label="Product" :options="$products->pluck('name', 'id')" :value="$ticket->product_id" col="col-md-3" class="searchable" />
    <x-field name="subject" label="Issue summary" :value="$ticket->subject" required col="col-md-6" placeholder="Recliner mechanism stuck, drawer channel broken…" />
    <x-field name="type" type="select" label="Type" :options="config('crm.ticket_types')" :value="$ticket->type" col="col-md-2" :empty="false" />
    <x-field name="priority" type="select" label="Priority" :options="config('crm.priorities')" :value="$ticket->priority" col="col-md-2" :empty="false" />
    <x-field name="source" type="select" label="Reported via" :options="config('crm.ticket_sources')" :value="$ticket->source" col="col-md-2" :empty="false" />
    <x-field name="description" type="textarea" label="Details" :value="$ticket->description" col="col-12" rows="3" />
    <x-field name="assigned_to" type="select" label="Assign to" :options="$staff->mapWithKeys(fn ($s) => [$s->id => $s->name.' ('.$s->role_label.')'])" :value="$ticket->assigned_to" col="col-md-4" />
    <x-field name="visit_date" type="date" label="Visit date" :value="$ticket->visit_date" col="col-md-4" />
    <x-field name="visit_slot" type="select" label="Visit slot" :options="config('crm.time_slots')" :value="$ticket->visit_slot" col="col-md-4" />
    <div class="col-12 small text-muted">Warranty is checked automatically from the order's delivery date and the product's warranty period. SLA: {{ collect(config('crm.sla_hours'))->map(fn ($h, $p) => ucfirst($p).' '.$h.'h')->implode(' · ') }}.</div>
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save ticket</button></div></form>
@endsection
