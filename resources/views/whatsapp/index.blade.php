@extends('layouts.app')
@section('title', 'WhatsApp')
@section('content')
<x-page-header title="WhatsApp inbox" subtitle="Conversations from your WhatsApp Business number">
    <a href="{{ route('whatsapp.templates') }}" class="btn btn-light btn-sm"><i class="bi bi-megaphone"></i> Templates & Broadcast</a>
</x-page-header>
@unless($configured)
    <div class="alert alert-warning small">WhatsApp Cloud API is not configured. Messages will stay queued until you add the phone number ID and access token in
        @if(auth()->user()->canAccess('settings'))<a href="{{ route('settings.edit') }}#whatsapp">Settings</a>@else Settings (ask an administrator)@endif.</div>
@endunless
<div class="d-flex flex-wrap gap-2 mb-2">
    @foreach(['' => 'All', 'unread' => 'Unread'] as $k => $v)
        <a href="{{ request()->fullUrlWithQuery(['filter' => $k ?: null, 'page' => null]) }}" class="btn btn-sm {{ (string) request('filter') === $k ? 'btn-primary' : 'btn-light' }}">{{ $v }}</a>
    @endforeach
</div>
<form class="card card-body mb-3 py-2"><input type="hidden" name="filter" value="{{ request('filter') }}"><div class="row g-2">
    <div class="col-md-6"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search by phone or contact name…"></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Search</button></div>
    <div class="col-md-4"><div class="input-group input-group-sm">
        <input id="newChat" class="form-control" placeholder="Start chat: 98765 43210" aria-label="Phone number for new chat">
        <button type="button" class="btn btn-success" onclick="const p = document.getElementById('newChat').value.replace(/\D/g, ''); if (p) location = @js(url('whatsapp/chat')) + '/' + p;"><i class="bi bi-whatsapp"></i> Open</button>
    </div></div>
</div></form>
<div class="card"><div class="list-group list-group-flush">
    @forelse($threads as $thread)
        @php($m = $last[$thread->last_id] ?? null)
        <a href="{{ route('whatsapp.show', $thread->phone) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center {{ $thread->unread ? 'bg-success-subtle' : '' }}">
            <span class="avatar bg-success" style="width:38px;height:38px">{{ strtoupper(substr($m?->contact?->name ?? $m?->contact_name ?? '#', 0, 1)) }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">{{ $m?->contact?->name ?? $m?->contact_name ?? '+'.$thread->phone }}
                        @if($m?->contact instanceof \App\Models\Lead)<span class="badge badge-soft-info">Lead</span>@elseif($m?->contact instanceof \App\Models\Customer)<span class="badge badge-soft-success">Customer</span>@endif</span>
                    <span class="small text-muted text-nowrap">{{ $m?->created_at->isToday() ? $m->created_at->format('h:i A') : $m?->created_at->format('d M') }}</span>
                </div>
                <div class="d-flex justify-content-between gap-2">
                    <span class="small text-muted text-truncate">
                        @if($m?->direction === 'out')<i class="bi bi-check2{{ in_array($m->status, ['delivered', 'read']) ? '-all' : '' }} {{ $m->status === 'read' ? 'text-primary' : '' }}"></i>@endif
                        @if($m?->type !== 'text')<i class="bi bi-paperclip"></i>@endif
                        {{ \Illuminate\Support\Str::limit($m?->body ?? ($m?->template_name ? 'Template: '.$m->template_name : ucfirst((string) $m?->type)), 90) }}
                    </span>
                    @if($thread->unread)<span class="badge rounded-pill bg-success">{{ $thread->unread }}</span>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="list-group-item"><x-empty message="No conversations yet. Incoming WhatsApp messages will appear here." icon="whatsapp" /></div>
    @endforelse
</div></div>
<div class="mt-3">{{ $threads->links() }}</div>
@endsection
