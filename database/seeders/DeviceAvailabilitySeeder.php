<?php

namespace Database\Seeders;

use App\Models\DeviceAvailability;
use Illuminate\Database\Seeder;

class DeviceAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $devices = [
            ['device_type' => 'PC', 'ready_quantity' => 10],
            ['device_type' => 'RF', 'ready_quantity' => 24],
        ];

        foreach ($devices as $device) {
            DeviceAvailability::query()->updateOrCreate(
                ['device_type' => $device['device_type']],
                $device,
            );
        }
    }
}
