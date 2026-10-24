@extends('layouts.app')
@section('title', 'Edit '.$lead->name)
@section('content')
<x-page-header :title="'Edit '.$lead->name" :back="route('leads.show', $lead)" />
<form method="POST" action="{{ route('leads.update', $lead) }}" class="card"><div class="card-body">
    @csrf @method('PUT')
    @include('leads._form')
</div><div class="card-footer bg-white text-end"><button class="btn btn-primary">Save changes</button></div></form>
@endsection
