<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SalesOrder;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, SalesOrder $order, SalesService $sales): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $order->balance)],
            'method' => ['required', Rule::in(array_keys(config('crm.payment_methods')))],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['amount.max' => 'Amount exceeds the balance due ('.money($order->balance).').']);

        $sales->recordPayment($order, $data);

        return back()->with('success', 'Payment recorded.');
    }

    public function destroy(Request $request, Payment $payment, SalesService $sales): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        $sales->deletePayment($payment);

        return back()->with('success', 'Payment deleted.');
    }
}
