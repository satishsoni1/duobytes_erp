<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = ['user_id', 'path', 'original_name', 'mime', 'size', 'label'];

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $a) => Storage::disk('public')->delete($a->path));
    }

    public function attachable()
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
