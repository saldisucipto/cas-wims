<?php

namespace Database\Seeders;

use App\Models\RfDevice;
use Illuminate\Database\Seeder;

class RfDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($index = 1; $index <= 25; $index++) {
            $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            RfDevice::query()->updateOrCreate(
                ['code' => 'RF-' . $number],
                ['status' => 'Available']
            );
        }
    }
}