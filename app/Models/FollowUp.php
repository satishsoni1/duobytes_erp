<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
    protected $fillable = ['user_id', 'created_by', 'type', 'title', 'due_at', 'completed_at', 'reminded_at', 'outcome'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function followable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('completed_at');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->pending()->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return ! $this->completed_at && $this->due_at->isPast();
    }
}
