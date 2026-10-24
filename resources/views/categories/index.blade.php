@extends('layouts.app')
@section('title', 'Categories')
@section('content')
<x-page-header title="Product categories" />
<div class="row g-3">
    <div class="col-lg-4"><form method="POST" action="{{ route('categories.store') }}" class="card"><div class="card-header">Add category</div><div class="card-body row g-3">
        @csrf
        <x-field name="name" label="Name" required col="col-12" placeholder="Sofas, Beds, Dining, Wardrobes…" />
        <x-field name="parent_id" type="select" label="Parent" :options="$parents->pluck('name', 'id')" col="col-12" empty="— Top level —" />
        <div class="col-12"><button class="btn btn-primary w-100">Add</button></div>
    </div></form></div>
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Category</th><th>Parent</th><th class="text-end">Products</th><th></th></tr></thead><tbody>
        @forelse($categories as $c)
            <tr><td>{{ $c->name }}</td><td class="small text-muted">{{ $c->parent?->name ?? '—' }}</td><td class="text-end"><a href="{{ route('products.index', ['category' => $c->id]) }}">{{ $c->products_count }}</a></td>
                <td class="text-end text-nowrap"><a href="{{ route('categories.edit', $c) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('categories.destroy', $c) }}" class="d-inline" data-confirm="Delete category?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @empty <tr><td colspan="4"><x-empty message="No categories yet." icon="tags" /></td></tr> @endforelse
    </tbody></table></div></div></div>
</div>
@endsection
