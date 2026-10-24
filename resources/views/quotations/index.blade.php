@extends('layouts.app')
@section('title', 'Quotations')
@section('content')
<x-page-header title="Quotations" :subtitle="$quotations->total().' quotations'">
    <a href="{{ route('quotations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Quotation</a>
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Number or customer…"></div>
    <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>
        @foreach(config('crm.quotation_statuses') as $k => $c)<option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Number</th><th>Customer</th><th>Date</th><th>Valid until</th><th>Status</th><th>Prepared by</th><th class="text-end">Total</th></tr></thead>
    <tbody>
    @forelse($quotations as $q)
        <tr><td><a href="{{ route('quotations.show', $q) }}" class="fw-semibold">{{ $q->number }}</a></td>
            <td>{{ $q->customer->name }}<div class="small text-muted">{{ $q->customer->phone }}</div></td>
            <td class="small">{{ $q->quote_date->format('d M Y') }}</td>
            <td class="small {{ $q->valid_until?->isPast() && in_array($q->status, ['draft', 'sent']) ? 'overdue' : '' }}">{{ $q->valid_until?->format('d M Y') ?? '—' }}</td>
            <td><span class="badge badge-soft-{{ config("crm.quotation_statuses.{$q->status}") }}">{{ ucfirst($q->status) }}</span></td>
            <td class="small">{{ $q->user?->name }}</td><td class="text-end fw-semibold">{{ money($q->total) }}</td></tr>
    @empty
        <tr><td colspan="7"><x-empty message="No quotations yet." icon="file-earmark-text" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $quotations->links() }}</div>
@endsection
