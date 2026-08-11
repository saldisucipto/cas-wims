<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // UserSeeder::class,
            // DailyWorkerSeeder::class,
            // PackingStationSeeder::class,
            // RfDeviceSeeder::class,
            // ConsumableSeeder::class,
            AtkItemSeeder::class,
            AtkActivitySeeder::class,
            // WmsAccountSeeder::class,
            // WorkingSessionSeeder::class,
        ]);
    }
}
