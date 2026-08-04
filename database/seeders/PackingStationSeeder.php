<?php

namespace Database\Seeders;

use App\Models\PackingStation;
use Illuminate\Database\Seeder;

class PackingStationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($index = 1; $index <= 20; $index++) {
            $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            PackingStation::query()->updateOrCreate(
                ['code' => 'STATION ' . $number],
                [
                    'station_number' => $number,
                    'name' => 'Packing ' . $number,
                    'qr_code' => 'QR-STATION-' . $number,
                    'status' => 'Active',
                ]
            );
        }
    }
}
