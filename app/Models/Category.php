<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id'];

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            $c->slug = Str::slug($c->name).($c->exists ? '' : '-'.Str::lower(Str::random(4)));
            if ($c->exists && static::where('slug', $c->slug)->whereKeyNot($c->id)->exists()) {
                $c->slug .= '-'.$c->id;
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }
}
