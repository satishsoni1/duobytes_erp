<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'category_id', 'brand', 'material', 'color', 'finish', 'dimensions', 'description',
        'unit', 'cost_price', 'selling_price', 'mrp', 'tax_rate', 'hsn_code', 'tally_name', 'reorder_level',
        'warranty_months', 'requires_installation', 'track_stock', 'is_active', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'mrp' => 'decimal:2',
            'tax_rate' => 'decimal:2', 'requires_installation' => 'boolean', 'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /** Requires withSum('stocks', 'quantity') for efficiency; falls back to a query. */
    public function getTotalStockAttribute(): int
    {
        return (int) ($this->stocks_sum_quantity ?? $this->stocks()->sum('quantity'));
    }

    public function stockIn(?int $warehouseId): int
    {
        return (int) $this->stocks()->where('warehouse_id', $warehouseId)->value('quantity');
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->total_stock <= $this->reorder_level;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('name', 'like', "%$term%")
            ->orWhere('sku', 'like', "%$term%")
            ->orWhere('material', 'like', "%$term%")
            ->orWhere('brand', 'like', "%$term%"));
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->where('track_stock', true)->whereRaw(
            'reorder_level >= (select coalesce(sum(quantity),0) from product_stocks where product_stocks.product_id = products.id)'
        );
    }
}
