@extends('layouts.app')
@section('title', 'After-sales Service')
@section('content')
<x-page-header title="After-sales service">
    <a href="{{ route('tickets.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Ticket</a>
</x-page-header>
<div class="d-flex flex-wrap gap-2 mb-2">
    @foreach(['open' => 'Open', 'overdue' => 'SLA breached', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $k => $v)
        <a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-light' }}">{{ $v }}</a>
    @endforeach
</div>
<form class="card card-body mb-3 py-2"><input type="hidden" name="status" value="{{ $status }}"><div class="row g-2">
    <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Ticket no., subject, customer…"></div>
    <div class="col-6 col-md-2"><select name="type" class="form-select form-select-sm"><option value="">All types</option>@foreach(config('crm.ticket_types') as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><select name="priority" class="form-select form-select-sm"><option value="">Priority</option>@foreach(config('crm.priorities') as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="assigned_to" class="form-select form-select-sm"><option value="">Anyone</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected(request('assigned_to') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Ticket</th><th>Customer</th><th>Type</th><th>Priority</th><th>SLA due</th><th>Assigned</th><th>Status</th></tr></thead><tbody>
    @forelse($tickets as $t)
        <tr><td><a href="{{ route('tickets.show', $t) }}" class="fw-semibold">{{ $t->number }}</a><div class="small text-truncate" style="max-width:260px">{{ $t->subject }}</div></td>
            <td>{{ $t->customer->name }}<div class="small text-muted">{{ $t->product?->name }}</div></td>
            <td class="small">{{ config("crm.ticket_types.{$t->type}") }} @if($t->is_under_warranty)<span class="badge badge-soft-success">Warranty</span>@endif</td>
            <td><span class="badge badge-soft-{{ ['low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$t->priority] }}">{{ ucfirst($t->priority) }}</span></td>
            <td class="small {{ $t->isOverdue() ? 'overdue' : '' }}">{{ $t->due_at?->format('d M, h:i A') }}<div class="text-muted">{{ $t->due_at?->diffForHumans() }}</div></td>
            <td class="small">{{ $t->assignee?->name ?? '—' }}</td><td>{!! status_badge('ticket_statuses', $t->status) !!}</td></tr>
    @empty <tr><td colspan="7"><x-empty message="No tickets here." icon="wrench-adjustable" /></td></tr> @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $tickets->links() }}</div>
@endsection
