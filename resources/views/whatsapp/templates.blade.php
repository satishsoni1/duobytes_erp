@extends('layouts.app')
@section('title', 'WhatsApp Templates')
@section('content')
<x-page-header title="Templates & broadcast" :back="route('whatsapp.index')" subtitle="Message templates approved in your Meta WhatsApp Business account">
    <form method="POST" action="{{ route('whatsapp.templates.sync') }}">@csrf
        <button class="btn btn-light btn-sm" @disabled(! $configured)><i class="bi bi-arrow-repeat"></i> Sync from Meta</button>
    </form>
</x-page-header>
@unless($configured)
    <div class="alert alert-warning small">Add your WhatsApp Business Account ID and access token in Settings to sync templates.</div>
@endunless
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Template</th><th>Category</th><th>Vars</th><th>Status</th></tr></thead><tbody>
            @forelse($templates as $t)
                <tr><td><div class="fw-semibold">{{ $t->name }} <span class="text-muted small">{{ $t->language }}</span></div>
                        <div class="small text-muted" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($t->body, 220) }}</div></td>
                    <td class="small">{{ ucfirst(strtolower((string) $t->category)) }}</td>
                    <td>{{ $t->variable_count }}</td>
                    <td><span class="badge badge-soft-{{ ['APPROVED' => 'success', 'PENDING' => 'warning', 'REJECTED' => 'danger'][$t->status] ?? 'secondary' }}">{{ ucfirst(strtolower($t->status)) }}</span></td></tr>
            @empty
                <tr><td colspan="4"><x-empty message="No templates yet. Create them in Meta Business Manager, then sync." icon="file-earmark-text" /></td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>
    <div class="col-lg-5">
        @if(auth()->user()->hasRole('admin', 'manager'))
            <div class="card"><div class="card-header"><i class="bi bi-megaphone"></i> Broadcast</div><div class="card-body">
                <p class="small text-muted">Send an approved template to a segment – new collection launches, festive sales, clearance offers. Customers are only included if they have opted in to WhatsApp. Use <code>{name}</code> in a variable to insert each contact's name.</p>
                <form method="POST" action="{{ route('whatsapp.broadcast') }}" class="row g-2" data-confirm="Send this broadcast now? Messages cannot be recalled.">@csrf
                    <x-field name="template_id" type="select" label="Template" :options="$templates->where('status', 'APPROVED')->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.$t->language.')'])" col="col-12" required data-js="bcTemplate" />
                    <div class="col-12 row g-2 m-0 p-0" id="bcParams"></div>
                    <x-field name="audience" type="select" label="Audience" :options="['customers' => 'Customers (opted-in)', 'leads' => 'Leads']" value="customers" col="col-12" :empty="false" data-js="bcAudience" />
                    <div class="col-12 row g-2 m-0 p-0" data-audience="customers">
                        <x-field name="customer_type" type="select" label="Customer type" :options="config('crm.customer_types')" empty="Any" />
                    </div>
                    <div class="col-12 row g-2 m-0 p-0 d-none" data-audience="leads">
                        <x-field name="lead_status" type="select" label="Lead status" :options="collect(config('crm.lead_statuses'))->map->label" empty="Any" />
                        <x-field name="lead_source" type="select" label="Lead source" :options="config('crm.lead_sources')" empty="Any" />
                    </div>
                    <x-field name="city" type="select" label="City" :options="$cities->combine($cities)" empty="Any" col="col-12" />
                    <div class="col-12 text-end"><button class="btn btn-success btn-sm" @disabled($templates->where('status', 'APPROVED')->isEmpty())><i class="bi bi-send"></i> Queue broadcast</button></div>
                </form>
            </div></div>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script>
    const templateVars = @json($templates->pluck('variable_count', 'id'));
    document.querySelector('[data-js=bcTemplate]')?.addEventListener('change', e => {
        const box = document.getElementById('bcParams');
        box.innerHTML = '';
        for (let i = 1; i <= (templateVars[e.target.value] || 0); i++) {
            const col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = `<input name="params[]" class="form-control form-control-sm" placeholder="Variable {{ '{{' }}${i}}}" value="${i === 1 ? '{name}' : ''}">`;
            box.appendChild(col);
        }
    });
    document.querySelector('[data-js=bcAudience]')?.addEventListener('change', e => {
        document.querySelectorAll('[data-audience]').forEach(el => el.classList.toggle('d-none', el.dataset.audience !== e.target.value));
    });
</script>
@endpush
