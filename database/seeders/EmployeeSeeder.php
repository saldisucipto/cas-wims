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

        $picker = Position::query()->where('code', 'PICKER')->first();
        $checkPack = Position::query()->where('code', 'CHECKPACK')->first();
        $dispatch = Position::query()->where('code', 'DISPATCH')->first();
        $handover = Position::query()->where('code', 'HANDOVER')->first();
        $receiving = Position::query()->where('code', 'RECEIVE')->first();

        $seed = [
            ['EMP-001', 'Andi Pratama', $picker, 'ROTATING'],
            ['EMP-002', 'Budi Santoso', $picker, 'ROTATING'],
            ['EMP-003', 'Citra Maulana', $picker, 'ROTATING'],
            ['EMP-004', 'Dewi Nugraha', $picker, 'ROTATING'],
            ['EMP-005', 'Eko Saputra', $checkPack, 'ROTATING'],
            ['EMP-006', 'Fitri Firmansyah', $checkPack, 'ROTATING'],
            ['EMP-007', 'Gilang Wijaya', $checkPack, 'FIXED_S1'],
            ['EMP-008', 'Hana Kurniawan', $checkPack, 'FIXED_S2'],
            ['EMP-009', 'Indra Setiawan', $dispatch, 'FIXED_S2'],
            ['EMP-010', 'Joko Hidayat', $handover, 'FIXED_S2'],
            ['EMP-011', 'Kartika Putri', $receiving, 'ROTATING'],
            ['EMP-012', 'Lina Rahmawati', $receiving, 'FIXED_S1'],
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
