@extends('layouts.app')
@section('title', 'Edit Location')
@section('content')
<x-page-header :title="'Edit '.$warehouse->name" :back="route('warehouses.index')" />
<form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="card" style="max-width:640px"><div class="card-body row g-3">
    @csrf @method('PUT') @include('warehouses._fields')
    <div class="col-12"><button class="btn btn-primary">Save</button></div>
</div></form>
@endsection
