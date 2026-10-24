@extends('layouts.app')
@section('title', 'New Customer')
@section('content')
<x-page-header title="New customer" :back="route('customers.index')" />
<form method="POST" action="{{ route('customers.store') }}" class="card"><div class="card-body">@csrf @include('customers._form')</div>
<div class="card-footer bg-white text-end"><button class="btn btn-primary">Create customer</button></div></form>
@endsection
