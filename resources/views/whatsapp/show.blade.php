@extends('layouts.app')
@section('title', $name.' · WhatsApp')
@section('content')
<x-page-header :title="$name" :back="route('whatsapp.index')" :subtitle="'+'.e($phone).($windowOpen ? ' · <span class=\'text-success\'>24h window open</span>' : ' · <span class=\'text-muted\'>Outside 24h window – use a template</span>')">
    @if($contact instanceof \App\Models\Lead && auth()->user()->canAccess('leads'))
        <a href="{{ route('leads.show', $contact) }}" class="btn btn-light btn-sm"><i class="bi bi-funnel"></i> Lead {{ $contact->code }}</a>
    @elseif($contact instanceof \App\Models\Customer && auth()->user()->canAccess('customers'))
        <a href="{{ route('customers.show', $contact) }}" class="btn btn-light btn-sm"><i class="bi bi-person"></i> Customer {{ $contact->code }}</a>
    @elseif(! $contact && auth()->user()->canAccess('leads'))
        <a href="{{ route('leads.create', ['phone' => $phone, 'whatsapp' => $phone, 'source' => 'whatsapp', 'name' => $name !== '+'.$phone ? $name : null]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-plus"></i> Create lead</a>
    @endif
</x-page-header>
@unless($configured)
    <div class="alert alert-warning small">WhatsApp Cloud API is not configured – messages will be queued but not delivered.</div>
@endunless
<div class="row g-3">
    <div class="col-lg-8">
        <div class="chat mb-3" id="chat">
            @forelse($messages as $m)
                @if($loop->first || ! $m->created_at->isSameDay($messages->values()[$loop->index - 1]->created_at))
                    <div class="text-center my-2"><span class="badge bg-white text-muted shadow-sm">{{ $m->created_at->isToday() ? 'Today' : $m->created_at->format('d M Y') }}</span></div>
                @endif
                <div class="bubble {{ $m->direction }}">
                    @if($m->template_name)<div class="small text-muted"><i class="bi bi-file-earmark-text"></i> {{ $m->template_name }}</div>@endif
                    @if(in_array($m->type, ['image', 'document', 'audio', 'video', 'sticker']))
                        <a href="{{ route('whatsapp.media', $m) }}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-1 small"><i class="bi bi-{{ $m->type === 'image' ? 'image' : 'paperclip' }}"></i> {{ ucfirst($m->type) }}</a>
                    @endif
                    @if($m->body)<div>{{ $m->body }}</div>@endif
                    <div class="meta">
                        @if($m->direction === 'out' && $m->user){{ $m->user->name }} · @endif
                        {{ $m->created_at->format('h:i A') }}
                        @if($m->direction === 'out')
                            @switch($m->status)
                                @case('read')<i class="bi bi-check2-all text-primary" title="Read"></i>@break
                                @case('delivered')<i class="bi bi-check2-all" title="Delivered"></i>@break
                                @case('sent')<i class="bi bi-check2" title="Sent"></i>@break
                                @case('failed')<i class="bi bi-exclamation-circle text-danger" title="{{ $m->error }}"></i>@break
                                @default<i class="bi bi-clock" title="Queued"></i>
                            @endswitch
                        @endif
                    </div>
                    @if($m->status === 'failed' && $m->error)<div class="small text-danger">{{ \Illuminate\Support\Str::limit($m->error, 160) }}</div>@endif
                </div>
            @empty
                <x-empty message="No messages yet. Start the conversation with an approved template." icon="chat-dots" />
            @endforelse
        </div>

        <div class="card">
            <div class="card-header p-0 border-0">
                <ul class="nav nav-tabs px-2 pt-2" role="tablist">
                    <li class="nav-item"><button class="nav-link {{ $windowOpen ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-text" type="button">Message</button></li>
                    <li class="nav-item"><button class="nav-link {{ $windowOpen ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#tab-template" type="button">Template</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-file" type="button">File</button></li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade {{ $windowOpen ? 'show active' : '' }}" id="tab-text">
                    @unless($windowOpen)<div class="small text-muted mb-2">The customer hasn't messaged in the last 24 hours, so Meta will only deliver template messages.</div>@endunless
                    <form method="POST" action="{{ route('whatsapp.send', $phone) }}">@csrf
                        <input type="hidden" name="mode" value="text">
                        <div class="input-group">
                            <textarea name="body" rows="2" class="form-control" placeholder="Type a message…" required maxlength="4096"
                                onkeydown="if (event.key === 'Enter' && ! event.shiftKey) { event.preventDefault(); this.form.requestSubmit(); }"></textarea>
                            <button class="btn btn-success" @disabled(! $windowOpen)><i class="bi bi-send"></i></button>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade {{ $windowOpen ? '' : 'show active' }}" id="tab-template">
                    @if($templates->isEmpty())
                        <div class="small text-muted">No approved templates. <a href="{{ route('whatsapp.templates') }}">Sync templates from Meta</a>.</div>
                    @else
                        <form method="POST" action="{{ route('whatsapp.send', $phone) }}" class="row g-2">@csrf
                            <input type="hidden" name="mode" value="template">
                            <div class="col-12"><select name="template_id" id="tplSelect" class="form-select form-select-sm" required>
                                <option value="">— Choose template —</option>
                                @foreach($templates as $t)<option value="{{ $t->id }}" data-vars="{{ $t->variable_count }}" data-body="{{ $t->body }}">{{ $t->name }} ({{ $t->language }})</option>@endforeach
                            </select></div>
                            <div class="col-12 small text-muted" id="tplPreview" style="white-space:pre-line"></div>
                            <div class="col-12 row g-2 m-0 p-0" id="tplParams"></div>
                            <div class="col-12 text-end"><button class="btn btn-sm btn-success"><i class="bi bi-send"></i> Send template</button></div>
                        </form>
                    @endif
                </div>
                <div class="tab-pane fade" id="tab-file">
                    <form method="POST" action="{{ route('whatsapp.send', $phone) }}" enctype="multipart/form-data" class="row g-2">@csrf
                        <input type="hidden" name="mode" value="file">
                        <div class="col-md-6"><input type="file" name="file" class="form-control form-control-sm" required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"></div>
                        <div class="col-md-4"><input name="caption" class="form-control form-control-sm" placeholder="Caption (optional)" maxlength="1000"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-success w-100" @disabled(! $windowOpen)><i class="bi bi-upload"></i> Send</button></div>
                        <div class="col-12 form-text">Images, PDF catalogues, Word or Excel up to 16 MB.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Contact</div><div class="card-body">
            @if($contact)
                <dl class="dl-grid small">
                    <dt>Name</dt><dd>{{ $contact->name }}</dd>
                    <dt>Type</dt><dd>{{ $contact instanceof \App\Models\Lead ? 'Lead' : 'Customer' }} · {{ $contact->code }}</dd>
                    @if($contact instanceof \App\Models\Lead)<dt>Status</dt><dd>{!! status_badge('lead_statuses', $contact->status) !!}</dd>@endif
                    <dt>Phone</dt><dd>{{ $contact->phone ?? '—' }}</dd>
                    <dt>Email</dt><dd>{{ $contact->email ?? '—' }}</dd>
                    <dt>City</dt><dd>{{ $contact->city ?? '—' }}</dd>
                    @if($contact instanceof \App\Models\Lead)<dt>Requirement</dt><dd style="white-space:pre-line">{{ $contact->requirement ?? '—' }}</dd>@endif
                </dl>
            @else
                <div class="small text-muted">This number isn't linked to any lead or customer yet.</div>
            @endif
        </div></div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    const chat = document.getElementById('chat');
    chat.scrollTop = chat.scrollHeight;
    const tpl = document.getElementById('tplSelect');
    tpl?.addEventListener('change', () => {
        const opt = tpl.selectedOptions[0];
        const count = Number(opt.dataset.vars || 0);
        document.getElementById('tplPreview').textContent = opt.dataset.body || '';
        const box = document.getElementById('tplParams');
        box.innerHTML = '';
        for (let i = 1; i <= count; i++) {
            const col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = `<input name="params[]" class="form-control form-control-sm" placeholder="Variable {{ '{{' }}${i}}}" required>`;
            box.appendChild(col);
        }
    });
</script>
@endpush
