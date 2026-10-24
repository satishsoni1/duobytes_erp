<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All stock changes flow through here so that product_stocks and the
 * stock_movements ledger always agree.
 */
class InventoryService
{
    public function adjust(
        Product $product,
        int $warehouseId,
        int $quantity,
        string $type,
        ?Model $reference = null,
        ?string $note = null,
        ?float $unitCost = null,
        bool $allowNegative = false,
    ): ?StockMovement {
        if ($quantity === 0 || ! $product->track_stock) {
            return null;
        }

        return DB::transaction(function () use ($product, $warehouseId, $quantity, $type, $reference, $note, $unitCost, $allowNegative) {
            ProductStock::firstOrCreate(['product_id' => $product->id, 'warehouse_id' => $warehouseId], ['quantity' => 0]);
            $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();

            $balance = $stock->quantity + $quantity;
            if ($balance < 0 && ! $allowNegative && setting('allow_negative_stock', '0') !== '1') {
                throw ValidationException::withMessages([
                    'stock' => "Insufficient stock for {$product->name} ({$product->sku}). Available: {$stock->quantity}, required: ".abs($quantity).'.',
                ]);
            }

            $stock->update(['quantity' => $balance]);

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balance,
                'unit_cost' => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }

    public function transfer(Product $product, int $fromWarehouse, int $toWarehouse, int $quantity, ?string $note = null): void
    {
        if ($fromWarehouse === $toWarehouse) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'Source and destination must differ.']);
        }

        DB::transaction(function () use ($product, $fromWarehouse, $toWarehouse, $quantity, $note) {
            $this->adjust($product, $fromWarehouse, -$quantity, 'transfer_out', null, $note);
            $this->adjust($product, $toWarehouse, $quantity, 'transfer_in', null, $note);
        });
    }
}
