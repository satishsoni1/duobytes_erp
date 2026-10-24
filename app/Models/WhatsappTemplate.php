<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $fillable = ['name', 'language', 'category', 'status', 'body', 'variable_count', 'components'];

    protected function casts(): array
    {
        return ['components' => 'array'];
    }

    public function render(array $params): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $params[$m[1] - 1] ?? $m[0], (string) $this->body);
    }
}
