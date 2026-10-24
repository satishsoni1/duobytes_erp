<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'is_active', 'receives_leads',
        'last_lead_assigned_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_lead_assigned_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'receives_leads' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canAccess(string $ability): bool
    {
        return $this->isAdmin() || in_array($this->role, config("crm.permissions.$ability", []), true);
    }

    /** Sales users only see their own leads; managers/admins see everything. */
    public function seesAllRecords(): bool
    {
        return $this->hasRole('admin', 'manager', 'support');
    }

    public function getRoleLabelAttribute(): string
    {
        return config("crm.roles.{$this->role}", $this->role);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeRole(Builder $q, string|array $roles): Builder
    {
        return $q->whereIn('role', (array) $roles);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }
}
