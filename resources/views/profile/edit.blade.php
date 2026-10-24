@extends('layouts.app')
@section('title', 'Profile')
@section('content')
<x-page-header title="My profile" :subtitle="$user->email.' · '.$user->role_label" />
<div class="card" style="max-width:720px"><div class="card-body">
    <form method="POST" action="{{ route('profile.update') }}" class="row g-3">
        @csrf @method('PUT')
        <x-field name="name" label="Name" :value="$user->name" required />
        <x-field name="phone" label="Phone" :value="$user->phone" />
        <div class="col-12"><hr><h6 class="mb-0">Change password</h6></div>
        <x-field name="current_password" type="password" label="Current password" col="col-md-4" />
        <x-field name="password" type="password" label="New password" col="col-md-4" />
        <x-field name="password_confirmation" type="password" label="Confirm" col="col-md-4" />
        <div class="col-12"><button class="btn btn-primary">Save</button></div>
    </form>
</div></div>
@endsection
