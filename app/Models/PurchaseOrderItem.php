<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'received_quantity', 'unit_cost', 'tax_rate', 'total'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getPendingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->received_quantity);
    }
}
