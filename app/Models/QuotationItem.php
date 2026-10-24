<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = ['product_id', 'description', 'quantity', 'unit_price', 'discount_percent', 'tax_rate', 'tax_amount', 'total'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
