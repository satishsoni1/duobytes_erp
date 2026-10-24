@extends('layouts.app')
@section('title', $ticket->number)
@section('content')
<x-page-header :title="$ticket->number.' – '.$ticket->subject" :back="route('tickets.index')"
    :subtitle="status_badge('ticket_statuses', $ticket->status).' · '.e($ticket->customer->name).' · opened '.$ticket->created_at->diffForHumans()">
    @if(auth()->user()->canAccess('whatsapp') && $ticket->customer->whatsapp)<a href="{{ route('whatsapp.show', $ticket->customer->whatsapp) }}" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Chat</a>@endif
    @unless(auth()->user()->hasRole('technician'))<a href="{{ route('tickets.edit', $ticket) }}" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endunless
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body"><dl class="dl-grid small">
            <dt>Customer</dt><dd>@if(auth()->user()->canAccess('customers'))<a href="{{ route('customers.show', $ticket->customer) }}">{{ $ticket->customer->name }}</a>@else{{ $ticket->customer->name }}@endif<br>{{ $ticket->customer->phone }}</dd>
            <dt>Address</dt><dd>{{ $ticket->customer->address ?? '—' }}</dd>
            <dt>Type</dt><dd>{{ config("crm.ticket_types.{$ticket->type}") }}</dd>
            <dt>Priority</dt><dd>{{ ucfirst($ticket->priority) }}</dd>
            <dt>Reported via</dt><dd>{{ config("crm.ticket_sources.{$ticket->source}") }}</dd>
            <dt>SLA due</dt><dd class="{{ $ticket->isOverdue() ? 'overdue' : '' }}">{{ $ticket->due_at?->format('d M Y, h:i A') }}</dd>
            <dt>Product</dt><dd>{{ $ticket->product?->name ?? '—' }}</dd>
            <dt>Order</dt><dd>{{ $ticket->order?->number ?? '—' }}</dd>
            <dt>Warranty</dt><dd>@if($ticket->warranty_expires_on)<span class="badge {{ $ticket->is_under_warranty ? 'badge-soft-success' : 'badge-soft-danger' }}">{{ $ticket->is_under_warranty ? 'Covered' : 'Expired' }}</span> till {{ $ticket->warranty_expires_on->format('d M Y') }}@else Unknown @endif</dd>
            <dt>Assigned to</dt><dd>{{ $ticket->assignee?->name ?? '—' }}</dd>
            <dt>Visit</dt><dd>{{ $ticket->visit_date?->format('d M Y') ?? '—' }} {{ config("crm.time_slots.{$ticket->visit_slot}") }}</dd>
            @if($ticket->description)<dt>Details</dt><dd style="white-space:pre-line">{{ $ticket->description }}</dd>@endif
            @if($ticket->resolution)<dt>Resolution</dt><dd style="white-space:pre-line">{{ $ticket->resolution }}</dd>@endif
            @if($ticket->service_charge > 0)<dt>Charges</dt><dd>{{ money($ticket->service_charge) }}</dd>@endif
            @if($ticket->rating)<dt>Rating</dt><dd>{{ str_repeat('★', $ticket->rating) }}{{ str_repeat('☆', 5 - $ticket->rating) }} {{ $ticket->feedback }}</dd>@endif
        </dl></div></div>

        <div class="card mb-3"><div class="card-header">Update ticket</div><div class="card-body">
            <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="row g-2">@csrf @method('PATCH')
                <div class="col-12"><select name="status" class="form-select form-select-sm">@foreach(config('crm.ticket_statuses') as $k => $d)<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $d['label'] }}</option>@endforeach</select></div>
                <div class="col-12"><textarea name="resolution" class="form-control form-control-sm" rows="2" placeholder="Resolution (required when resolving)">{{ $ticket->resolution }}</textarea></div>
                <div class="col-6"><input type="number" step="0.01" min="0" name="service_charge" class="form-control form-control-sm" placeholder="Charges" value="{{ $ticket->service_charge > 0 ? $ticket->service_charge : '' }}" @disabled($ticket->is_under_warranty)></div>
                <div class="col-6"><select name="rating" class="form-select form-select-sm"><option value="">Rating</option>@for($r = 5; $r >= 1; $r--)<option value="{{ $r }}" @selected($ticket->rating === $r)>{{ $r }} ★</option>@endfor</select></div>
                <div class="col-12"><input name="feedback" class="form-control form-control-sm" placeholder="Customer feedback" value="{{ $ticket->feedback }}"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary w-100">Save</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">@include('partials.timeline', ['subject' => $ticket, 'assignees' => $staff])</div>
</div>
@endsection
