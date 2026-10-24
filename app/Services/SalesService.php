<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    public function __construct(
        protected InventoryService $inventory,
        protected CustomerNotifier $notifier,
    ) {}

    public function convertQuotation(Quotation $quotation, ?int $warehouseId = null): SalesOrder
    {
        if ($quotation->status === 'converted' && $quotation->order) {
            return $quotation->order;
        }

        return DB::transaction(function () use ($quotation, $warehouseId) {
            $quotation->loadMissing('items.product', 'customer');

            $order = SalesOrder::create([
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'lead_id' => $quotation->lead_id,
                'user_id' => $quotation->user_id ?? auth()->id(),
                'warehouse_id' => $warehouseId ?? Warehouse::default()?->id,
                'status' => 'pending',
                'order_date' => today(),
                'requires_installation' => $quotation->items->contains(fn ($i) => $i->product?->requires_installation),
                'delivery_address' => $quotation->customer->address,
                'discount' => $quotation->discount,
                'notes' => $quotation->notes,
            ]);

            $order->syncItems($quotation->items->map->only([
                'product_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_rate',
            ])->all());

            $quotation->update(['status' => 'converted']);
            $quotation->logActivity("Converted to sales order {$order->number}", 'system');
            $order->logActivity("Created from quotation {$quotation->number}", 'system');

            if ($lead = $quotation->lead) {
                $lead->update(['status' => 'won', 'converted_at' => now(), 'customer_id' => $quotation->customer_id]);
                $lead->logActivity("Lead won – order {$order->number}", 'status');
            }

            return $order;
        });
    }

    public function changeStatus(SalesOrder $order, string $status, ?string $note = null): void
    {
        if (! array_key_exists($status, config('crm.order_statuses')) || $order->status === $status) {
            return;
        }

        DB::transaction(function () use ($order, $status, $note) {
            $order->loadMissing('items.product', 'customer');
            $old = $order->status;

            if (in_array($status, config('crm.stock_deduct_statuses'), true) && ! $order->stock_deducted) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $this->inventory->adjust($item->product, $order->warehouse_id, -$item->quantity, 'sale', $order, "Order {$order->number}");
                    }
                }
                $order->stock_deducted = true;
            }

            if ($status === 'cancelled' && $order->stock_deducted) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $this->inventory->adjust($item->product, $order->warehouse_id, $item->quantity, 'sale_return', $order, "Order {$order->number} cancelled");
                    }
                }
                $order->stock_deducted = false;
            }

            $order->status = $status;
            $order->save();

            $order->logActivity(
                'Status changed from '.config("crm.order_statuses.$old.label").' to '.config("crm.order_statuses.$status.label").($note ? " – $note" : ''),
                'status'
            );

            if ($status === 'delivered' && $order->requires_installation && ! $order->installations()->exists()) {
                $installation = Installation::create([
                    'customer_id' => $order->customer_id,
                    'sales_order_id' => $order->id,
                    'status' => 'pending',
                    'address' => $order->delivery_address ?: ($order->customer->address ?? 'To be confirmed'),
                    'contact_phone' => $order->customer->phone,
                    'created_by' => auth()->id(),
                ]);
                $installation->logActivity("Auto-created on delivery of {$order->number}", 'system');
            }
        });

        $vars = [
            'number' => $order->number,
            'total' => money($order->total),
            'delivery_date' => $order->delivery_date?->format('d M Y') ?? 'to be confirmed',
        ];
        match ($status) {
            'confirmed' => $this->notifier->send('order_confirmed', $order->customer, $vars),
            'dispatched' => $this->notifier->send('order_dispatched', $order->customer, $vars),
            'delivered' => $this->notifier->send('order_delivered', $order->customer, $vars),
            default => null,
        };
    }

    public function recordPayment(SalesOrder $order, array $data): Payment
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages(['amount' => 'Cannot record payment on a cancelled order.']);
        }

        $payment = DB::transaction(function () use ($order, $data) {
            $payment = $order->payments()->create($data + ['received_by' => auth()->id()]);
            $order->refreshPaymentStatus();
            $order->logActivity('Payment of '.money($payment->amount).' received via '.config("crm.payment_methods.{$payment->method}"), 'system');

            return $payment;
        });

        $this->notifier->send('payment_received', $order->customer, [
            'amount' => money($payment->amount),
            'number' => $order->number,
            'balance' => money($order->balance),
        ]);

        return $payment;
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $order = $payment->order;
            $payment->delete();
            $order->refreshPaymentStatus();
            $order->logActivity('Payment of '.money($payment->amount).' deleted', 'system');
        });
    }
}
