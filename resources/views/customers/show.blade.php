@extends('layouts.app')
@section('title', $customer->name)
@section('content')
@php($u = auth()->user())
<x-page-header :title="$customer->display_name" :back="route('customers.index')" :subtitle="$customer->code.' · '.config('crm.customer_types.'.$customer->type).' · since '.$customer->created_at->format('M Y')">
    @if($customer->phone)<a href="tel:+{{ $customer->phone }}" class="btn btn-light btn-sm"><i class="bi bi-telephone"></i></a>@endif
    @if($u->canAccess('whatsapp') && $customer->whatsapp)<a href="{{ route('whatsapp.show', $customer->whatsapp) }}" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Chat @if($messageCount)<span class="badge bg-light text-success">{{ $messageCount }}</span>@endif</a>@endif
    @if($u->canAccess('sales'))
        <a href="{{ route('quotations.create', ['customer_id' => $customer->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-file-earmark-plus"></i> Quotation</a>
        <a href="{{ route('orders.create', ['customer_id' => $customer->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-bag-plus"></i> Order</a>
    @endif
    @if($u->canAccess('service'))<a href="{{ route('tickets.create', ['customer_id' => $customer->id]) }}" class="btn btn-light btn-sm"><i class="bi bi-wrench"></i> Service</a>@endif
    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><x-stat label="Lifetime value" :value="money($stats['revenue'])" icon="gem" color="primary" /></div>
    <div class="col-6 col-md-3"><x-stat label="Orders" :value="$stats['orders']" icon="bag" color="info" /></div>
    <div class="col-6 col-md-3"><x-stat label="Paid" :value="money($stats['paid'])" icon="cash-coin" color="success" /></div>
    <div class="col-6 col-md-3"><x-stat label="Outstanding" :value="money($stats['due'])" icon="hourglass-split" :color="$stats['due'] > 0 ? 'danger' : 'secondary'" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body"><dl class="dl-grid small">
            <dt>Phone</dt><dd>{{ $customer->phone }} @if($customer->alt_phone)<br>{{ $customer->alt_phone }}@endif</dd>
            <dt>WhatsApp</dt><dd>{{ $customer->whatsapp }} @unless($customer->whatsapp_opt_in)<span class="badge badge-soft-danger">opted out</span>@endunless</dd>
            <dt>Email</dt><dd>{{ $customer->email ?? '—' }}</dd>
            <dt>Address</dt><dd>{{ collect([$customer->address, $customer->city, $customer->state, $customer->pincode])->filter()->implode(', ') ?: '—' }}</dd>
            <dt>GSTIN</dt><dd>{{ $customer->tax_number ?? '—' }}</dd>
            <dt>Source</dt><dd>{{ config("crm.lead_sources.{$customer->source}", '—') }}</dd>
            <dt>Birthday</dt><dd>{{ $customer->birthday?->format('d M') ?? '—' }}</dd>
            <dt>Anniversary</dt><dd>{{ $customer->anniversary?->format('d M') ?? '—' }}</dd>
            <dt>Account mgr</dt><dd>{{ $customer->assignee?->name ?? '—' }}</dd>
            @if($customer->notes)<dt>Notes</dt><dd style="white-space:pre-line">{{ $customer->notes }}</dd>@endif
        </dl></div></div>
        @if($customer->leads->isNotEmpty())
            <div class="card mb-3"><div class="card-header">Enquiries</div><div class="list-group list-group-flush">
                @foreach($customer->leads as $l)
                    <a href="{{ route('leads.show', $l) }}" class="list-group-item list-group-item-action small d-flex justify-content-between"><span>{{ $l->code }} · {{ $l->source_label }}</span>{!! status_badge('lead_statuses', $l->status) !!}</a>
                @endforeach
            </div></div>
        @endif
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header p-0 border-bottom-0"><ul class="nav nav-tabs px-2 pt-2">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#c-orders">Orders ({{ $customer->orders->count() }})</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-quotes">Quotations ({{ $customer->quotations->count() }})</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-install">Installations ({{ $customer->installations->count() }})</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-tickets">Service ({{ $customer->tickets->count() }})</button></li>
            </ul></div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="c-orders"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead><tbody>
                    @forelse($customer->orders as $o)
                        <tr><td><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td><td class="small">{{ $o->order_date->format('d M Y') }}</td><td>{!! status_badge('order_statuses', $o->status) !!}</td>
                            <td class="text-end">{{ money($o->total) }}</td><td class="text-end {{ $o->balance > 0 ? 'text-danger' : '' }}">{{ money($o->balance) }}</td></tr>
                    @empty <tr><td colspan="5" class="text-muted small p-3">No orders yet.</td></tr> @endforelse
                </tbody></table></div></div>
                <div class="tab-pane fade" id="c-quotes"><div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Quotation</th><th>Date</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody>
                    @forelse($customer->quotations as $q)
                        <tr><td><a href="{{ route('quotations.show', $q) }}">{{ $q->number }}</a></td><td class="small">{{ $q->quote_date->format('d M Y') }}</td>
                            <td><span class="badge badge-soft-{{ config("crm.quotation_statuses.{$q->status}") }}">{{ ucfirst($q->status) }}</span></td><td class="text-end">{{ money($q->total) }}</td></tr>
                    @empty <tr><td colspan="4" class="text-muted small p-3">No quotations.</td></tr> @endforelse
                </tbody></table></div></div>
                <div class="tab-pane fade" id="c-install"><div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Request</th><th>Scheduled</th><th>Technician</th><th>Status</th></tr></thead><tbody>
                    @forelse($customer->installations as $i)
                        <tr><td><a href="{{ route('installations.show', $i) }}">{{ $i->number }}</a></td><td class="small">{{ $i->scheduled_date?->format('d M Y') ?? '—' }}</td>
                            <td class="small">{{ $i->technician?->name ?? '—' }}</td><td>{!! status_badge('installation_statuses', $i->status) !!}</td></tr>
                    @empty <tr><td colspan="4" class="text-muted small p-3">No installations.</td></tr> @endforelse
                </tbody></table></div></div>
                <div class="tab-pane fade" id="c-tickets"><div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Opened</th></tr></thead><tbody>
                    @forelse($customer->tickets as $t)
                        <tr><td><a href="{{ route('tickets.show', $t) }}">{{ $t->number }}</a></td><td class="small">{{ $t->subject }}</td>
                            <td>{!! status_badge('ticket_statuses', $t->status) !!}</td><td class="small">{{ $t->created_at->format('d M Y') }}</td></tr>
                    @empty <tr><td colspan="4" class="text-muted small p-3">No service tickets.</td></tr> @endforelse
                </tbody></table></div></div>
            </div>
        </div>
        @include('partials.timeline', ['subject' => $customer, 'assignees' => $users])
    </div>
</div>
@endsection
