@extends('layouts.app')
@section('title', 'Locations')
@section('content')
<x-page-header title="Showrooms & godowns" subtitle="Every stock location – stock is tracked per location" />
<div class="row g-3">
    <div class="col-lg-4"><form method="POST" action="{{ route('warehouses.store') }}" class="card"><div class="card-header">Add location</div><div class="card-body row g-3">
        @csrf @include('warehouses._fields', ['warehouse' => new \App\Models\Warehouse(['type' => 'showroom'])])
        <div class="col-12"><button class="btn btn-primary w-100">Add</button></div>
    </div></form></div>
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Location</th><th>Type</th><th class="text-end">Units in stock</th><th></th></tr></thead><tbody>
        @forelse($warehouses as $w)
            <tr><td>{{ $w->name }} <span class="text-muted small">{{ $w->code }}</span> @if($w->is_default)<span class="badge badge-soft-primary">Default</span>@endif<div class="small text-muted">{{ $w->address }}</div></td>
                <td class="small">{{ ucfirst($w->type) }}</td><td class="text-end">{{ (int) $w->stocks_sum_quantity }}</td>
                <td class="text-end text-nowrap"><a href="{{ route('warehouses.edit', $w) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('warehouses.destroy', $w) }}" class="d-inline" data-confirm="Delete location?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @empty <tr><td colspan="4"><x-empty message="Add your first showroom or godown." icon="shop" /></td></tr> @endforelse
    </tbody></table></div></div></div>
</div>
@endsection
