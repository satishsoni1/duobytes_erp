<?php

namespace App\Models;

use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'warehouse_id', 'status', 'order_date', 'expected_date',
        'subtotal', 'tax_total', 'total', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (PurchaseOrder $po) => $po->number ??= NumberSequence::next('PO'));
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function syncItems(array $lines): void
    {
        $this->items()->delete();
        $subtotal = $tax = 0;
        foreach ($lines as $line) {
            if (empty($line['product_id'])) {
                continue;
            }
            $qty = max(1, (int) $line['quantity']);
            $base = $qty * (float) $line['unit_cost'];
            $lineTax = $base * (float) ($line['tax_rate'] ?? 0) / 100;
            $this->items()->create([
                'product_id' => $line['product_id'], 'quantity' => $qty,
                'unit_cost' => $line['unit_cost'], 'tax_rate' => $line['tax_rate'] ?? 0,
                'total' => round($base + $lineTax, 2),
            ]);
            $subtotal += $base;
            $tax += $lineTax;
        }
        $this->update(['subtotal' => round($subtotal, 2), 'tax_total' => round($tax, 2), 'total' => round($subtotal + $tax, 2)]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'ordered'], true) && $this->items->sum('received_quantity') === 0;
    }
}
