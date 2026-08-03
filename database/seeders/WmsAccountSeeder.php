<?php

namespace Database\Seeders;

use App\Models\WmsAccount;
use Illuminate\Database\Seeder;

class WmsAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($index = 1; $index <= 100; $index++) {
            $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);

            WmsAccount::query()->updateOrCreate(
                ['username' => 'PACK' . $number],
                [
                    'password' => '123456',
                    'function' => 'Outbound',
                    'status' => 'Available',
                ]
            );
        }
    }
}
