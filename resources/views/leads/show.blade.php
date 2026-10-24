@extends('layouts.app')
@section('title', $lead->name)
@section('content')
<x-page-header :title="$lead->name" :back="route('leads.index')" :subtitle="$lead->code.' · '.$lead->source_label.' · created '.$lead->created_at->diffForHumans()">
    @if($lead->phone)
        <a href="tel:+{{ $lead->phone }}" class="btn btn-light btn-sm"><i class="bi bi-telephone"></i> Call</a>
        @if(auth()->user()->canAccess('whatsapp'))<a href="{{ route('whatsapp.show', $lead->whatsapp ?? $lead->phone) }}" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp @if($messageCount)<span class="badge bg-light text-success">{{ $messageCount }}</span>@endif</a>@endif
    @endif
    <a href="{{ route('leads.edit', $lead) }}" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i> Edit</a>
    @unless($lead->isClosed())
        <form method="POST" action="{{ route('leads.convert', $lead) }}">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-plus"></i> {{ $lead->customer ? 'New quotation' : 'Convert & quote' }}</button></form>
    @endunless
    @if(auth()->user()->hasRole('admin', 'manager'))
        <form method="POST" action="{{ route('leads.destroy', $lead) }}" data-confirm="Delete this lead?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                {!! status_badge('lead_statuses', $lead->status) !!}
                <span class="badge {{ $lead->priority === 'hot' ? 'badge-soft-danger' : ($lead->priority === 'warm' ? 'badge-soft-warning' : 'badge-soft-info') }}">{{ ucfirst($lead->priority) }}</span>
            </div>
            <dl class="dl-grid small">
                <dt>Phone</dt><dd>{{ $lead->phone ?? '—' }}</dd>
                @if($lead->whatsapp && $lead->whatsapp !== $lead->phone)<dt>WhatsApp</dt><dd>{{ $lead->whatsapp }}</dd>@endif
                <dt>Email</dt><dd>{{ $lead->email ?? '—' }}</dd>
                <dt>City</dt><dd>{{ $lead->city ?? '—' }}</dd>
                <dt>Requirement</dt><dd style="white-space:pre-line">{{ $lead->requirement ?? '—' }}</dd>
                <dt>Budget</dt><dd>{{ $lead->budget ? money($lead->budget) : '—' }}</dd>
                <dt>Campaign</dt><dd>{{ $lead->campaign ?? '—' }}</dd>
                <dt>Expected close</dt><dd>{{ $lead->expected_close_date?->format('d M Y') ?? '—' }}</dd>
                <dt>Last contacted</dt><dd>{{ $lead->last_contacted_at?->diffForHumans() ?? 'Never' }}</dd>
                <dt>Next follow-up</dt><dd class="{{ $lead->next_follow_up_at?->isPast() ? 'overdue' : '' }}">{{ $lead->next_follow_up_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                @if($lead->status === 'lost')<dt>Lost reason</dt><dd>{{ config("crm.lost_reasons.{$lead->lost_reason}") }}</dd>@endif
                @if($lead->customer)<dt>Customer</dt><dd><a href="{{ route('customers.show', $lead->customer) }}">{{ $lead->customer->code }}</a></dd>@endif
                @if($lead->address)<dt>Address</dt><dd>{{ $lead->address }}</dd>@endif
                @if($lead->notes)<dt>Notes</dt><dd style="white-space:pre-line">{{ $lead->notes }}</dd>@endif
            </dl>
        </div></div>

        <div class="card mb-3"><div class="card-header">Update status</div><div class="card-body">
            <form method="POST" action="{{ route('leads.status', $lead) }}" class="row g-2">
                @csrf @method('PATCH')
                <div class="col-12"><select name="status" class="form-select form-select-sm" onchange="document.getElementById('lostBox').classList.toggle('d-none', this.value !== 'lost')">
                    @foreach(config('crm.lead_statuses') as $k => $d)<option value="{{ $k }}" @selected($lead->status === $k)>{{ $d['label'] }}</option>@endforeach</select></div>
                <div class="col-12 {{ $lead->status === 'lost' ? '' : 'd-none' }}" id="lostBox"><select name="lost_reason" class="form-select form-select-sm"><option value="">Lost reason…</option>
                    @foreach(config('crm.lost_reasons') as $k => $v)<option value="{{ $k }}" @selected($lead->lost_reason === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-12"><input name="note" class="form-control form-control-sm" placeholder="Note (optional)"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary w-100">Update</button></div>
            </form>
        </div></div>

        <div class="card mb-3"><div class="card-header">Owner</div><div class="card-body">
            @if(auth()->user()->seesAllRecords())
                <form method="POST" action="{{ route('leads.assign', $lead) }}" class="d-flex gap-2">@csrf @method('PATCH')
                    <select name="assigned_to" class="form-select form-select-sm"><option value="">Unassigned</option>
                        @foreach($users as $u)<option value="{{ $u->id }}" @selected($lead->assigned_to === $u->id)>{{ $u->name }}</option>@endforeach</select>
                    <button class="btn btn-sm btn-light">Save</button></form>
            @else
                {{ $lead->assignee?->name ?? 'Unassigned' }}
            @endif
            @if($lead->creator)<div class="small text-muted mt-2">Created by {{ $lead->creator->name }}</div>@endif
        </div></div>

        @if($lead->quotations->isNotEmpty())
            <div class="card mb-3"><div class="card-header">Quotations</div><div class="list-group list-group-flush">
                @foreach($lead->quotations as $q)
                    <a href="{{ route('quotations.show', $q) }}" class="list-group-item list-group-item-action d-flex justify-content-between small">
                        <span>{{ $q->number }}</span><span>{{ money($q->total) }} <span class="badge badge-soft-{{ config("crm.quotation_statuses.{$q->status}") }}">{{ ucfirst($q->status) }}</span></span></a>
                @endforeach
            </div></div>
        @endif
    </div>
    <div class="col-lg-8">
        @include('partials.timeline', ['subject' => $lead, 'assignees' => $users])
    </div>
</div>
@endsection
