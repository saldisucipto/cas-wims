<?php

namespace Database\Seeders;

use App\Models\DailyWorker;
use Illuminate\Database\Seeder;

class DailyWorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $firstNames = [
        //     'Andi', 'Budi', 'Rizky', 'Dimas', 'Yusuf', 'Fajar', 'Agus', 'Rendi', 'Rudi', 'Rahmat',
        // ];

        // $lastNames = [
        //     'Pratama', 'Santoso', 'Maulana', 'Nugraha', 'Saputra', 'Firmansyah', 'Wijaya', 'Kurniawan', 'Setiawan', 'Hidayat',
        // ];

        // $workers = [];

        // $counter = 1;

        // foreach ($firstNames as $firstName) {
        //     foreach ($lastNames as $lastName) {
        //         $employeeCode = 'DW' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);

        //         $workers[] = [
        //             'employee_code' => $employeeCode,
        //             'name' => $firstName . ' ' . $lastName,
        //             'function' => 'Outbound',
        //             'division' => 'Packer',
        //             'position' => 'Packer',
        //             'status' => 'Active',
        //             'is_active' => true,
        //             'created_at' => now(),
        //             'updated_at' => now(),
        //         ];

        //         $counter++;
        //     }
        // }

        // DailyWorker::query()->upsert(array_slice($workers, 0, 50), ['name'], ['updated_at']);
    }
}
