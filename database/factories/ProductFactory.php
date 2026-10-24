<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(2000, 60000);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('FUR-####')),
            'name' => fake()->randomElement(['Teak', 'Sheesham', 'Oak', 'Walnut', 'Velvet']).' '.fake()->randomElement(['Sofa', 'Bed', 'Dining Table', 'Wardrobe', 'Recliner']).' '.fake()->unique()->numberBetween(1, 9999),
            'unit' => 'pcs',
            'cost_price' => $cost,
            'selling_price' => round($cost * 1.4),
            'tax_rate' => 18,
            'reorder_level' => 2,
            'warranty_months' => 12,
            'track_stock' => true,
            'is_active' => true,
        ];
    }
}
