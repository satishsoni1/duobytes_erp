<?php

namespace App\Models;

use App\Models\Concerns\HasTimeline;
use App\Services\NumberSequence;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasTimeline, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'company_name', 'type', 'phone', 'alt_phone', 'whatsapp', 'email', 'tax_number',
        'address', 'city', 'state', 'pincode', 'source', 'birthday', 'anniversary', 'whatsapp_opt_in',
        'assigned_to', 'notes', 'tally_ledger_name',
    ];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'anniversary' => 'date', 'whatsapp_opt_in' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $c) {
            $c->code ??= NumberSequence::next('CUS', false);
        });

        static::saving(function (Customer $c) {
            $c->phone = Phone::normalize($c->phone);
            $c->whatsapp = Phone::normalize($c->whatsapp) ?? $c->phone;
        });
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function orders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function installations()
    {
        return $this->hasMany(Installation::class);
    }

    public function tickets()
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function whatsappMessages()
    {
        return $this->morphMany(WhatsappMessage::class, 'contact');
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
            ->orWhere('company_name', 'like', "%$term%"));
    }

    public static function findByContact(?string $phone, ?string $email = null): ?self
    {
        $variants = Phone::variants($phone);

        return static::query()
            ->where(function ($q) use ($variants, $email) {
                foreach ($variants as $v) {
                    $q->orWhere('phone', 'like', "%$v")->orWhere('whatsapp', 'like', "%$v");
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->when(! $variants && ! $email, fn ($q) => $q->whereRaw('1 = 0'))
            ->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ? "{$this->name} ({$this->company_name})" : $this->name;
    }

    public function getOutstandingAttribute(): float
    {
        return (float) $this->orders()->where('status', '!=', 'cancelled')->sum('total')
            - (float) $this->orders()->where('status', '!=', 'cancelled')->sum('paid_amount');
    }
}
