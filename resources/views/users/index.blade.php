@extends('layouts.app')
@section('title', 'Users')
@section('content')
<x-page-header title="Users & roles">
    <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New User</a>
</x-page-header>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ request()->fullUrlWithQuery(['role' => null, 'page' => null]) }}" class="btn btn-sm {{ request('role') ? 'btn-light' : 'btn-primary' }}">All</a>
    @foreach(config('crm.roles') as $k => $v)
        <a href="{{ request()->fullUrlWithQuery(['role' => $k, 'page' => null]) }}" class="btn btn-sm {{ request('role') === $k ? 'btn-primary' : 'btn-light' }}">{{ $v }}</a>
    @endforeach
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Lead rotation</th><th class="text-end">Open leads</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($users as $u)
        <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
            <td><div class="d-flex align-items-center gap-2"><span class="avatar">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                <div><div class="fw-semibold">{{ $u->name }} @if($u->is(auth()->user()))<span class="badge badge-soft-secondary">You</span>@endif</div><div class="small text-muted">{{ $u->email }}</div></div></div></td>
            <td class="small">{{ $u->role_label }}</td>
            <td class="small">{{ $u->phone ?? '—' }}</td>
            <td>@if($u->receives_leads)<span class="badge badge-soft-success">Receives leads</span>@else<span class="text-muted small">—</span>@endif</td>
            <td class="text-end">{{ $u->open_leads }}</td>
            <td class="small text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
            <td>@if($u->is_active)<span class="badge badge-soft-success">Active</span>@else<span class="badge badge-soft-danger">Inactive</span>@endif</td>
            <td class="text-end text-nowrap">
                <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                @if($u->is_active && ! $u->is(auth()->user()))
                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline" data-confirm="Deactivate {{ $u->name }}? They will no longer be able to log in.">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-light text-danger" aria-label="Deactivate"><i class="bi bi-person-slash"></i></button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="8"><x-empty message="No users found." icon="people" /></td></tr>
    @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
