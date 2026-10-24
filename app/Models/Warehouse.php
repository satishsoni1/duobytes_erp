<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['name', 'code', 'type', 'address', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::first();
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }
}
