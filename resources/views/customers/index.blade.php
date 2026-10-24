@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<x-page-header title="Customers" :subtitle="$customers->total().' customers'">
    <a href="{{ route('customers.export', request()->query()) }}" class="btn btn-light btn-sm"><i class="bi bi-download"></i> Export</a>
    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Customer</a>
</x-page-header>
<form class="card card-body mb-3 py-2"><div class="row g-2">
    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, code, phone, email, company…"></div>
    <div class="col-6 col-md-3"><select name="type" class="form-select form-select-sm"><option value="">All types</option>
        @foreach(config('crm.customer_types') as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><select name="city" class="form-select form-select-sm"><option value="">All cities</option>
        @foreach($cities as $c)<option @selected(request('city') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary">Filter</button><a href="{{ route('customers.index') }}" class="btn btn-sm btn-light">Reset</a></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Customer</th><th>Phone</th><th>Type</th><th>City</th><th class="text-end">Orders</th><th class="text-end">Lifetime value</th><th>Since</th></tr></thead>
    <tbody>
    @forelse($customers as $c)
        <tr>
            <td><a href="{{ route('customers.show', $c) }}" class="fw-semibold">{{ $c->name }}</a><div class="small text-muted">{{ $c->code }}{{ $c->company_name ? ' · '.$c->company_name : '' }}</div></td>
            <td class="small">{{ $c->phone }}<div class="text-muted">{{ $c->email }}</div></td>
            <td class="small">{{ config("crm.customer_types.{$c->type}") }}</td>
            <td class="small">{{ $c->city }}</td>
            <td class="text-end">{{ $c->orders_count }}</td>
            <td class="text-end">{{ money($c->revenue ?? 0) }}</td>
            <td class="small text-muted">{{ $c->created_at->format('M Y') }}</td>
        </tr>
    @empty
        <tr><td colspan="7"><x-empty message="No customers found." icon="people" /></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-3">{{ $customers->links() }}</div>
@endsection
