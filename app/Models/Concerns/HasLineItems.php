<?php

namespace App\Models\Concerns;

/**
 * Shared totals logic for quotations, sales orders.
 * Line: base = qty * price * (1 - disc%), tax = base * rate%, total = base + tax.
 * Document: total = subtotal + tax_total - discount (+ delivery_charge when present).
 */
trait HasLineItems
{
    public static function calculateLine(array $line): array
    {
        $qty = max(1, (int) ($line['quantity'] ?? 1));
        $price = (float) ($line['unit_price'] ?? 0);
        $disc = min(100, max(0, (float) ($line['discount_percent'] ?? 0)));
        $rate = max(0, (float) ($line['tax_rate'] ?? 0));

        $base = round($qty * $price * (1 - $disc / 100), 2);
        $tax = round($base * $rate / 100, 2);

        return [
            'product_id' => $line['product_id'] ?: null,
            'description' => $line['description'],
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_percent' => $disc,
            'tax_rate' => $rate,
            'tax_amount' => $tax,
            'total' => round($base + $tax, 2),
        ];
    }

    public function syncItems(array $lines): void
    {
        $this->items()->delete();
        foreach ($lines as $line) {
            if (blank($line['description'] ?? null)) {
                continue;
            }
            $this->items()->create(static::calculateLine($line));
        }
        $this->load('items');
        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $tax = $this->items->sum('tax_amount');
        $subtotal = $this->items->sum(fn ($i) => $i->total - $i->tax_amount);

        $this->subtotal = round($subtotal, 2);
        $this->tax_total = round($tax, 2);
        $this->total = round($subtotal + $tax - (float) $this->discount + (float) ($this->delivery_charge ?? 0), 2);
        $this->save();
    }
}
