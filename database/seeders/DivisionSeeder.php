<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['code' => 'INB', 'name' => 'Inbound'], ['code' => 'OB', 'name' => 'Outbound']] as $row) {
            Division::query()->updateOrCreate(['code' => $row['code']], $row);
        }
    }
}
