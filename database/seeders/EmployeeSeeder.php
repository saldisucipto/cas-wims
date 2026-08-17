<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $outbound = Division::query()->where('code', 'OB')->first();
        $inbound = Division::query()->where('code', 'INB')->first();
        $inventory = Division::query()->where('code', 'INV')->first();

        $picker = Position::query()->where('code', 'PICKER')->first();
        $checkPack = Position::query()->where('code', 'CHECKPACK')->first();
        $dispatch = Position::query()->where('code', 'DISPATCH')->first();
        $handover = Position::query()->where('code', 'HANDOVER')->first();
        $receiving = Position::query()->where('code', 'RECEIVE')->first();
        $leader = Position::query()->where('code', 'LD')->first();

        $seed = [
            ['CASWH20261', 'Bella', $outbound, 'ROTATING'],
            ['CASWH20261', 'Wilya', $outbound, 'ROTATING'],
            ['CASWH20261', 'Ayu', $inbound, 'ROTATING'],
            ['CASWH20261', 'Arif', $inventory, 'ROTATING'],
        ];

        foreach ($seed as [$code, $name, $position, $pattern]) {
            Employee::query()->updateOrCreate(
                ['employee_code' => $code],
                [
                    'employee_name' => $name,
                    'division_id' => $position?->division_id ?? $outbound?->id,
                    'position_id' => $position?->id,
                    'employment_type' => 'CORE_EMPLOYEE',
                    'employment_start_date' => '2024-01-01',
                    'status' => 'ACTIVE',
                    'shift_pattern' => $pattern,
                ],
            );
        }
    }
}
