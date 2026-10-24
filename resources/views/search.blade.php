@extends('layouts.app')
@section('title', 'Search')
@section('content')
<x-page-header title="Search results" :subtitle="$term ? 'for “'.e($term).'”' : 'Type at least 2 characters'" />
@forelse($results as $group => $items)
    <div class="card mb-3"><div class="card-header">{{ $group }} <span class="text-muted small">({{ $items->count() }})</span></div>
        <div class="list-group list-group-flush">
            @foreach($items as $item)
                <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $item['title'] }}</span><span class="text-muted small">{{ $item['sub'] }}</span></a>
            @endforeach
        </div></div>
@empty
    <div class="card"><x-empty message="No matches found." icon="search" /></div>
@endforelse
@endsection
