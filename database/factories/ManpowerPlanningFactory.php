<?php

namespace Database\Factories;

use App\Models\ManpowerPlanning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManpowerPlanning>
 */
class ManpowerPlanningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'planning_number' => 'MP-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'planning_date' => now()->toDateString(),
            'inbound_volume' => 10000,
            'outbound_volume' => 5000,
            'vas_volume' => 0,
            'shift_duration' => 8,
            'non_productive_hours' => 1,
            'effective_working_hours' => 7,
            'total_mpp' => 0,
            'recommendation' => '1 Shift',
            'overall_status' => 'FEASIBLE',
            'status' => 'CALCULATED',
            'revision' => 1,
        ];
    }
}
