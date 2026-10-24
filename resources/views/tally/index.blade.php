@extends('layouts.app')
@section('title', 'Tally Sync')
@section('content')
@php
    $mode = setting('tally_mode', 'manual');
    $token = setting('webhook_token');
@endphp
<x-page-header title="Tally sync" :subtitle="'Sales vouchers from Tally Prime / Tally.ERP 9 become CRM sales orders · '.$imported.' imported so far'.(setting('tally_last_synced_at') ? ' · last import '.\Carbon\Carbon::parse(setting('tally_last_synced_at'))->diffForHumans() : '')">
    <a href="{{ route('help.index') }}#tally" class="btn btn-light btn-sm"><i class="bi bi-question-circle"></i> How it works</a>
    @if($mode === 'pull')
        <form method="POST" action="{{ route('tally.sync') }}">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Sync now</button></form>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('tally.update') }}" class="card mb-3">@csrf @method('PUT')
            <div class="card-header">Connection</div>
            <div class="card-body row g-3">
                <x-field name="tally_mode" type="select" label="How should orders arrive?" col="col-12" :empty="false" :value="$mode" data-js="tallyMode" :options="[
                    'manual' => 'Manual – I upload a Day Book XML exported from Tally',
                    'pull' => 'Automatic pull – the CRM can reach the Tally PC (same office network / VPN)',
                    'push' => 'Automatic push – a scheduled script on the Tally PC sends data to the CRM',
                ]" />
                <div class="col-12 row g-3 m-0 p-0" data-mode="pull">
                    <x-field name="tally_url" label="Tally server address" :value="setting('tally_url')" placeholder="http://192.168.1.20:9000" help="On the Tally PC: F1 Help → Settings → Connectivity → Client/Server: Both, Port 9000." />
                    <x-field name="tally_company" label="Company name in Tally" :value="setting('tally_company')" help="Leave blank to use the company currently open in Tally." />
                </div>
                <x-field name="tally_voucher_types" label="Voucher types to import" :value="setting('tally_voucher_types', 'Sales')" required help="Comma separated, exactly as named in Tally, e.g. Sales, GST Sales, Sales Order." />
                <x-field name="tally_order_status" type="select" label="Status for imported orders" :options="collect(config('crm.order_statuses'))->except('cancelled')->map->label" :value="setting('tally_order_status', 'confirmed')" :empty="false" />
                <x-field name="tally_sync_from" type="date" label="Import vouchers dated from" :value="setting('tally_sync_from')" col="col-md-4" help="First automatic sync starts here." />
                <x-field name="tally_lookback_days" type="number" label="Re-check edits (days)" :value="setting('tally_lookback_days', 7)" col="col-md-4" min="0" max="90" />
                <x-field name="tally_deduct_stock" type="checkbox" label="Deduct CRM stock for imported sales" :value="setting('tally_deduct_stock', '1') === '1'" col="col-md-4" />
                @if($mode !== 'manual')<div class="col-12 small text-muted">Next automatic sync reads vouchers from <strong>{{ $windowStart->format('d M Y') }}</strong> to today. Runs every 15 minutes.</div>@endif
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary btn-sm">Save</button></div>
        </form>

        <div class="card mb-3" data-mode="push">
            <div class="card-header">Push script for the Tally PC</div>
            <div class="card-body small">
                @if(! $token)
                    <div class="alert alert-warning mb-0">Generate a webhook token in <a href="{{ route('settings.edit') }}#webhooks">Settings → Lead webhooks</a> first.</div>
                @else
                    <ol class="ps-3">
                        <li>In Tally enable the XML server: F1 Help → Settings → Connectivity → Client/Server: <em>Both</em>, Port <em>9000</em>.</li>
                        <li>On the Tally PC save this as <code>C:\crm\tally-push.ps1</code>:</li>
                    </ol>
                    @php($script = '$crm = "'.url('api/webhooks/tally').'"'."\n"
                        .'$token = "'.$token.'"'."\n"
                        .'$request = (Invoke-WebRequest -UseBasicParsing "$crm/request/$token").Content'."\n"
                        .'$data = (Invoke-WebRequest -UseBasicParsing -Uri "http://localhost:9000" -Method Post -Body $request -ContentType "text/xml; charset=utf-8").Content'."\n"
                        .'Invoke-WebRequest -UseBasicParsing -Uri "$crm/$token" -Method Post -Body ([Text.Encoding]::UTF8.GetBytes($data)) -ContentType "text/xml; charset=utf-8"')
                    <div class="position-relative mb-2">
                        <pre class="bg-dark text-light p-3 rounded mb-0" style="white-space:pre-wrap;font-size:.75rem">{{ $script }}</pre>
                        <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2" data-copy="{{ $script }}" aria-label="Copy script"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <ol class="ps-3" start="3">
                        <li>Schedule it every 15 minutes (run once in an administrator Command Prompt):
                            <pre class="bg-light p-2 rounded mt-1 mb-0" style="white-space:pre-wrap;font-size:.75rem">schtasks /create /tn "CRM Tally Push" /sc minute /mo 15 /tr "powershell -ExecutionPolicy Bypass -File C:\crm\tally-push.ps1"</pre></li>
                        <li>Keep Tally open with the company loaded – the script only works while Tally is running.</li>
                    </ol>
                    <div class="text-muted">Regenerating the webhook token in Settings breaks this script until you update the token in it.</div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Import a Tally XML file</div>
            <div class="card-body">
                <p class="small text-muted mb-2">In Tally open <strong>Display More Reports → Day Book</strong>, press <kbd>F2</kbd> to pick the period, then <strong>Export</strong> (<kbd>Ctrl</kbd>+<kbd>E</kbd>) → Format: <em>XML (Data Interchange)</em>. Upload the file here. Importing the same file twice is safe.</p>
                <form method="POST" action="{{ route('tally.upload') }}" enctype="multipart/form-data" class="row g-2">@csrf
                    <div class="col-md-9"><input type="file" name="file" accept=".xml,.txt" class="form-control form-control-sm" required aria-label="Tally XML file"></div>
                    <div class="col-md-3"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i> Import</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span>Unmatched Tally items</span><span class="badge badge-soft-{{ $unmatched->isEmpty() ? 'success' : 'warning' }}">{{ $unmatched->count() }}</span></div>
            @if($unmatched->isEmpty())
                <div class="card-body small text-muted">All imported items are linked to CRM products. Items are matched by the product's <em>Tally item name</em>, then SKU, then name.</div>
            @else
                <div class="card-body small text-muted pb-0">Link each Tally stock item to a CRM product so stock and reports are correct. Future imports remember the link.</div>
                <div class="list-group list-group-flush">
                    @foreach($unmatched->take(30) as $name => $count)
                        <form method="POST" action="{{ route('tally.map-item') }}" class="list-group-item">@csrf
                            <input type="hidden" name="name" value="{{ $name }}">
                            <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold">{{ $name }}</span><span class="text-muted">{{ $count }} lines</span></div>
                            <div class="input-group input-group-sm">
                                <select name="product_id" class="form-select searchable" required aria-label="CRM product for {{ $name }}">
                                    <option value="">— Choose CRM product —</option>
                                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
                                </select>
                                <button class="btn btn-light">Link</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>Recent imports</span><a href="{{ route('settings.webhook-logs', ['source' => 'tally']) }}" class="small">All</a></div>
            <div class="list-group list-group-flush">
                @forelse($logs as $log)
                    @php($p = $log->payload ?? [])
                    <div class="list-group-item small">
                        <div class="d-flex justify-content-between">
                            <span><span class="badge badge-soft-{{ $log->status === 'processed' ? 'success' : ($log->status === 'failed' ? 'danger' : 'info') }}">{{ ucfirst($log->status) }}</span> {{ ucfirst($p['channel'] ?? '') }}</span>
                            <span class="text-muted">{{ $log->created_at->format('d M, h:i A') }}</span>
                        </div>
                        @isset($p['created'])<div>{{ $p['created'] }} new · {{ $p['updated'] }} updated · {{ $p['cancelled'] }} cancelled · {{ $p['unchanged'] }} unchanged · {{ $p['skipped'] }} other vouchers skipped</div>@endisset
                        @if($log->error)<div class="text-danger" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($log->error, 400) }}</div>@endif
                    </div>
                @empty
                    <div class="list-group-item"><x-empty message="Nothing imported yet." icon="arrow-left-right" /></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    const modeSelect = document.querySelector('[data-js=tallyMode]');
    const applyMode = () => document.querySelectorAll('[data-mode]').forEach(el => el.classList.toggle('d-none', el.dataset.mode !== modeSelect.value));
    modeSelect.addEventListener('change', applyMode);
    applyMode();
    document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', () => navigator.clipboard.writeText(btn.dataset.copy)));
</script>
@endpush
