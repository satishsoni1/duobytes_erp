<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberSequence
{
    /**
     * Generate the next document number, e.g. "QT-2026-00012". Uses a row lock so
     * concurrent requests never receive the same number.
     */
    public static function next(string $prefix, bool $yearly = true, int $pad = 5): string
    {
        $key = $yearly ? $prefix.'-'.now()->year : $prefix;

        $value = DB::transaction(function () use ($key) {
            $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();

            if (! $row) {
                DB::table('number_sequences')->insert([
                    'key' => $key, 'next_value' => 2, 'created_at' => now(), 'updated_at' => now(),
                ]);

                return 1;
            }

            DB::table('number_sequences')->where('key', $key)->update([
                'next_value' => $row->next_value + 1, 'updated_at' => now(),
            ]);

            return $row->next_value;
        });

        return $key.'-'.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
    }
}
