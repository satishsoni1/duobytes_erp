<?php

namespace App\Models;

use App\Models\Concerns\HasTimeline;
use App\Services\NumberSequence;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, HasTimeline, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'phone', 'whatsapp', 'email', 'city', 'address', 'source', 'source_ref', 'campaign',
        'status', 'priority', 'requirement', 'budget', 'expected_close_date', 'next_follow_up_at',
        'last_contacted_at', 'assigned_to', 'customer_id', 'lost_reason', 'notes', 'meta', 'converted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'budget' => 'decimal:2',
            'expected_close_date' => 'date',
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            $lead->code ??= NumberSequence::next('LD', false, 6);
        });

        static::saving(function (Lead $lead) {
            $lead->phone = Phone::normalize($lead->phone);
            $lead->whatsapp = Phone::normalize($lead->whatsapp) ?? $lead->phone;
            $lead->email = $lead->email ? strtolower(trim($lead->email)) : null;
        });
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function whatsappMessages()
    {
        return $this->morphMany(WhatsappMessage::class, 'contact');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->seesAllRecords() ? $q : $q->where('assigned_to', $user->id);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['won', 'lost']);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('name', 'like', "%$term%")
            ->orWhere('code', 'like', "%$term%")
            ->orWhere('phone', 'like', "%$term%")
            ->orWhere('email', 'like', "%$term%")
            ->orWhere('requirement', 'like', "%$term%"));
    }

    public static function findOpenDuplicate(?string $phone, ?string $email): ?self
    {
        $variants = Phone::variants($phone);
        if (! $variants && ! $email) {
            return null;
        }

        return static::query()->open()
            ->where(function ($q) use ($variants, $email) {
                foreach ($variants as $v) {
                    $q->orWhere('phone', 'like', "%$v")->orWhere('whatsapp', 'like', "%$v");
                }
                if ($email) {
                    $q->orWhere('email', strtolower($email));
                }
            })
            ->latest()
            ->first();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['won', 'lost'], true);
    }

    public function getSourceLabelAttribute(): string
    {
        return config("crm.lead_sources.{$this->source}", $this->source);
    }
}
