<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $inbound = Division::query()->where('code', 'INB')->first();
        $outbound = Division::query()->where('code', 'OB')->first();

        $positions = [
            ['code' => 'UNLOAD', 'name' => 'Unloading', 'division' => $inbound, 'device_type' => null, 'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00'],
            ['code' => 'RECEIVE', 'name' => 'Receiving', 'division' => $inbound, 'device_type' => 'RF', 'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00'],
            ['code' => 'PUTAWAY', 'name' => 'Putaway', 'division' => $inbound, 'device_type' => 'RF', 'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00'],
            ['code' => 'VAS', 'name' => 'VAS', 'division' => $inbound, 'device_type' => 'PC', 'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00'],
            ['code' => 'PICKER', 'name' => 'Picker', 'division' => $outbound, 'device_type' => 'RF', 'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00'],
            ['code' => 'CHECKPACK', 'name' => 'Check-Pack', 'division' => $outbound, 'device_type' => 'PC', 'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00'],
            ['code' => 'DISPATCH', 'name' => 'Dispatch', 'division' => $outbound, 'device_type' => 'RF', 'allowed_shifts' => 'S2', 'start_time' => '15:00', 'end_time' => '23:00'],
            ['code' => 'HANDOVER', 'name' => 'Handover', 'division' => $outbound, 'device_type' => 'RF', 'allowed_shifts' => 'S2', 'start_time' => '15:00', 'end_time' => '23:00'],
        ];

        foreach ($positions as $position) {
            Position::query()->updateOrCreate(
                ['code' => $position['code']],
                [
                    'name' => $position['name'],
                    'division_id' => $position['division']?->id,
                    'device_type' => $position['device_type'],
                    'allowed_shifts' => $position['allowed_shifts'],
                    'start_time' => $position['start_time'],
                    'end_time' => $position['end_time'],
                ],
            );
        }
    }
}
