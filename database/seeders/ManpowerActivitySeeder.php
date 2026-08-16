<?php

namespace Database\Seeders;

use App\Models\ManpowerActivity;
use Illuminate\Database\Seeder;

class ManpowerActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = [
            [
                'division' => 'Inbound', 'name' => 'Unloading', 'code' => 'INB-UNLOAD',
                'workload_source' => 'Inbound', 'workload_unit' => 'PCS', 'conversion_ratio' => 1,
                'productivity_per_hour' => 800, 'productivity_unit' => 'pcs/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 20, 'device_type' => null,
                'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00', 'sort_order' => 10,
            ],
            [
                'division' => 'Inbound', 'name' => 'Receiving', 'code' => 'INB-RECEIVE',
                'workload_source' => 'Inbound', 'workload_unit' => 'PCS', 'conversion_ratio' => 1,
                'productivity_per_hour' => 400, 'productivity_unit' => 'pcs/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 40, 'device_type' => 'RF',
                'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00', 'sort_order' => 20,
            ],
            [
                'division' => 'Inbound', 'name' => 'Putaway', 'code' => 'INB-PUTAWAY',
                'workload_source' => 'Inbound', 'workload_unit' => 'PCS', 'conversion_ratio' => 1,
                'productivity_per_hour' => 600, 'productivity_unit' => 'pcs/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 50, 'device_type' => 'RF',
                'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00', 'sort_order' => 30,
            ],
            [
                'division' => 'Inbound', 'name' => 'VAS', 'code' => 'INB-VAS',
                'workload_source' => 'VAS', 'workload_unit' => 'PCS', 'conversion_ratio' => 1,
                'productivity_per_hour' => 200, 'productivity_unit' => 'pcs/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 20, 'device_type' => 'PC',
                'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00', 'sort_order' => 40,
            ],
            [
                'division' => 'Outbound', 'name' => 'Picker', 'code' => 'OB-PICKER',
                'workload_source' => 'Outbound', 'workload_unit' => 'PCS', 'conversion_ratio' => 2.8,
                'productivity_per_hour' => 150, 'productivity_unit' => 'pcs/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 150, 'device_type' => 'RF',
                'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00', 'sort_order' => 10,
            ],
            [
                'division' => 'Outbound', 'name' => 'Check-Pack', 'code' => 'OB-CHECKPACK',
                'workload_source' => 'Outbound', 'workload_unit' => 'Order', 'conversion_ratio' => 1,
                'productivity_per_hour' => 24, 'productivity_unit' => 'order/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 300, 'device_type' => 'PC',
                'allowed_shifts' => 'S1,S2', 'start_time' => '07:00', 'end_time' => '23:00', 'sort_order' => 20,
            ],
            [
                'division' => 'Outbound', 'name' => 'Dispatch', 'code' => 'OB-DISPATCH',
                'workload_source' => 'Outbound', 'workload_unit' => 'Order', 'conversion_ratio' => 1,
                'productivity_per_hour' => 500, 'productivity_unit' => 'order/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 30, 'device_type' => 'RF',
                'allowed_shifts' => 'S2', 'start_time' => '15:00', 'end_time' => '23:00', 'sort_order' => 30,
            ],
            [
                'division' => 'Outbound', 'name' => 'Handover', 'code' => 'OB-HANDOVER',
                'workload_source' => 'Outbound', 'workload_unit' => 'Order', 'conversion_ratio' => 1,
                'productivity_per_hour' => 400, 'productivity_unit' => 'order/hour',
                'manpower_type' => 'Variable', 'minimum_manpower' => null,
                'available_manpower' => 30, 'device_type' => 'RF',
                'allowed_shifts' => 'S2', 'start_time' => '15:00', 'end_time' => '23:00', 'sort_order' => 40,
            ],
        ];

        foreach ($activities as $activity) {
            ManpowerActivity::query()->updateOrCreate(
                ['code' => $activity['code']],
                $activity,
            );
        }
    }
}
