@extends('layouts.app')
@section('title', 'Purchase Orders')
@section('content')
<x-page-header title="Purchase orders">
    <a href="{{ route('purchase-orders.create', ['low_stock' => 1]) }}" class="btn btn-light btn-sm"><i class="bi bi-lightning"></i> Reorder low stock</a>
    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New PO</a>
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="PO number…"></div>
    <div class="col-md-3"><select name="supplier_id" class="form-select form-select-sm"><option value="">All suppliers</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>@foreach(config('crm.purchase_statuses') as $k => $c)<option value="{{ $k }}" @selected(request('status') === $k)>{{ ucfirst($k) }}</option>@endforeach</select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>PO</th><th>Supplier</th><th>Deliver to</th><th>Ordered</th><th>Expected</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody>
    @forelse($orders as $po)
        <tr><td><a href="{{ route('purchase-orders.show', $po) }}" class="fw-semibold">{{ $po->number }}</a></td><td>{{ $po->supplier->name }}</td><td class="small">{{ $po->warehouse->name }}</td>
            <td class="small">{{ $po->order_date->format('d M Y') }}</td>
            <td class="small {{ $po->expected_date?->isPast() && in_array($po->status, ['ordered', 'partial']) ? 'overdue' : '' }}">{{ $po->expected_date?->format('d M Y') ?? '—' }}</td>
            <td><span class="badge badge-soft-{{ config("crm.purchase_statuses.{$po->status}") }}">{{ ucfirst($po->status) }}</span></td><td class="text-end">{{ money($po->total) }}</td></tr>
    @empty <tr><td colspan="7"><x-empty message="No purchase orders." icon="truck" /></td></tr> @endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
