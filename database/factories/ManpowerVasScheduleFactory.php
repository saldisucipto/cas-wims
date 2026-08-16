<?php

namespace Database\Factories;

use App\Models\ManpowerVasSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManpowerVasSchedule>
 */
class ManpowerVasScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_date' => now()->toDateString(),
            'volume' => 100,
        ];
    }
}
