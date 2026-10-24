@extends('layouts.app')
@section('title', $po->number)
@section('content')
<x-page-header :title="'Purchase order '.$po->number" :back="route('purchase-orders.index')"
    :subtitle="'<span class=\'badge badge-soft-'.config('crm.purchase_statuses.'.$po->status).'\'>'.ucfirst($po->status).'</span> · '.e($po->supplier->name).' → '.e($po->warehouse->name)">
    <button onclick="window.print()" class="btn btn-light btn-sm"><i class="bi bi-printer"></i> Print</button>
    @if(in_array($po->status, ['draft', 'ordered', 'partial']))
        @if($po->status === 'draft')<form method="POST" action="{{ route('purchase-orders.status', $po) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="ordered"><button class="btn btn-light btn-sm"><i class="bi bi-send"></i> Mark ordered</button></form>@endif
        @if($po->isEditable())<a href="{{ route('purchase-orders.edit', $po) }}" class="btn btn-light btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
        <a href="{{ route('purchase-orders.receive', $po) }}" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-in-down"></i> Receive goods</a>
        <form method="POST" action="{{ route('purchase-orders.status', $po) }}" data-confirm="Cancel this PO?">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="btn btn-outline-danger btn-sm">Cancel</button></form>
    @endif
</x-page-header>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th>Product</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Unit cost</th><th class="text-end">Tax</th><th class="text-end">Total</th></tr></thead><tbody>
        @foreach($po->items as $item)
            <tr><td>{{ $item->product->name }}<div class="small text-muted">{{ $item->product->sku }}</div></td><td class="text-end">{{ $item->quantity }}</td>
                <td class="text-end {{ $item->pending_quantity ? 'text-warning' : 'text-success' }}">{{ $item->received_quantity }}</td>
                <td class="text-end">{{ money($item->unit_cost, false) }}</td><td class="text-end">{{ (float) $item->tax_rate }}%</td><td class="text-end">{{ money($item->total, false) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot class="table-light"><tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end">{{ money($po->subtotal, false) }}</td></tr>
            <tr><td colspan="5" class="text-end">Tax</td><td class="text-end">{{ money($po->tax_total, false) }}</td></tr>
            <tr class="fw-bold"><td colspan="5" class="text-end">Total</td><td class="text-end">{{ money($po->total) }}</td></tr></tfoot>
    </table></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><dl class="dl-grid small">
        <dt>Supplier</dt><dd>{{ $po->supplier->name }}<br>{{ $po->supplier->phone }}</dd>
        <dt>Deliver to</dt><dd>{{ $po->warehouse->name }}</dd>
        <dt>Order date</dt><dd>{{ $po->order_date->format('d M Y') }}</dd>
        <dt>Expected</dt><dd>{{ $po->expected_date?->format('d M Y') ?? '—' }}</dd>
        <dt>Created by</dt><dd>{{ $po->creator?->name }}</dd>
        @if($po->notes)<dt>Notes</dt><dd style="white-space:pre-line">{{ $po->notes }}</dd>@endif
    </dl>
    @if($po->items->sum('received_quantity') === 0)
        <form method="POST" action="{{ route('purchase-orders.destroy', $po) }}" data-confirm="Delete this PO?" class="mt-3">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger w-100">Delete</button></form>
    @endif
    </div></div></div>
</div>
@endsection
