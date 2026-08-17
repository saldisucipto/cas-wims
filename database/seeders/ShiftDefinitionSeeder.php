<?php

namespace Database\Seeders;

use App\Models\ShiftDefinition;
use Illuminate\Database\Seeder;

class ShiftDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'code' => 'S1',
                'name' => 'Shift 1 (Morning)',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
                'break_minutes' => 60,
                'effective_hours' => 7,
                'is_short_day' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'S2',
                'name' => 'Shift 2 (Afternoon)',
                'start_time' => '14:00',
                'end_time' => '22:00',
                'break_start' => '18:00',
                'break_end' => '19:00',
                'break_minutes' => 60,
                'effective_hours' => 7,
                'is_short_day' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'S1_SAT',
                'name' => 'Saturday Short Shift 1',
                'start_time' => '08:00',
                'end_time' => '14:00',
                'break_start' => '09:00',
                'break_end' => '10:00',
                'break_minutes' => 60,
                'effective_hours' => 5,
                'is_short_day' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'S2_SAT',
                'name' => 'Saturday Short Shift 2',
                'start_time' => '12:00',
                'end_time' => '18:00',
                'break_start' => '14:00',
                'break_end' => '15:00',
                'break_minutes' => 60,
                'effective_hours' => 5,
                'is_short_day' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($definitions as $definition) {
            ShiftDefinition::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            );
        }
    }
}
