<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'wa_message_id', 'phone', 'contact_name', 'direction', 'type', 'body', 'template_name', 'media_id',
        'media_path', 'status', 'error', 'contact_type', 'contact_id', 'user_id', 'is_read', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'is_read' => 'boolean'];
    }

    public function contact()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
