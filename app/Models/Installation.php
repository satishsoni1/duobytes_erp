<?php

namespace App\Models;

use App\Models\Concerns\HasTimeline;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installation extends Model
{
    use HasTimeline, SoftDeletes;

    protected $fillable = [
        'number', 'customer_id', 'sales_order_id', 'technician_id', 'status', 'priority', 'address',
        'contact_phone', 'scheduled_date', 'time_slot', 'instructions', 'completion_notes',
        'customer_rating', 'customer_feedback', 'started_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Installation $i) => $i->number ??= NumberSequence::next('INS'));
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->hasRole('technician') ? $q->where('technician_id', $user->id) : $q;
    }

    public function getSlotLabelAttribute(): ?string
    {
        return config("crm.time_slots.{$this->time_slot}");
    }
}
