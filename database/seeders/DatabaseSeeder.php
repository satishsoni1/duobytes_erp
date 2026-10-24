<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the minimum needed to log in and start using the CRM. Safe to run more than once.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Administrator',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate(['code' => 'SHOW1'], ['name' => 'Main Showroom', 'type' => 'showroom', 'is_default' => true]);
        Warehouse::firstOrCreate(['code' => 'GDN1'], ['name' => 'Godown', 'type' => 'godown']);

        foreach (['Sofas', 'Beds', 'Dining', 'Wardrobes', 'Office Furniture', 'Mattresses', 'Decor'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        $defaults = [
            'company_name' => config('app.name'),
            'default_tax_rate' => '18',
            'default_country_code' => config('crm.default_country_code'),
            'quotation_validity_days' => '15',
            'lead_assignment' => 'round_robin',
            'lead_first_response_minutes' => '30',
            'whatsapp_auto_create_lead' => '1',
            'allow_negative_stock' => '0',
            'whatsapp_template_language' => 'en',
            'webhook_token' => Str::random(40),
        ];
        foreach ($defaults as $key => $value) {
            if (Setting::where('key', $key)->doesntExist()) {
                Setting::set($key, $value);
            }
        }
    }
}
