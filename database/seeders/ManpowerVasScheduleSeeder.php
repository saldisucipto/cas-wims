<?php

namespace Database\Seeders;

use App\Models\ManpowerVasSchedule;
use Illuminate\Database\Seeder;

class ManpowerVasScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = [
            ['schedule_date' => '2026-08-18', 'volume' => 0],
            ['schedule_date' => '2026-08-19', 'volume' => 3000],
            ['schedule_date' => '2026-08-20', 'volume' => 0],
            ['schedule_date' => '2026-08-21', 'volume' => 5000],
        ];

        foreach ($schedules as $schedule) {
            ManpowerVasSchedule::query()->updateOrCreate(
                ['schedule_date' => $schedule['schedule_date']],
                ['volume' => $schedule['volume']],
            );
        }
    }
}
