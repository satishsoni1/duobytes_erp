@extends('layouts.app')
@section('title', 'Edit '.$customer->name)
@section('content')
<x-page-header :title="'Edit '.$customer->name" :back="route('customers.show', $customer)" />
<form method="POST" action="{{ route('customers.update', $customer) }}" class="card"><div class="card-body">@csrf @method('PUT') @include('customers._form')</div>
<div class="card-footer bg-white text-end"><button class="btn btn-primary">Save changes</button></div></form>
@endsection
