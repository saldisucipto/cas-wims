<?php

namespace Database\Seeders;

use App\Models\Consumable;
use Illuminate\Database\Seeder;

class ConsumableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['name' => 'Bubble Wrap', 'unit' => 'Roll', 'stock' => 0],
            ['name' => 'Lakban Bening', 'unit' => 'Roll', 'stock' => 0],
            ['name' => 'Lakban Fragile', 'unit' => 'Roll', 'stock' => 0],
            ['name' => 'Poly Mailer 30 x 40 CM', 'unit' => 'Pack', 'stock' => 0],
            ['name' => 'Poly Mailer 20 X 30 CM', 'unit' => 'Pack', 'stock' => 0],
            ['name' => 'Poly Mailer 50 X 60 CM', 'unit' => 'Pack', 'stock' => 0],
        ];

        foreach ($items as $item) {
            Consumable::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'unit' => $item['unit'],
                    'stock' => $item['stock'],
                ]
            );
        }
    }
}
