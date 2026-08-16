<?php

namespace Database\Factories;

use App\Models\ManpowerActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManpowerActivity>
 */
class ManpowerActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'division' => fake()->randomElement(['Inbound', 'Outbound']),
            'name' => fake()->unique()->word(),
            'code' => fake()->unique()->lexify('ACT-????'),
            'workload_source' => fake()->randomElement(['Inbound', 'Outbound', 'VAS']),
            'workload_unit' => fake()->randomElement(['PCS', 'Order']),
            'conversion_ratio' => 1,
            'productivity_per_hour' => 100,
            'productivity_unit' => 'pcs/hour',
            'manpower_type' => 'Variable',
            'minimum_manpower' => null,
            'available_manpower' => 10,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
