<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise a phone number to digits-only international format (E.164 without "+"),
     * which is what the WhatsApp Cloud API expects.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        $cc = (string) (setting('default_country_code') ?: config('crm.default_country_code'));

        // Local 10-digit number → prefix country code.
        if (strlen($digits) <= 10) {
            $digits = $cc.$digits;
        }

        return $digits;
    }

    /**
     * Return the variants a phone may have been stored as, for duplicate lookups.
     */
    public static function variants(?string $phone): array
    {
        $normalized = self::normalize($phone);
        if (! $normalized) {
            return [];
        }

        return array_values(array_unique([$normalized, substr($normalized, -10)]));
    }
}
