<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::query()->updateOrCreate(['code' => 'WH-OPS'], ['code' => 'WH-OPS', 'name' => 'Warehouse Operations']);
    }
}
