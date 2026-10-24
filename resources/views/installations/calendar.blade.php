@extends('layouts.app')
@section('title', 'Installation Calendar')
@section('content')
<x-page-header title="Installation calendar" :subtitle="$start->format('d M').' – '.$start->addDays(6)->format('d M Y')">
    <a href="{{ route('installations.calendar', ['week' => $start->subWeek()->toDateString()]) }}" class="btn btn-light btn-sm"><i class="bi bi-chevron-left"></i></a>
    <a href="{{ route('installations.calendar') }}" class="btn btn-light btn-sm">This week</a>
    <a href="{{ route('installations.calendar', ['week' => $start->addWeek()->toDateString()]) }}" class="btn btn-light btn-sm"><i class="bi bi-chevron-right"></i></a>
    <a href="{{ route('installations.index', ['status' => 'pending']) }}" class="btn btn-primary btn-sm">Unscheduled</a>
</x-page-header>
<div class="row g-2">
    @for($d = 0; $d < 7; $d++)
        @php($day = $start->addDays($d))
        <div class="col-md">
            <div class="card h-100 {{ $day->isToday() ? 'border-primary' : '' }}">
                <div class="card-header small py-2 {{ $day->isToday() ? 'text-primary' : '' }}">{{ $day->format('D') }} <span class="fw-normal">{{ $day->format('d M') }}</span></div>
                <div class="card-body p-2">
                    @forelse($items[$day->toDateString()] ?? [] as $i)
                        <a href="{{ route('installations.show', $i) }}" class="d-block border rounded p-2 mb-2 text-decoration-none small text-body bg-light">
                            <div class="fw-semibold">{{ $i->customer->name }}</div>
                            <div class="text-muted">{{ $i->slot_label ?? 'Any time' }}</div>
                            <div>{{ $i->technician?->name ?? 'Unassigned' }}</div>
                            {!! status_badge('installation_statuses', $i->status) !!}
                        </a>
                    @empty
                        <div class="text-muted small text-center py-3">—</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endfor
</div>
@endsection
