<?php

namespace Database\Seeders;

use App\Models\WorkingCalendar;
use Illuminate\Database\Seeder;

class WorkingCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $days = [
            [1, 'Monday', true, 8],
            [2, 'Tuesday', true, 8],
            [3, 'Wednesday', true, 8],
            [4, 'Thursday', true, 8],
            [5, 'Friday', true, 8],
            [6, 'Saturday', true, 5],
            [7, 'Sunday', false, 0],
        ];

        foreach ($days as [$dayOfWeek, $name, $working, $hours]) {
            WorkingCalendar::query()->updateOrCreate(
                ['day_of_week' => $dayOfWeek],
                ['day_name' => $name, 'is_working_day' => $working, 'working_hours' => $hours],
            );
        }
    }
}
