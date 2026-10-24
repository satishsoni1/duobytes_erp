@extends('layouts.app')
@section('title', 'Pipeline')
@section('content')
<x-page-header title="Sales pipeline" subtitle="Drag cards between stages. Won/Lost show the last 30 days.">
    <a href="{{ route('leads.index', request()->query()) }}" class="btn btn-light btn-sm"><i class="bi bi-list-ul"></i> List</a>
    <a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Lead</a>
</x-page-header>
@include('leads._filters')
<div class="kanban">
    @foreach(config('crm.lead_statuses') as $status => $def)
        <div class="kanban-col" data-status="{{ $status }}">
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="fw-semibold small"><span class="badge badge-soft-{{ $def['color'] }}">{{ $def['label'] }}</span></span>
                <span class="small text-muted">{{ $columns[$status]->count() }} · {{ money($columns[$status]->sum('budget')) }}</span>
            </div>
            <div class="cards">
                @foreach($columns[$status] as $lead)
                    <div class="kanban-card" draggable="true" data-id="{{ $lead->id }}" data-url="{{ route('leads.status', $lead) }}">
                        <div class="d-flex justify-content-between"><a href="{{ route('leads.show', $lead) }}" class="fw-semibold small">{{ $lead->name }}</a>
                            @if($lead->priority === 'hot')<i class="bi bi-fire text-danger" title="Hot"></i>@endif</div>
                        <div class="small text-muted text-truncate">{{ $lead->requirement ?: $lead->source_label }}</div>
                        <div class="d-flex justify-content-between small mt-1">
                            <span>{{ $lead->budget ? money($lead->budget) : '' }}</span>
                            <span class="text-muted">{{ $lead->assignee?->name ? strtok($lead->assignee->name, ' ') : '—' }}</span>
                        </div>
                        @if($lead->next_follow_up_at && ! $lead->isClosed())
                            <div class="small {{ $lead->next_follow_up_at->isPast() ? 'overdue' : 'text-muted' }}"><i class="bi bi-clock"></i> {{ $lead->next_follow_up_at->format('d M h:i A') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<div class="modal fade" id="lostModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Why was this lead lost?</h5></div>
    <div class="modal-body"><select id="lostReason" class="form-select">@foreach(config('crm.lost_reasons') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal" id="lostCancel">Cancel</button><button class="btn btn-danger" id="lostSave">Mark lost</button></div>
</div></div></div>
@endsection
@push('scripts')
<script>
    let dragged = null, origin = null;
    const lostModal = new bootstrap.Modal(document.getElementById('lostModal'));
    const save = (card, status, lostReason = null) => fetch(card.dataset.url, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CRM.csrf },
        body: JSON.stringify({ status, lost_reason: lostReason }),
    }).then(r => { if (!r.ok) throw new Error(); }).catch(() => { origin.appendChild(card); alert('Could not update the lead.'); });

    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', () => { dragged = card; origin = card.parentElement; card.style.opacity = .5; });
        card.addEventListener('dragend', () => card.style.opacity = 1);
    });
    document.querySelectorAll('.kanban-col').forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drag-over'); });
        col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
        col.addEventListener('drop', e => {
            e.preventDefault(); col.classList.remove('drag-over');
            if (!dragged || dragged.parentElement === col.querySelector('.cards')) return;
            col.querySelector('.cards').prepend(dragged);
            const card = dragged, status = col.dataset.status;
            if (status === 'lost') {
                lostModal.show();
                document.getElementById('lostSave').onclick = () => { lostModal.hide(); save(card, 'lost', document.getElementById('lostReason').value); };
                document.getElementById('lostCancel').onclick = () => origin.appendChild(card);
            } else { save(card, status); }
        });
    });
</script>
@endpush
