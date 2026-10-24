@extends('layouts.app')
@section('title', 'Webhook Logs')
@section('content')
<x-page-header title="Webhook logs" :back="route('settings.edit')" subtitle="Every inbound lead, WhatsApp and Meta event received by the CRM" />
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-3"><select name="source" class="form-select form-select-sm"><option value="">All sources</option>@foreach(['website', 'email', 'indiamart', 'google_ads', 'meta', 'whatsapp', 'tally'] as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ str($s)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>@foreach(['received', 'processed', 'ignored', 'failed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Received</th><th>Source</th><th>Status</th><th>Payload</th></tr></thead><tbody>
    @forelse($logs as $log)
        <tr><td class="small text-nowrap">{{ $log->created_at->format('d M y H:i:s') }}</td>
            <td class="small">{{ str($log->source)->replace('_', ' ')->title() }}</td>
            <td><span class="badge badge-soft-{{ ['processed' => 'success', 'failed' => 'danger', 'ignored' => 'secondary'][$log->status] ?? 'info' }}">{{ ucfirst($log->status) }}</span>
                @if($log->error)<div class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error, 120) }}</div>@endif</td>
            <td><details><summary class="small text-muted">{{ \Illuminate\Support\Str::limit(json_encode($log->payload), 90) }}</summary>
                <pre class="small bg-light p-2 rounded mt-1 mb-0" style="max-height:300px;white-space:pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></details></td></tr>
    @empty
        <tr><td colspan="4"><x-empty message="No webhook events received yet." icon="journal-code" /></td></tr>
    @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
