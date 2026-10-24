@extends('layouts.app')
@section('title', 'Edit Category')
@section('content')
<x-page-header :title="'Edit '.$category->name" :back="route('categories.index')" />
<form method="POST" action="{{ route('categories.update', $category) }}" class="card" style="max-width:520px"><div class="card-body row g-3">
    @csrf @method('PUT')
    <x-field name="name" label="Name" :value="$category->name" required col="col-12" />
    <x-field name="parent_id" type="select" label="Parent" :options="$parents->pluck('name', 'id')" :value="$category->parent_id" col="col-12" empty="— Top level —" />
    <div class="col-12"><button class="btn btn-primary">Save</button></div>
</div></form>
@endsection
