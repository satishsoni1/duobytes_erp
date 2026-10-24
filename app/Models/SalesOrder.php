<?php

namespace App\Models;

use App\Models\Concerns\HasLineItems;
use App\Models\Concerns\HasTimeline;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasLineItems, HasTimeline, SoftDeletes;

    protected $fillable = [
        'number', 'customer_id', 'quotation_id', 'lead_id', 'user_id', 'warehouse_id', 'status', 'order_date',
        'delivery_date', 'requires_installation', 'delivery_address', 'subtotal', 'discount', 'tax_total',
        'delivery_charge', 'total', 'paid_amount', 'payment_status', 'stock_deducted', 'notes',
        'tally_guid', 'tally_voucher_number', 'tally_voucher_type', 'tally_alter_id',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date', 'delivery_date' => 'date',
            'requires_installation' => 'boolean', 'stock_deducted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (SalesOrder $o) => $o->number ??= NumberSequence::next('SO'));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest('paid_on');
    }

    public function installations()
    {
        return $this->hasMany(Installation::class);
    }

    public function tickets()
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $this->paid_amount = $paid;
        $this->payment_status = $paid <= 0 ? 'unpaid' : ($paid + 0.009 >= (float) $this->total ? 'paid' : 'partial');
        $this->save();
    }

    public function isLocked(): bool
    {
        return $this->stock_deducted || in_array($this->status, ['delivered', 'completed', 'cancelled'], true);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->seesAllRecords() || $user->hasRole('inventory') ? $q : $q->where('user_id', $user->id);
    }
}
