<?php

use App\Models\Setting;

if (! function_exists('money')) {
    function money($amount, bool $symbol = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $symbol ? config('crm.currency').' '.$formatted : $formatted;
    }
}

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('status_badge')) {
    /**
     * Render a bootstrap badge for a status defined in config/crm.php.
     */
    function status_badge(string $group, ?string $status): string
    {
        $def = config("crm.{$group}.{$status}");
        $label = is_array($def) ? $def['label'] : str($status)->replace('_', ' ')->title();
        $color = is_array($def) ? $def['color'] : ($def ?: 'secondary');

        return '<span class="badge badge-soft-'.e($color).'">'.e($label).'</span>';
    }
}
