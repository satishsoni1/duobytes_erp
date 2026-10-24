@extends('layouts.app')
@section('title', 'Follow-ups')
@section('content')
<x-page-header title="Follow-ups" subtitle="Calls, visits and reminders scheduled for you and your team" />
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach(['today' => 'Due today', 'overdue' => 'Overdue', 'pending' => 'All pending', 'done' => 'Completed'] as $k => $v)
        <a href="{{ request()->fullUrlWithQuery(['scope' => $k, 'page' => null]) }}" class="btn btn-sm {{ $scope === $k ? 'btn-primary' : 'btn-light' }}">{{ $v }}</a>
    @endforeach
    @if($users->isNotEmpty())
        <form class="ms-auto"><input type="hidden" name="scope" value="{{ $scope }}">
            <select name="user" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Mine</option><option value="all" @selected(request('user') === 'all')>Everyone</option>
                @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach
            </select></form>
    @endif
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Due</th><th>Task</th><th>Related to</th><th>Owner</th><th></th></tr></thead>
    <tbody>
    @forelse($followUps as $f)
        @php($s = $f->followable)
        <tr>
            <td class="{{ $f->isOverdue() ? 'overdue' : '' }} text-nowrap">{{ $f->due_at->format('d M, h:i A') }}<div class="small text-muted">{{ $f->due_at->diffForHumans() }}</div></td>
            <td><span class="badge badge-soft-info">{{ config("crm.follow_up_types.{$f->type}") }}</span> {{ $f->title }}
                @if($f->outcome)<div class="small text-muted">{{ $f->outcome }}</div>@endif</td>
            <td>
                @if($s instanceof \App\Models\Lead)<a href="{{ route('leads.show', $s) }}">{{ $s->name }}</a><div class="small text-muted">Lead · {{ $s->phone }}</div>
                @elseif($s instanceof \App\Models\Customer)<a href="{{ route('customers.show', $s) }}">{{ $s->name }}</a><div class="small text-muted">Customer · {{ $s->phone }}</div>
                @elseif($s instanceof \App\Models\ServiceTicket)<a href="{{ route('tickets.show', $s) }}">{{ $s->number }}</a><div class="small text-muted">Service ticket</div>
                @elseif($s)<span>{{ $s->number ?? '#'.$s->id }}</span>@endif
            </td>
            <td class="small">{{ $f->user->name }}</td>
            <td class="text-end">
                @unless($f->completed_at)
                    <form method="POST" action="{{ route('follow-ups.complete', $f) }}" class="d-inline-flex gap-1">@csrf @method('PATCH')
                        <input name="outcome" class="form-control form-control-sm" placeholder="Outcome" style="width:160px">
                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button></form>
                @else<span class="text-success small"><i class="bi bi-check-circle"></i> {{ $f->completed_at->format('d M') }}</span>@endunless
            </td>
        </tr>
    @empty
        <tr><td colspan="5"><x-empty message="Nothing here." icon="calendar-check" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $followUps->links() }}</div>
@endsection
