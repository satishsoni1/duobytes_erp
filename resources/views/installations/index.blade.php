@extends('layouts.app')
@section('title', 'Installations')
@section('content')
<x-page-header title="Installation requests">
    <a href="{{ route('installations.calendar') }}" class="btn btn-light btn-sm"><i class="bi bi-calendar-week"></i> Calendar</a>
    @unless(auth()->user()->hasRole('technician'))<a href="{{ route('installations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Request</a>@endunless
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Number or customer…"></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open</option>
        @foreach(config('crm.installation_statuses') as $k => $d)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $d['label'] }}</option>@endforeach</select></div>
    @unless(auth()->user()->hasRole('technician'))
    <div class="col-md-3"><select name="technician_id" class="form-select form-select-sm"><option value="">All technicians</option>@foreach($technicians as $t)<option value="{{ $t->id }}" @selected(request('technician_id') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    @endunless
    <div class="col-md-2"><input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm" aria-label="Date"></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Request</th><th>Customer</th><th>Order</th><th>Schedule</th><th>Technician</th><th>Priority</th><th>Status</th></tr></thead><tbody>
    @forelse($installations as $i)
        <tr><td><a href="{{ route('installations.show', $i) }}" class="fw-semibold">{{ $i->number }}</a></td>
            <td>{{ $i->customer->name }}<div class="small text-muted text-truncate" style="max-width:220px">{{ $i->address }}</div></td>
            <td class="small">{{ $i->order?->number ?? '—' }}</td>
            <td class="small">{{ $i->scheduled_date?->format('D, d M') ?? 'Not scheduled' }}<div class="text-muted">{{ $i->slot_label }}</div></td>
            <td class="small">{{ $i->technician?->name ?? '—' }}</td>
            <td><span class="badge badge-soft-{{ ['low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$i->priority] }}">{{ ucfirst($i->priority) }}</span></td>
            <td>{!! status_badge('installation_statuses', $i->status) !!}</td></tr>
    @empty <tr><td colspan="7"><x-empty message="No installation requests." icon="tools" /></td></tr> @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $installations->links() }}</div>
@endsection
