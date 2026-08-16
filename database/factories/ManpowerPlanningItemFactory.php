<?php

namespace Database\Factories;

use App\Models\ManpowerPlanning;
use App\Models\ManpowerPlanningItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManpowerPlanningItem>
 */
class ManpowerPlanningItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manpower_planning_id' => ManpowerPlanning::factory(),
            'division' => 'Inbound',
            'name' => 'Unloading',
            'code' => 'INB-UNLOAD',
            'workload_source' => 'Inbound',
            'workload' => 10000,
            'workload_unit' => 'PCS',
            'productivity_per_hour' => 800,
            'productivity_unit' => 'pcs/hour',
            'manpower_type' => 'Variable',
            'minimum_manpower' => null,
            'shift_duration' => 8,
            'non_productive_hours' => 1,
            'effective_working_hours' => 7,
            'required_mpp' => 2,
            'mpp_per_shift' => 1,
            'number_of_shift' => 1,
            'available_mpp' => 20,
            'feasibility_status' => 'Feasible',
            'bottleneck' => false,
            'sort_order' => 1,
        ];
    }
}
