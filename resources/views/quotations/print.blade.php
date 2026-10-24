@include('partials.document', [
    'doc' => $quotation, 'docTitle' => 'Quotation', 'docNumber' => $quotation->number, 'docDate' => $quotation->quote_date,
    'items' => $quotation->items, 'terms' => $quotation->terms,
    'extra' => $quotation->valid_until ? '<div>Valid until: '.$quotation->valid_until->format('d M Y').'</div>' : '',
])
