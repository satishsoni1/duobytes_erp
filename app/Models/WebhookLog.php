<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = ['source', 'payload', 'status', 'error'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
