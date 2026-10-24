@extends('layouts.app')
@section('title', $quotation->number)
@section('content')
<x-page-header :title="'Quotation '.$quotation->number" :back="route('quotations.index')"
    :subtitle="'<span class=\'badge badge-soft-'.config('crm.quotation_statuses.'.$quotation->status).'\'>'.ucfirst($quotation->status).'</span> · '.e($quotation->customer->name).' · prepared by '.e($quotation->user?->name)">
    <a href="{{ route('quotations.print', $quotation) }}" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-printer"></i> Print / PDF</a>
    <button class="btn btn-light btn-sm" onclick="navigator.clipboard.writeText(@js($publicUrl)).then(() => this.innerHTML = '<i class=\'bi bi-check\'></i> Copied')"><i class="bi bi-link-45deg"></i> Copy link</button>
    <form method="POST" action="{{ route('quotations.send', $quotation) }}">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Send on WhatsApp</button></form>
    <form method="POST" action="{{ route('quotations.duplicate', $quotation) }}">@csrf<button class="btn btn-light btn-sm"><i class="bi bi-copy"></i> Duplicate</button></form>
    @if($quotation->status !== 'converted')
        <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#convertModal"><i class="bi bi-bag-check"></i> Convert to order</button>
    @elseif($quotation->order)
        <a href="{{ route('orders.show', $quotation->order) }}" class="btn btn-primary btn-sm">View order {{ $quotation->order->number }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Disc</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @foreach($quotation->items as $item)
                <tr><td>{{ $loop->iteration }}</td><td>{{ $item->description }}@if($item->product)<div class="small text-muted">{{ $item->product->sku }}</div>@endif</td>
                    <td class="text-end">{{ $item->quantity }}</td><td class="text-end">{{ money($item->unit_price, false) }}</td>
                    <td class="text-end">{{ (float) $item->discount_percent }}%</td><td class="text-end">{{ (float) $item->tax_rate }}%</td><td class="text-end">{{ money($item->total, false) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr><td colspan="6" class="text-end">Subtotal</td><td class="text-end">{{ money($quotation->subtotal, false) }}</td></tr>
                <tr><td colspan="6" class="text-end">Tax</td><td class="text-end">{{ money($quotation->tax_total, false) }}</td></tr>
                @if($quotation->discount > 0)<tr><td colspan="6" class="text-end">Discount</td><td class="text-end">− {{ money($quotation->discount, false) }}</td></tr>@endif
                <tr class="fw-bold"><td colspan="6" class="text-end">Total</td><td class="text-end">{{ money($quotation->total) }}</td></tr>
            </tfoot>
        </table></div></div>
        @include('partials.timeline', ['subject' => $quotation])
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body"><dl class="dl-grid small">
            <dt>Customer</dt><dd><a href="{{ route('customers.show', $quotation->customer) }}">{{ $quotation->customer->name }}</a><br>{{ $quotation->customer->phone }}</dd>
            <dt>Date</dt><dd>{{ $quotation->quote_date->format('d M Y') }}</dd>
            <dt>Valid until</dt><dd>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</dd>
            @if($quotation->lead)<dt>Lead</dt><dd><a href="{{ route('leads.show', $quotation->lead) }}">{{ $quotation->lead->code }}</a></dd>@endif
            @if($quotation->notes)<dt>Notes</dt><dd style="white-space:pre-line">{{ $quotation->notes }}</dd>@endif
        </dl></div></div>
        @if($quotation->status !== 'converted')
            <div class="card"><div class="card-header">Status</div><div class="card-body">
                <form method="POST" action="{{ route('quotations.status', $quotation) }}" class="d-flex gap-2">@csrf @method('PATCH')
                    <select name="status" class="form-select form-select-sm">@foreach(['draft', 'sent', 'accepted', 'rejected', 'expired'] as $s)<option value="{{ $s }}" @selected($quotation->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                    <button class="btn btn-sm btn-light">Update</button></form>
                <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" data-confirm="Delete this quotation?" class="mt-3">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete quotation</button></form>
            </div></div>
        @endif
    </div>
</div>

<div class="modal fade" id="convertModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('quotations.convert', $quotation) }}" class="modal-content">
    @csrf
    <div class="modal-header"><h5 class="modal-title">Convert to sales order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label small fw-semibold">Fulfil from</label>
        <select name="warehouse_id" class="form-select">@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected($w->is_default)>{{ $w->name }}</option>@endforeach</select>
        <div class="form-text">Stock is reserved from this location once the order is confirmed.</div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Create order</button></div>
</form></div></div>
@endsection
