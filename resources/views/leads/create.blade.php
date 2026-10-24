@extends('layouts.app')
@section('title', 'New Lead')
@section('content')
<x-page-header title="New lead" :back="route('leads.index')" />
<form method="POST" action="{{ route('leads.store') }}" class="card"><div class="card-body">
    @csrf
    @include('leads._form')
    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="send_welcome" value="1" id="welcome" @checked(old('send_welcome', setting('notify_lead_welcome_enabled') === '1'))>
        <label class="form-check-label" for="welcome">Send WhatsApp welcome message (if enabled in Settings)</label></div>
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Create lead</button></div></form>
@endsection
