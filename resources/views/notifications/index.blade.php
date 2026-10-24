@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<x-page-header title="Notifications">
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-light">Mark all as read</button></form>
</x-page-header>
<div class="card"><div class="list-group list-group-flush">
    @forelse($notifications as $n)
        <a href="{{ route('notifications.open', $n->id) }}" class="list-group-item list-group-item-action d-flex gap-3 {{ $n->read_at ? '' : 'bg-warning-subtle' }}">
            <i class="bi bi-{{ $n->data['icon'] ?? 'bell' }} text-{{ $n->data['color'] ?? 'primary' }} fs-5"></i>
            <div class="flex-grow-1"><div class="fw-semibold">{{ $n->data['title'] }}</div><div class="small text-muted">{{ $n->data['message'] }}</div></div>
            <div class="small text-muted text-nowrap">{{ $n->created_at->diffForHumans() }}</div>
        </a>
    @empty
        <div class="list-group-item"><x-empty message="No notifications." icon="bell" /></div>
    @endforelse
</div></div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
