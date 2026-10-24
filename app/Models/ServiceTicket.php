<?php

namespace App\Models;

use App\Models\Concerns\HasTimeline;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTicket extends Model
{
    use HasTimeline, SoftDeletes;

    protected $fillable = [
        'number', 'customer_id', 'sales_order_id', 'product_id', 'assigned_to', 'type', 'source', 'priority',
        'status', 'subject', 'description', 'is_under_warranty', 'warranty_expires_on', 'due_at', 'visit_date',
        'visit_slot', 'resolution', 'service_charge', 'rating', 'feedback', 'resolved_at', 'closed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_under_warranty' => 'boolean', 'warranty_expires_on' => 'date', 'due_at' => 'datetime',
            'visit_date' => 'date', 'resolved_at' => 'datetime', 'closed_at' => 'datetime',
            'service_charge' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceTicket $t) {
            $t->number ??= NumberSequence::next('SR');
            $t->due_at ??= now()->addHours(config("crm.sla_hours.{$t->priority}", 72));
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['resolved', 'closed', 'cancelled']);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->hasRole('technician') ? $q->where('assigned_to', $user->id) : $q;
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && ! in_array($this->status, ['resolved', 'closed', 'cancelled'], true);
    }

    /**
     * Determine warranty coverage from the linked order date + product warranty months.
     */
    public function evaluateWarranty(): void
    {
        $order = $this->order;
        $product = $this->product;
        if (! $order || ! $product) {
            return;
        }
        $expires = ($order->delivery_date ?? $order->order_date)->copy()->addMonths($product->warranty_months);
        $this->warranty_expires_on = $expires;
        $this->is_under_warranty = $expires->isFuture();
    }
}
