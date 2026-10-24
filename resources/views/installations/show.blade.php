@extends('layouts.app')
@section('title', $installation->number)
@section('content')
<x-page-header :title="'Installation '.$installation->number" :back="route('installations.index')" :subtitle="status_badge('installation_statuses', $installation->status).' · '.e($installation->customer->name)">
    @if($installation->contact_phone)<a href="tel:+{{ $installation->contact_phone }}" class="btn btn-light btn-sm"><i class="bi bi-telephone"></i> Call</a>@endif
    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($installation->address) }}" target="_blank" rel="noopener" class="btn btn-light btn-sm"><i class="bi bi-geo-alt"></i> Map</a>
    @unless(auth()->user()->hasRole('technician'))<a href="{{ route('installations.edit', $installation) }}" class="btn btn-light btn-sm"><i class="bi bi-calendar-event"></i> Schedule / edit</a>@endunless
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body"><dl class="dl-grid small">
            <dt>Customer</dt><dd>@if(auth()->user()->canAccess('customers'))<a href="{{ route('customers.show', $installation->customer) }}">{{ $installation->customer->name }}</a>@else{{ $installation->customer->name }}@endif</dd>
            <dt>Contact</dt><dd>{{ $installation->contact_phone ?? $installation->customer->phone }}</dd>
            <dt>Address</dt><dd style="white-space:pre-line">{{ $installation->address }}</dd>
            <dt>Schedule</dt><dd>{{ $installation->scheduled_date?->format('D, d M Y') ?? 'Not scheduled' }}<br>{{ $installation->slot_label }}</dd>
            <dt>Technician</dt><dd>{{ $installation->technician?->name ?? '—' }}</dd>
            <dt>Priority</dt><dd>{{ ucfirst($installation->priority) }}</dd>
            @if($installation->order)<dt>Order</dt><dd>@if(auth()->user()->canAccess('sales'))<a href="{{ route('orders.show', $installation->order) }}">{{ $installation->order->number }}</a>@else{{ $installation->order->number }}@endif</dd>@endif
            @if($installation->instructions)<dt>Instructions</dt><dd style="white-space:pre-line">{{ $installation->instructions }}</dd>@endif
            @if($installation->started_at)<dt>Started</dt><dd>{{ $installation->started_at->format('d M h:i A') }}</dd>@endif
            @if($installation->completed_at)<dt>Completed</dt><dd>{{ $installation->completed_at->format('d M h:i A') }}</dd>@endif
            @if($installation->completion_notes)<dt>Work done</dt><dd style="white-space:pre-line">{{ $installation->completion_notes }}</dd>@endif
            @if($installation->customer_rating)<dt>Rating</dt><dd>{{ str_repeat('★', $installation->customer_rating) }}{{ str_repeat('☆', 5 - $installation->customer_rating) }} {{ $installation->customer_feedback }}</dd>@endif
        </dl></div></div>

        @if($installation->order)
            <div class="card mb-3"><div class="card-header">Items to install</div><ul class="list-group list-group-flush small">
                @foreach($installation->order->items as $item)<li class="list-group-item d-flex justify-content-between"><span>{{ $item->description }}</span><span>× {{ $item->quantity }}</span></li>@endforeach
            </ul></div>
        @endif

        @if(! in_array($installation->status, ['completed', 'cancelled']))
            <div class="card mb-3"><div class="card-header">Field update</div><div class="card-body">
                @if($installation->status !== 'in_progress')
                    <form method="POST" action="{{ route('installations.status', $installation) }}" class="mb-3">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="in_progress"><button class="btn btn-warning w-100"><i class="bi bi-play-fill"></i> Start work</button></form>
                @endif
                <form method="POST" action="{{ route('installations.status', $installation) }}" class="row g-2">@csrf @method('PATCH')
                    <input type="hidden" name="status" value="completed">
                    <div class="col-12"><textarea name="completion_notes" class="form-control form-control-sm" rows="3" placeholder="Work done, issues, parts used…" required></textarea></div>
                    <div class="col-5"><select name="customer_rating" class="form-select form-select-sm"><option value="">Rating</option>@for($r = 5; $r >= 1; $r--)<option value="{{ $r }}">{{ $r }} ★</option>@endfor</select></div>
                    <div class="col-7"><input name="customer_feedback" class="form-control form-control-sm" placeholder="Customer remark"></div>
                    <div class="col-12"><button class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> Mark completed</button></div>
                </form>
                <div class="small text-muted mt-2">Upload site/finished photos and the signed job sheet in Files.</div>
                @unless(auth()->user()->hasRole('technician'))
                    <form method="POST" action="{{ route('installations.status', $installation) }}" data-confirm="Cancel this installation?" class="mt-3">@csrf @method('PATCH')
                        <input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-outline-danger w-100">Cancel request</button></form>
                @endunless
            </div></div>
        @endif
    </div>
    <div class="col-lg-8">@include('partials.timeline', ['subject' => $installation])</div>
</div>
@endsection
