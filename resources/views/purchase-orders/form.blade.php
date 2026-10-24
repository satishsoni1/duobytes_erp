@extends('layouts.app')
@section('title', $po->exists ? 'Edit '.$po->number : 'New Purchase Order')
@section('content')
@php($lines = collect($lines)->values()->all() ?: [[]])
<x-page-header :title="$po->exists ? 'Edit '.$po->number : 'New purchase order'" :back="$po->exists ? route('purchase-orders.show', $po) : route('purchase-orders.index')" />
<form method="POST" action="{{ $po->exists ? route('purchase-orders.update', $po) : route('purchase-orders.store') }}">
    @csrf @if($po->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body row g-3">
        <x-field name="supplier_id" type="select" label="Supplier" :options="$suppliers->pluck('name', 'id')" :value="$po->supplier_id" required col="col-md-4" class="searchable" />
        <x-field name="warehouse_id" type="select" label="Deliver to" :options="$warehouses->pluck('name', 'id')" :value="$po->warehouse_id" required col="col-md-3" :empty="false" />
        <x-field name="order_date" type="date" label="Order date" :value="$po->order_date" required col="col-md-2" />
        <x-field name="expected_date" type="date" label="Expected" :value="$po->expected_date" col="col-md-2" />
        <x-field name="status" type="select" label="Status" :options="['draft' => 'Draft', 'ordered' => 'Ordered']" :value="$po->status" col="col-md-1" :empty="false" />
    </div></div>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Items</span><button type="button" class="btn btn-sm btn-light" id="addPoLine"><i class="bi bi-plus-lg"></i> Add</button></div>
        <div class="table-responsive"><table class="table align-middle mb-0" id="poTable">
            <thead><tr><th style="min-width:280px">Product</th><th style="width:110px">Qty</th><th style="width:140px">Unit cost</th><th style="width:100px">Tax %</th><th class="text-end" style="width:140px">Total</th><th></th></tr></thead>
            <tbody>
            @foreach($lines as $i => $line)
                <tr class="line">
                    <td><select name="items[{{ $i }}][product_id]" class="form-select form-select-sm pick" required placeholder="Search product…">
                        @if(! empty($line['product_id']))<option value="{{ $line['product_id'] }}" selected>{{ $line['label'] ?? 'Product #'.$line['product_id'] }}</option>@endif</select></td>
                    <td><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" class="form-control form-control-sm qty" required></td>
                    <td><input type="number" min="0" step="0.01" name="items[{{ $i }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" class="form-control form-control-sm cost" required></td>
                    <td><input type="number" min="0" step="0.01" name="items[{{ $i }}][tax_rate]" value="{{ $line['tax_rate'] ?? setting('default_tax_rate', 18) }}" class="form-control form-control-sm tax"></td>
                    <td class="text-end amount fw-semibold">0.00</td>
                    <td><button type="button" class="btn btn-link btn-sm text-danger remove" aria-label="Remove"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="4" class="text-end fw-bold">Total</td><td class="text-end fw-bold" id="poTotal">0.00</td><td></td></tr></tfoot>
        </table></div></div>
    <div class="card mb-3"><div class="card-body"><x-field name="notes" type="textarea" label="Notes" :value="$po->notes" col="col-12" rows="2" /></div></div>
    <div class="text-end"><button class="btn btn-primary">Save purchase order</button></div>
</form>
@endsection
@push('scripts')
<script>
(() => {
    const tbody = document.querySelector('#poTable tbody');
    const recalc = () => { let sum = 0; tbody.querySelectorAll('tr.line').forEach(tr => { const v = (+tr.querySelector('.qty').value || 0) * (+tr.querySelector('.cost').value || 0) * (1 + (+tr.querySelector('.tax').value || 0) / 100); sum += v; tr.querySelector('.amount').textContent = v.toFixed(2); }); document.getElementById('poTotal').textContent = CRM.currency + ' ' + sum.toFixed(2); };
    const bind = tr => { tr._ts = new TomSelect(tr.querySelector('.pick'), { valueField: 'value', labelField: 'text', searchField: 'text',
        load(q, cb) { fetch(@json(route('lookup.products')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()); },
        onChange(v) { const d = this.options[v]; if (d && d.cost !== undefined) { tr.querySelector('.cost').value = d.cost; tr.querySelector('.tax').value = d.tax; recalc(); } } }); };
    let idx = tbody.querySelectorAll('tr.line').length;
    document.getElementById('addPoLine').onclick = () => {
        const tpl = tbody.querySelector('tr.line'); tpl._ts?.destroy(); const tr = tpl.cloneNode(true); bind(tpl);
        tr.querySelectorAll('[name]').forEach(i => i.name = i.name.replace(/items\[\d+]/, `items[${idx}]`));
        tr.querySelector('.pick').innerHTML = ''; tr.querySelector('.cost').value = ''; tr.querySelector('.qty').value = 1;
        tbody.appendChild(tr); bind(tr); idx++; recalc();
    };
    tbody.addEventListener('click', e => { if (e.target.closest('.remove') && tbody.querySelectorAll('tr.line').length > 1) { e.target.closest('tr').remove(); recalc(); } });
    tbody.addEventListener('input', recalc);
    tbody.querySelectorAll('tr.line').forEach(bind); recalc();
})();
</script>
@endpush
