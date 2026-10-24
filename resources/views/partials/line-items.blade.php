{{-- Line item editor shared by quotations and sales orders. Expects $lines (array) and optional $showDelivery. --}}
@php($lines = collect($lines)->values()->all() ?: [[]])
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Items</span>
        <button type="button" class="btn btn-sm btn-light" id="addLine"><i class="bi bi-plus-lg"></i> Add item</button></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="lineTable">
            <thead><tr><th style="min-width:280px">Product / description</th><th style="width:90px">Qty</th><th style="width:130px">Rate</th><th style="width:90px">Disc %</th><th style="width:90px">Tax %</th><th style="width:130px" class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @foreach($lines as $i => $line)
                <tr class="line">
                    <td>
                        <select class="form-select form-select-sm product-pick mb-1" name="items[{{ $i }}][product_id]" placeholder="Search product (optional)">
                            @if(! empty($line['product_id']))<option value="{{ $line['product_id'] }}" selected>{{ $line['description'] ?? 'Product #'.$line['product_id'] }}</option>@endif
                        </select>
                        <input name="items[{{ $i }}][description]" class="form-control form-control-sm desc" value="{{ $line['description'] ?? '' }}" placeholder="Description (fabric, finish, size…)" required>
                        <div class="small text-muted stock-hint"></div>
                    </td>
                    <td><input type="number" min="1" name="items[{{ $i }}][quantity]" class="form-control form-control-sm qty" value="{{ $line['quantity'] ?? 1 }}" required></td>
                    <td><input type="number" min="0" step="0.01" name="items[{{ $i }}][unit_price]" class="form-control form-control-sm price" value="{{ $line['unit_price'] ?? '' }}" required></td>
                    <td><input type="number" min="0" max="100" step="0.01" name="items[{{ $i }}][discount_percent]" class="form-control form-control-sm disc" value="{{ $line['discount_percent'] ?? 0 }}"></td>
                    <td><input type="number" min="0" max="100" step="0.01" name="items[{{ $i }}][tax_rate]" class="form-control form-control-sm tax" value="{{ $line['tax_rate'] ?? setting('default_tax_rate', 18) }}"></td>
                    <td class="text-end fw-semibold amount">0.00</td>
                    <td><button type="button" class="btn btn-sm btn-link text-danger remove" aria-label="Remove"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body border-top">
        <div class="row justify-content-end"><div class="col-md-5 col-lg-4">
            <table class="table table-sm mb-0">
                <tr><td>Subtotal</td><td class="text-end" id="tSub">0.00</td></tr>
                <tr><td>Tax</td><td class="text-end" id="tTax">0.00</td></tr>
                <tr><td>Additional discount</td><td><input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control form-control-sm text-end" value="{{ old('discount', $discount ?? 0) }}"></td></tr>
                @if($showDelivery ?? false)
                    <tr><td>Delivery charges</td><td><input type="number" step="0.01" min="0" name="delivery_charge" id="delivery" class="form-control form-control-sm text-end" value="{{ old('delivery_charge', $delivery ?? 0) }}"></td></tr>
                @endif
                <tr class="fw-bold fs-6"><td>Grand total</td><td class="text-end" id="tTotal">0.00</td></tr>
            </table>
        </div></div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const tbody = document.querySelector('#lineTable tbody');
    const fmt = n => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const recalc = () => {
        let sub = 0, tax = 0;
        tbody.querySelectorAll('tr.line').forEach(tr => {
            const q = +tr.querySelector('.qty').value || 0, p = +tr.querySelector('.price').value || 0;
            const d = +tr.querySelector('.disc').value || 0, t = +tr.querySelector('.tax').value || 0;
            const base = q * p * (1 - d / 100), lineTax = base * t / 100;
            sub += base; tax += lineTax;
            tr.querySelector('.amount').textContent = fmt(base + lineTax);
        });
        const disc = +document.getElementById('discount').value || 0;
        const del = +(document.getElementById('delivery')?.value || 0);
        document.getElementById('tSub').textContent = fmt(sub);
        document.getElementById('tTax').textContent = fmt(tax);
        document.getElementById('tTotal').textContent = CRM.currency + ' ' + fmt(sub + tax - disc + del);
    };
    const bindProduct = tr => {
        const el = tr.querySelector('.product-pick');
        const ts = new TomSelect(el, {
            valueField: 'value', labelField: 'text', searchField: 'text', create: false, allowEmptyOption: true,
            load(q, cb) { fetch(@json(route('lookup.products')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()); },
            render: { option: d => `<div>${d.text}<div class="small text-muted">${d.price !== undefined ? CRM.currency + ' ' + d.price + ' · stock ' + d.stock : ''}</div></div>` },
            onChange(v) {
                const d = this.options[v]; if (!d || d.price === undefined) return;
                tr.querySelector('.desc').value = d.text;
                tr.querySelector('.price').value = d.price;
                tr.querySelector('.tax').value = d.tax;
                tr.querySelector('.stock-hint').textContent = 'In stock: ' + d.stock;
                recalc();
            },
        });
        tr._ts = ts;
    };
    let idx = tbody.querySelectorAll('tr.line').length;
    document.getElementById('addLine').addEventListener('click', () => {
        const tpl = tbody.querySelector('tr.line');
        tpl._ts?.destroy();
        const tr = tpl.cloneNode(true);
        bindProduct(tpl);
        tr.querySelectorAll('[name]').forEach(i => { i.name = i.name.replace(/items\[\d+]/, `items[${idx}]`); });
        tr.querySelector('.product-pick').innerHTML = '';
        tr.querySelectorAll('.desc,.price').forEach(i => i.value = '');
        tr.querySelector('.qty').value = 1; tr.querySelector('.disc').value = 0;
        tr.querySelector('.stock-hint').textContent = '';
        tbody.appendChild(tr); bindProduct(tr); idx++; recalc();
    });
    tbody.addEventListener('click', e => {
        if (!e.target.closest('.remove')) return;
        if (tbody.querySelectorAll('tr.line').length > 1) { e.target.closest('tr').remove(); recalc(); }
    });
    document.addEventListener('input', e => { if (e.target.closest('#lineTable') || ['discount', 'delivery'].includes(e.target.id)) recalc(); });
    tbody.querySelectorAll('tr.line').forEach(bindProduct);
    recalc();
})();
</script>
@endpush
