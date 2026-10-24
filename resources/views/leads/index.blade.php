@extends('layouts.app')
@section('title', 'Leads')
@section('content')
<x-page-header title="Leads" :subtitle="$leads->total().' leads'">
    <a href="{{ route('leads.board', request()->query()) }}" class="btn btn-light btn-sm"><i class="bi bi-kanban"></i> Board</a>
    <a href="{{ route('leads.export', request()->query()) }}" class="btn btn-light btn-sm"><i class="bi bi-download"></i> Export</a>
    <a href="{{ route('leads.import') }}" class="btn btn-light btn-sm"><i class="bi bi-upload"></i> Import</a>
    <a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Lead</a>
</x-page-header>

@include('leads._filters')

<form method="POST" action="{{ route('leads.bulk') }}" id="bulkForm">
    @csrf
    @if(auth()->user()->seesAllRecords())
        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center" id="bulkBar" style="display:none!important">
            <span class="small text-muted"><span id="bulkCount">0</span> selected</span>
            <select name="action" class="form-select form-select-sm" style="width:auto" onchange="document.getElementById('bulkAssign').classList.toggle('d-none', this.value!=='assign');document.getElementById('bulkStatus').classList.toggle('d-none', this.value!=='status')">
                <option value="assign">Assign to</option><option value="status">Change status</option>
                @if(auth()->user()->hasRole('admin', 'manager'))<option value="delete">Delete</option>@endif
            </select>
            <select name="assigned_to" id="bulkAssign" class="form-select form-select-sm" style="width:auto">@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
            <select name="status" id="bulkStatus" class="form-select form-select-sm d-none" style="width:auto">@foreach(config('crm.lead_statuses') as $k => $d)<option value="{{ $k }}">{{ $d['label'] }}</option>@endforeach</select>
            <button class="btn btn-sm btn-primary" onclick="return confirm('Apply to selected leads?')">Apply</button>
        </div>
    @endif

    <div class="card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                @if(auth()->user()->seesAllRecords())<th style="width:30px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Select all"></th>@endif
                <th>Lead</th><th>Contact</th><th>Source</th><th>Requirement</th><th>Status</th><th>Next follow-up</th><th>Owner</th><th>Created</th>
            </tr></thead>
            <tbody>
            @forelse($leads as $lead)
                <tr>
                    @if(auth()->user()->seesAllRecords())<td><input type="checkbox" name="ids[]" value="{{ $lead->id }}" class="form-check-input row-check" aria-label="Select"></td>@endif
                    <td><a href="{{ route('leads.show', $lead) }}" class="fw-semibold">{{ $lead->name }}</a>
                        <div class="small text-muted">{{ $lead->code }} @if($lead->priority === 'hot')<span class="badge badge-soft-danger">Hot</span>@endif</div></td>
                    <td class="small">{{ $lead->phone }}<div class="text-muted">{{ $lead->email }}</div></td>
                    <td class="small">{{ $lead->source_label }}@if($lead->campaign)<div class="text-muted text-truncate" style="max-width:140px">{{ $lead->campaign }}</div>@endif</td>
                    <td class="small text-truncate" style="max-width:220px">{{ $lead->requirement }}@if($lead->budget)<div class="text-muted">{{ money($lead->budget) }}</div>@endif</td>
                    <td>{!! status_badge('lead_statuses', $lead->status) !!}</td>
                    <td class="small {{ $lead->next_follow_up_at?->isPast() && ! $lead->isClosed() ? 'overdue' : '' }}">{{ $lead->next_follow_up_at?->format('d M, h:i A') ?? '—' }}</td>
                    <td class="small">{{ $lead->assignee?->name ?? 'Unassigned' }}</td>
                    <td class="small text-muted text-nowrap">{{ $lead->created_at->format('d M y') }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty message="No leads match these filters." icon="funnel" /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</form>
<div class="mt-3">{{ $leads->links() }}</div>
@endsection
@push('scripts')
<script>
    const bar = document.getElementById('bulkBar');
    const update = () => { const n = document.querySelectorAll('.row-check:checked').length; if (bar) { bar.style.setProperty('display', n ? 'flex' : 'none', 'important'); document.getElementById('bulkCount').textContent = n; } };
    document.querySelectorAll('.row-check').forEach(c => c.addEventListener('change', update));
    document.getElementById('checkAll')?.addEventListener('change', e => { document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked); update(); });
</script>
@endpush
