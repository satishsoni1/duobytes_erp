@extends('layouts.app')
@section('title', 'Suppliers')
@section('content')
<x-page-header title="Suppliers"><a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Supplier</a></x-page-header>
<form class="mb-3" style="max-width:360px"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search suppliers…"></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Supplier</th><th>Contact</th><th>GSTIN</th><th class="text-end">POs</th><th></th></tr></thead><tbody>
    @forelse($suppliers as $s)
        <tr><td class="fw-semibold">{{ $s->name }}<div class="small text-muted">{{ $s->address }}</div></td>
            <td class="small">{{ $s->contact_person }}<div>{{ $s->phone }} {{ $s->email }}</div></td><td class="small">{{ $s->tax_number }}</td>
            <td class="text-end"><a href="{{ route('purchase-orders.index', ['supplier_id' => $s->id]) }}">{{ $s->purchase_orders_count }}</a></td>
            <td class="text-end text-nowrap"><a href="{{ route('suppliers.edit', $s) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('suppliers.destroy', $s) }}" class="d-inline" data-confirm="Archive supplier?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-archive"></i></button></form></td></tr>
    @empty <tr><td colspan="5"><x-empty message="No suppliers yet." icon="building" /></td></tr> @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
