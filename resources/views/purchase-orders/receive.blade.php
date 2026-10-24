@extends('layouts.app')
@section('title', 'Receive '.$po->number)
@section('content')
<x-page-header :title="'Goods receipt – '.$po->number" :back="route('purchase-orders.show', $po)" :subtitle="'Stock will be added to '.e($po->warehouse->name)" />
<form method="POST" action="{{ route('purchase-orders.receive.store', $po) }}" class="card">
    @csrf
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Product</th><th class="text-end">Ordered</th><th class="text-end">Already received</th><th class="text-end">Pending</th><th style="width:160px">Receiving now</th></tr></thead><tbody>
        @foreach($po->items as $item)
            <tr><td>{{ $item->product->name }}<div class="small text-muted">{{ $item->product->sku }}</div></td><td class="text-end">{{ $item->quantity }}</td>
                <td class="text-end">{{ $item->received_quantity }}</td><td class="text-end fw-semibold">{{ $item->pending_quantity }}</td>
                <td><input type="number" min="0" max="{{ $item->pending_quantity }}" name="received[{{ $item->id }}]" value="{{ $item->pending_quantity }}" class="form-control form-control-sm" @disabled(! $item->pending_quantity)></td></tr>
        @endforeach
        </tbody></table></div>
    <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> Receive into stock</button></div>
</form>
@endsection
