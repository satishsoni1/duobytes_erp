@include('partials.document', [
    'doc' => $order, 'docTitle' => $order->payment_status === 'paid' ? 'Tax Invoice' : 'Proforma Invoice', 'docNumber' => $order->number,
    'docDate' => $order->order_date, 'items' => $order->items, 'terms' => setting('invoice_terms'),
    'extra' => $order->delivery_date ? '<div>Delivery: '.$order->delivery_date->format('d M Y').'</div>' : '',
    'footerRows' => '<tr><td colspan="6" class="text-end">Paid</td><td class="text-end">'.money($order->paid_amount, false).'</td></tr>'
        .'<tr class="fw-bold"><td colspan="6" class="text-end">Balance due</td><td class="text-end">'.money($order->balance).'</td></tr>',
])
