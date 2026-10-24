<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Keys whose values are stored encrypted at rest. */
    public const SECRET_KEYS = ['whatsapp_access_token', 'meta_app_secret', 'meta_page_access_token'];

    public static function all_cached(): array
    {
        return Cache::rememberForever('crm.settings', function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function get(string $key, $default = null)
    {
        $value = static::all_cached()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        if (in_array($key, self::SECRET_KEYS, true) && filled($value)) {
            $value = Crypt::encryptString($value);
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('crm.settings');
    }
}
