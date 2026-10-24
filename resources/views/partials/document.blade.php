{{-- Printable quotation / invoice. Expects $doc, $docTitle, $docNumber, $docDate, $items, $extra (optional). --}}
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $docTitle }} {{ $docNumber }}</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f2efeb; font-size: 13px; padding: 16px; }
    .sheet { max-width: 860px; margin: auto; background: #fff; padding: 40px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .doc-title { color: #083a7c; letter-spacing: .1em; }
    table.items th { background: #f6f1eb; font-size: 11px; text-transform: uppercase; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; padding: 0; } .no-print { display: none; } }
    @media (max-width: 600px) { .sheet { padding: 16px; } }
</style></head>
<body>
<div class="text-center mb-3 no-print"><button onclick="window.print()" class="btn btn-dark btn-sm">Print / Save as PDF</button></div>
<div class="sheet">
    <div class="d-flex justify-content-between flex-wrap gap-3 border-bottom pb-3 mb-3">
        <div>
            @if(setting('company_logo'))<img src="{{ Storage::disk('public')->url(setting('company_logo')) }}" alt="" style="max-height:56px" class="mb-2"><br>@endif
            <strong class="fs-5">{{ setting('company_name', config('app.name')) }}</strong>
            <div class="text-muted" style="white-space:pre-line">{{ setting('company_address') }}</div>
            <div class="text-muted">{{ collect([setting('company_phone'), setting('company_email'), setting('company_website')])->filter()->implode(' · ') }}</div>
            @if(setting('company_tax_number'))<div>GSTIN: {{ setting('company_tax_number') }}</div>@endif
        </div>
        <div class="text-end">
            <h2 class="doc-title fw-bold mb-1">{{ strtoupper($docTitle) }}</h2>
            <div><strong>{{ $docNumber }}</strong></div>
            <div>Date: {{ $docDate->format('d M Y') }}</div>
            {!! $extra ?? '' !!}
        </div>
    </div>
    <div class="mb-3">
        <div class="text-muted small text-uppercase">Bill to</div>
        <strong>{{ $doc->customer->display_name }}</strong>
        <div style="white-space:pre-line">{{ collect([$doc->customer->address, $doc->customer->city, $doc->customer->pincode])->filter()->implode(', ') }}</div>
        <div>{{ $doc->customer->phone }} {{ $doc->customer->email ? '· '.$doc->customer->email : '' }}</div>
        @if($doc->customer->tax_number)<div>GSTIN: {{ $doc->customer->tax_number }}</div>@endif
    </div>
    <table class="table table-bordered items">
        <thead><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Disc</th><th class="text-end">Tax</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        @foreach($items as $item)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $item->description }}</td><td class="text-end">{{ $item->quantity }}</td>
                <td class="text-end">{{ money($item->unit_price, false) }}</td><td class="text-end">{{ (float) $item->discount_percent }}%</td>
                <td class="text-end">{{ (float) $item->tax_rate }}%<br><small>{{ money($item->tax_amount, false) }}</small></td><td class="text-end">{{ money($item->total, false) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6" class="text-end">Subtotal</td><td class="text-end">{{ money($doc->subtotal, false) }}</td></tr>
            <tr><td colspan="6" class="text-end">Tax</td><td class="text-end">{{ money($doc->tax_total, false) }}</td></tr>
            @if($doc->discount > 0)<tr><td colspan="6" class="text-end">Discount</td><td class="text-end">− {{ money($doc->discount, false) }}</td></tr>@endif
            @if(($doc->delivery_charge ?? 0) > 0)<tr><td colspan="6" class="text-end">Delivery</td><td class="text-end">{{ money($doc->delivery_charge, false) }}</td></tr>@endif
            <tr class="fw-bold"><td colspan="6" class="text-end">Grand total</td><td class="text-end">{{ money($doc->total) }}</td></tr>
            {!! $footerRows ?? '' !!}
        </tfoot>
    </table>
    @if($doc->notes)<p><strong>Notes:</strong><br><span style="white-space:pre-line">{{ $doc->notes }}</span></p>@endif
    @if($terms)<p class="small"><strong>Terms & conditions:</strong><br><span style="white-space:pre-line">{{ $terms }}</span></p>@endif
    @if(setting('bank_details'))<p class="small"><strong>Bank details:</strong><br><span style="white-space:pre-line">{{ setting('bank_details') }}</span></p>@endif
    <div class="d-flex justify-content-between mt-5 pt-4 small">
        <div>Customer signature</div><div class="text-end">For {{ setting('company_name', config('app.name')) }}<br><br>Authorised signatory</div>
    </div>
</div>
</body></html>
