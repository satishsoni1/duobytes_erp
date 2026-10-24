<?php

namespace App\Models;

use App\Models\Concerns\HasLineItems;
use App\Models\Concerns\HasTimeline;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasLineItems, HasTimeline, SoftDeletes;

    protected $fillable = [
        'number', 'customer_id', 'lead_id', 'user_id', 'status', 'quote_date', 'valid_until',
        'subtotal', 'discount', 'tax_total', 'total', 'notes', 'terms',
    ];

    protected function casts(): array
    {
        return ['quote_date' => 'date', 'valid_until' => 'date'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Quotation $q) => $q->number ??= NumberSequence::next('QT'));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function order()
    {
        return $this->hasOne(SalesOrder::class);
    }
}
