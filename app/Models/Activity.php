<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['user_id', 'type', 'description', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'call' => 'telephone', 'email' => 'envelope', 'whatsapp' => 'whatsapp',
            'meeting' => 'people', 'status' => 'arrow-repeat', 'note' => 'sticky', default => 'info-circle',
        };
    }
}
