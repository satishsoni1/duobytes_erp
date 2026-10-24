@extends('layouts.app')
@section('title', 'New Product')
@section('content')
<x-page-header title="New product" :back="route('products.index')" />
<form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">@csrf @include('products._form')
<div class="text-end mt-3"><button class="btn btn-primary">Create product</button></div></form>
@endsection
