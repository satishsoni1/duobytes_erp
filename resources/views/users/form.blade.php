@extends('layouts.app')
@section('title', $user->exists ? 'Edit '.$user->name : 'New User')
@section('content')
<x-page-header :title="$user->exists ? 'Edit '.$user->name : 'New user'" :back="route('users.index')" />
<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card"><div class="card-body row g-3">
            @csrf @if($user->exists) @method('PUT') @endif
            <x-field name="name" label="Full name" :value="$user->name" required />
            <x-field name="email" type="email" label="Email (login)" :value="$user->email" required />
            <x-field name="phone" label="Mobile" :value="$user->phone" />
            <x-field name="role" type="select" label="Role" :options="config('crm.roles')" :value="$user->role" :empty="false" required />
            <x-field name="password" type="password" :label="$user->exists ? 'New password' : 'Password'" :required="! $user->exists" :help="$user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters.'" />
            <div class="col-md-6"></div>
            <x-field name="is_active" type="checkbox" label="Active (can log in)" :value="$user->is_active" />
            <x-field name="receives_leads" type="checkbox" label="Include in automatic lead rotation" :value="$user->receives_leads" />
        </div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save user</button></div></form>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Role access</div><div class="card-body small">
            @php($modules = collect(config('crm.permissions')))
            @foreach(config('crm.roles') as $role => $label)
                <div class="mb-2"><span class="fw-semibold">{{ $label }}:</span>
                    <span class="text-muted">{{ $role === 'admin' ? 'Everything, including users and settings' : ($modules->filter(fn ($roles) => in_array($role, $roles, true))->keys()->map(fn ($m) => str($m)->replace('.', ' ')->title())->implode(', ') ?: '—') }}</span></div>
            @endforeach
            <div class="text-muted mt-2">Sales executives only see leads and orders assigned to them; admins, managers and support see everything.</div>
        </div></div>
    </div>
</div>
@endsection
