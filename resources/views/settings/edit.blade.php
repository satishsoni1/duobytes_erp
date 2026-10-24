@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<x-page-header title="Settings & integrations">
    <a href="{{ route('settings.webhook-logs') }}" class="btn btn-light btn-sm"><i class="bi bi-journal-code"></i> Webhook logs</a>
</x-page-header>
@php($token = setting('webhook_token'))
<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card position-sticky" style="top:70px"><div class="list-group list-group-flush" id="settingsNav">
                @foreach(['company' => 'Company & branding', 'sales' => 'Sales documents', 'leads' => 'Leads & inventory', 'whatsapp' => 'WhatsApp Cloud API', 'meta' => 'Facebook / Instagram leads', 'webhooks' => 'Lead webhooks', 'notifications' => 'Customer notifications'] as $id => $label)
                    <a href="#{{ $id }}" class="list-group-item list-group-item-action">{{ $label }}</a>
                @endforeach
            </div></div>
        </div>
        <div class="col-lg-9">
            <div class="card mb-3" id="company"><div class="card-header">Company & branding</div><div class="card-body row g-3">
                <x-field name="company_name" label="Business name" :value="setting('company_name', config('app.name'))" required />
                <x-field name="company_tax_number" label="GSTIN / Tax number" :value="setting('company_tax_number')" />
                <x-field name="company_phone" label="Phone" :value="setting('company_phone')" col="col-md-4" />
                <x-field name="company_email" type="email" label="Email" :value="setting('company_email')" col="col-md-4" />
                <x-field name="company_website" label="Website" :value="setting('company_website')" col="col-md-4" />
                <x-field name="company_address" type="textarea" label="Address" :value="setting('company_address')" rows="2" />
                <x-field name="bank_details" type="textarea" label="Bank details (printed on invoices)" :value="setting('bank_details')" rows="2" />
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1" for="logo">Logo</label>
                    <input type="file" name="logo" id="logo" accept="image/*" class="form-control">
                    <div class="form-text">PNG or SVG, max 2 MB. Shown in the sidebar and on quotations and invoices.</div>
                </div>
                <div class="col-md-6 d-flex align-items-end">@if(setting('company_logo'))<img src="{{ Storage::disk('public')->url(setting('company_logo')) }}" alt="Current logo" style="max-height:56px">@endif</div>
            </div></div>

            <div class="card mb-3" id="sales"><div class="card-header">Sales documents</div><div class="card-body row g-3">
                <x-field name="default_tax_rate" type="number" label="Default GST / tax rate (%)" :value="setting('default_tax_rate', 18)" col="col-md-4" step="0.01" min="0" max="100" />
                <x-field name="quotation_validity_days" type="number" label="Quotation validity (days)" :value="setting('quotation_validity_days', 15)" col="col-md-4" min="1" max="365" />
                <x-field name="default_country_code" label="Default country code" :value="setting('default_country_code', config('crm.default_country_code'))" col="col-md-4" help="Used to normalise phone numbers, e.g. 91." />
                <x-field name="quotation_terms" type="textarea" label="Quotation terms & conditions" :value="setting('quotation_terms')" rows="4" />
                <x-field name="invoice_terms" type="textarea" label="Invoice terms & conditions" :value="setting('invoice_terms')" rows="4" />
            </div></div>

            <div class="card mb-3" id="leads"><div class="card-header">Leads & inventory</div><div class="card-body row g-3">
                <x-field name="lead_assignment" type="select" label="New lead assignment" :options="['round_robin' => 'Round robin (users marked “Receives leads”)', 'manual' => 'Manual']" :value="setting('lead_assignment', 'round_robin')" :empty="false" />
                <x-field name="lead_first_response_minutes" type="number" label="First response target (minutes)" :value="setting('lead_first_response_minutes', 30)" min="5" max="10080" help="Each new lead gets a “first contact” follow-up for its owner, due this many minutes after it arrives." />
                <x-field name="whatsapp_auto_create_lead" type="checkbox" label="Create a lead for WhatsApp messages from unknown numbers" :value="setting('whatsapp_auto_create_lead', '1') === '1'" />
                <x-field name="allow_negative_stock" type="checkbox" label="Allow confirming orders when stock is insufficient" :value="setting('allow_negative_stock', '0') === '1'" />
            </div></div>

            <div class="card mb-3" id="whatsapp"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-whatsapp text-success"></i> WhatsApp Cloud API</span>
                <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener" class="small fw-normal">Setup guide</a></div><div class="card-body row g-3">
                <x-field name="whatsapp_phone_number_id" label="Phone number ID" :value="setting('whatsapp_phone_number_id')" />
                <x-field name="whatsapp_business_account_id" label="WhatsApp Business Account ID" :value="setting('whatsapp_business_account_id')" />
                <x-field name="whatsapp_access_token" type="password" label="Permanent access token" :placeholder="$secretsSet['whatsapp_access_token'] ? '•••••••• saved – type to replace' : 'System user token'" autocomplete="new-password" />
                <x-field name="whatsapp_template_language" label="Default template language" :value="setting('whatsapp_template_language', 'en')" col="col-md-3" />
                <x-field name="whatsapp_verify_token" label="Webhook verify token" :value="setting('whatsapp_verify_token')" col="col-md-3" />
                @include('settings._url', ['label' => 'Webhook callback URL (subscribe to “messages”)', 'url' => route('webhooks.whatsapp')])
            </div></div>

            <div class="card mb-3" id="meta"><div class="card-header"><i class="bi bi-facebook text-primary"></i> Facebook & Instagram lead ads</div><div class="card-body row g-3">
                <x-field name="meta_page_access_token" type="password" label="Page access token" :placeholder="$secretsSet['meta_page_access_token'] ? '•••••••• saved – type to replace' : 'Long-lived page token with leads_retrieval'" autocomplete="new-password" />
                <x-field name="meta_app_secret" type="password" label="App secret" :placeholder="$secretsSet['meta_app_secret'] ? '•••••••• saved – type to replace' : 'Used to verify webhook signatures'" autocomplete="new-password" help="Shared with the WhatsApp webhook when both use the same Meta app." />
                <x-field name="meta_verify_token" label="Webhook verify token" :value="setting('meta_verify_token')" />
                @include('settings._url', ['label' => 'Webhook callback URL (Page → “leadgen” field)', 'url' => route('webhooks.meta')])
            </div></div>

            <div class="card mb-3" id="webhooks"><div class="card-header">Lead webhooks</div><div class="card-body row g-3">
                <div class="col-12 small text-muted">Send leads from your website forms, landing pages, Zapier / Make, IndiaMART or an email parser. Every endpoint is protected by the token below – send it in the URL, as an <code>X-Api-Key</code> header or as a <code>token</code> field.</div>
                @if($token)
                    @include('settings._url', ['label' => 'Website / Zapier (JSON or form: name, phone, email, city, message, product, budget, source, campaign)', 'url' => route('webhooks.leads', $token)])
                    @include('settings._url', ['label' => 'Inbound email (Mailgun, SendGrid, Postmark)', 'url' => route('webhooks.email', $token)])
                    @include('settings._url', ['label' => 'IndiaMART push API', 'url' => route('webhooks.indiamart', $token)])
                    @include('settings._url', ['label' => 'Google Ads lead form webhook (use the token as the “key”)', 'url' => route('webhooks.google-ads')])
                @else
                    <div class="col-12"><div class="alert alert-warning small mb-0">No webhook token yet. Generate one to enable lead webhooks.</div></div>
                @endif
                <div class="col-12"><button type="submit" form="regenerateToken" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-repeat"></i> {{ $token ? 'Regenerate token' : 'Generate token' }}</button></div>
            </div></div>

            <div class="card mb-3" id="notifications"><div class="card-header">Customer WhatsApp notifications</div><div class="card-body">
                <p class="small text-muted">Pick an approved template for each event to message customers any time. Without a template the text below is sent, which WhatsApp only delivers within 24 hours of the customer's last message. Template variables are filled in the order shown.</p>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width:60px">On</th><th>Event</th><th style="width:220px">Template</th><th>Fallback text</th></tr></thead><tbody>
                    @foreach(config('crm.notification_events') as $event => $def)
                        <tr>
                            <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="notify[{{ $event }}][enabled]" value="1" aria-label="Enable {{ $def['label'] }}" @checked(setting("notify_{$event}_enabled") === '1')></div></td>
                            <td><div class="fw-semibold small">{{ $def['label'] }}</div><div class="text-muted" style="font-size:.72rem">@foreach($def['params'] as $i => $p)<code>&#123;&#123;{{ $i + 1 }}&#125;&#125;</code>={{ $p }} @endforeach</div></td>
                            <td><select name="notify[{{ $event }}][template]" class="form-select form-select-sm" aria-label="Template for {{ $def['label'] }}">
                                <option value="">— Plain text —</option>
                                @foreach($templates->where('status', 'APPROVED') as $t)<option value="{{ $t->name }}" @selected(setting("notify_{$event}_template") === $t->name)>{{ $t->name }}</option>@endforeach
                            </select></td>
                            <td><textarea name="notify[{{ $event }}][text]" rows="2" class="form-control form-control-sm" placeholder="{{ $def['text'] }}" aria-label="Fallback text for {{ $def['label'] }}">{{ setting("notify_{$event}_text") }}</textarea></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            </div></div>

            <div class="text-end mb-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save settings</button></div>
        </div>
    </div>
</form>
<form method="POST" action="{{ route('settings.token') }}" id="regenerateToken" data-confirm="Generate a new webhook token? Existing integrations using the old token will stop working.">@csrf</form>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', () => {
        navigator.clipboard.writeText(btn.dataset.copy).then(() => { btn.innerHTML = '<i class="bi bi-check2"></i>'; setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i>', 1500); });
    }));
</script>
@endpush
