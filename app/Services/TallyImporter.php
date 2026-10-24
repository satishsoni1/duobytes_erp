<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns Tally sales vouchers into CRM sales orders.
 *
 * - Idempotent: each voucher is keyed by its Tally GUID, so the same data can be imported repeatedly.
 * - Edited vouchers (higher ALTERID) re-sync their lines; cancelled or deleted ones cancel the order.
 * - Order totals always equal the Tally invoice value: rounding, freight and ledger-level discounts
 *   are reconciled into the order's delivery charge / discount.
 * - The sale already happened in Tally, so stock is deducted even if it goes negative.
 */
class TallyImporter
{
    /** @var array<string, ?Product> */
    protected array $productCache = [];

    public function __construct(
        protected TallyXmlParser $parser,
        protected InventoryService $inventory,
        protected SalesService $sales,
    ) {}

    /**
     * Parse, import and log one batch of Tally XML.
     *
     * @return array{created: int, updated: int, cancelled: int, unchanged: int, skipped: int, errors: list<string>, unmatched: list<string>}
     */
    public function importXml(string $xml, string $channel): array
    {
        $log = WebhookLog::create(['source' => 'tally', 'payload' => ['channel' => $channel, 'bytes' => strlen($xml)]]);

        try {
            $summary = $this->import($this->parser->parse($xml));
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);

            throw $e;
        }

        $log->update([
            'status' => $summary['errors'] ? 'failed' : 'processed',
            'payload' => ['channel' => $channel] + $summary,
            'error' => $summary['errors'] ? implode("\n", array_slice($summary['errors'], 0, 20)) : null,
        ]);
        Setting::set('tally_last_synced_at', now()->toDateTimeString());

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $vouchers  Output of TallyXmlParser::parse()
     * @return array{created: int, updated: int, cancelled: int, unchanged: int, skipped: int, errors: list<string>, unmatched: list<string>}
     */
    public function import(array $vouchers): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => [], 'unmatched' => []];
        $types = $this->voucherTypes();

        foreach ($vouchers as $voucher) {
            if (! in_array(Str::lower($voucher['type']), $types, true)) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = DB::transaction(fn () => $this->importVoucher($voucher, $summary['unmatched']));
                $summary[$result]++;
            } catch (Throwable $e) {
                $summary['errors'][] = "{$voucher['type']} {$voucher['number']} ({$voucher['date']->format('d-m-Y')}): {$e->getMessage()}";
            }
        }

        $summary['unmatched'] = array_values(array_unique($summary['unmatched']));

        return $summary;
    }

    /**
     * @return list<string>
     */
    public function voucherTypes(): array
    {
        return collect(explode(',', (string) setting('tally_voucher_types', 'Sales')))
            ->map(fn ($t) => Str::lower(trim($t)))->filter()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $v
     * @param  list<string>  $unmatched
     * @return 'created'|'updated'|'cancelled'|'unchanged'|'skipped'
     */
    protected function importVoucher(array $v, array &$unmatched): string
    {
        $order = SalesOrder::withTrashed()->where('tally_guid', $v['guid'])->first();

        if ($v['cancelled'] || $v['deleted']) {
            if ($order && ! $order->trashed() && $order->status !== 'cancelled') {
                $this->sales->changeStatus($order, 'cancelled', "{$v['type']} {$v['number']} cancelled in Tally");

                return 'cancelled';
            }

            return 'skipped';
        }

        if ($order && ($order->trashed() || $order->status === 'cancelled')) {
            return 'skipped';
        }
        if ($order && $v['alter_id'] > 0 && (int) $order->tally_alter_id >= $v['alter_id']) {
            return 'unchanged';
        }

        $isNew = ! $order;
        $customer = $this->resolveCustomer($v);
        $lines = $this->lines($v, $unmatched);

        if ($order?->stock_deducted) {
            $this->moveStock($order, +1, 'sale_return', "Tally {$v['number']} edited – re-syncing");
            $order->stock_deducted = false;
        }

        $order ??= new SalesOrder([
            'status' => setting('tally_order_status', 'confirmed'),
            'user_id' => $customer->assigned_to,
        ]);
        $order->fill([
            'customer_id' => $customer->id,
            'warehouse_id' => $this->resolveWarehouse($v)->id,
            'order_date' => $v['date'],
            'delivery_address' => $v['address'] ?? $customer->address,
            'discount' => 0,
            'delivery_charge' => 0,
            'notes' => $this->notes($v),
            'tally_guid' => $v['guid'],
            'tally_voucher_number' => $v['number'] ?: null,
            'tally_voucher_type' => $v['type'],
            'tally_alter_id' => $v['alter_id'] ?: null,
        ]);
        $order->requires_installation = collect($lines)->contains(fn ($l) => $l['product']?->requires_installation);
        $order->save();
        $order->syncItems(array_map(fn ($l) => collect($l)->except('product')->all(), $lines));

        $difference = round($v['total'] - ((float) $order->subtotal + (float) $order->tax_total), 2);
        $order->delivery_charge = max(0, $difference);
        $order->discount = max(0, -$difference);
        $order->recalculateTotals();
        $order->refreshPaymentStatus();

        if (setting('tally_deduct_stock', '1') === '1' && in_array($order->status, config('crm.stock_deduct_statuses'), true)) {
            $order->load('items.product');
            $this->moveStock($order, -1, 'sale', "Tally {$v['type']} {$v['number']}");
            $order->stock_deducted = true;
            $order->save();
        }

        $order->logActivity(($isNew ? 'Imported' : 'Updated').' from Tally '.$v['type'].' '.$v['number'], 'system');

        return $isNew ? 'created' : 'updated';
    }

    /**
     * @param  array<string, mixed>  $v
     * @param  list<string>  $unmatched
     * @return list<array<string, mixed>>
     */
    protected function lines(array $v, array &$unmatched): array
    {
        $taxable = array_sum(array_column($v['items'], 'amount'));
        $voucherRate = $taxable > 0 && $v['tax'] > 0 ? round($v['tax'] / $taxable * 100, 2) : 0;

        return array_map(function (array $item) use ($v, $voucherRate, &$unmatched) {
            $product = $this->resolveProduct($item['name']);
            if (! $product) {
                $unmatched[] = $item['name'];
            }

            $line = [
                'product' => $product,
                'product_id' => $product?->id,
                'description' => $item['name'],
                'tax_rate' => $v['tax'] > 0 ? ($item['tax_rate'] ?? $voucherRate) : 0,
            ];

            $quantity = $item['quantity'];
            $isWhole = $quantity >= 1 && abs($quantity - round($quantity)) < 0.0001;
            $expected = $quantity * $item['rate'] * (1 - $item['discount'] / 100);

            if ($isWhole && $item['rate'] > 0 && abs($expected - $item['amount']) <= 1) {
                return $line + ['quantity' => (int) round($quantity), 'unit_price' => $item['rate'], 'discount_percent' => $item['discount']];
            }
            if ($isWhole) {
                return $line + ['quantity' => (int) round($quantity), 'unit_price' => round($item['amount'] / round($quantity), 2), 'discount_percent' => 0];
            }

            // Fractional quantities (sq.ft, metres…) are kept as one line at the Tally amount.
            $line['description'] .= ' — '.rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.').' '.$item['unit'].' @ '.$item['rate'];

            return $line + ['quantity' => 1, 'unit_price' => $item['amount'], 'discount_percent' => 0];
        }, $v['items']);
    }

    /**
     * @param  array<string, mixed>  $v
     */
    protected function resolveCustomer(array $v): Customer
    {
        // Counter sales are often booked to a "Cash" ledger with the buyer's name typed on the invoice.
        $isCashLedger = (bool) preg_match('/^cash\b/i', $v['party']);
        $name = $isCashLedger ? $v['buyer'] : $v['party'];

        $customer = (! $isCashLedger ? Customer::where('tally_ledger_name', $v['party'])->first() : null)
            ?? ($v['gstin'] ? Customer::where('tax_number', $v['gstin'])->first() : null)
            ?? Customer::where('name', $name)->orWhere('company_name', $name)->first()
            ?? new Customer(['name' => $name, 'source' => 'tally', 'type' => $v['gstin'] ? 'business' : 'individual']);

        if (! $isCashLedger) {
            $customer->tally_ledger_name ??= $v['party'];
        }
        $customer->tax_number ??= $v['gstin'];
        $customer->address ??= $v['address'];
        $customer->state ??= $v['state'];
        $customer->pincode ??= $v['pincode'];
        $customer->save();

        return $customer;
    }

    protected function resolveProduct(string $name): ?Product
    {
        return $this->productCache[Str::lower($name)] ??= Product::where('tally_name', $name)->first()
            ?? Product::where('sku', $name)->first()
            ?? Product::where('name', $name)->first();
    }

    /**
     * @param  array<string, mixed>  $v
     */
    protected function resolveWarehouse(array $v): Warehouse
    {
        $godown = collect($v['items'])->pluck('godown')->filter()->first();

        return ($godown ? Warehouse::where('name', $godown)->first() : null) ?? Warehouse::default()
            ?? Warehouse::create(['name' => 'Main Showroom', 'code' => 'MAIN', 'type' => 'showroom', 'is_default' => true]);
    }

    /**
     * @param  array<string, mixed>  $v
     */
    protected function notes(array $v): string
    {
        $parts = ["Tally {$v['type']} {$v['number']} dated {$v['date']->format('d-m-Y')}"];
        if ($v['reference']) {
            $parts[] = "Ref: {$v['reference']}";
        }
        foreach ($v['charges'] as $charge) {
            $parts[] = "{$charge['name']}: ".money($charge['amount']);
        }
        if ($v['narration']) {
            $parts[] = $v['narration'];
        }

        return implode("\n", $parts);
    }

    /**
     * @param  1|-1  $direction  -1 deducts the order's items from stock, +1 returns them.
     */
    protected function moveStock(SalesOrder $order, int $direction, string $type, string $note): void
    {
        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            if ($item->product) {
                $this->inventory->adjust($item->product, $order->warehouse_id, $direction * $item->quantity, $type, $order, $note, allowNegative: true);
            }
        }
    }
}
