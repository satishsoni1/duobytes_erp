@extends('layouts.app')
@section('title', 'Edit '.$product->name)
@section('content')
<x-page-header :title="'Edit '.$product->name" :back="route('products.show', $product)">
    <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Archive this product?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i> Archive</button></form>
</x-page-header>
<form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('products._form')
<div class="text-end mt-3"><button class="btn btn-primary">Save product</button></div></form>
@endsection
