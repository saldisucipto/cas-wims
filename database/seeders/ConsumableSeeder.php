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
            ['name' => 'Bubble Wrap', 'unit' => 'Roll', 'stock' => 120],
            ['name' => 'Lakban Bening', 'unit' => 'Roll', 'stock' => 200],
            ['name' => 'Lakban Fragile', 'unit' => 'Roll', 'stock' => 140],
            ['name' => 'Stretch Film', 'unit' => 'Roll', 'stock' => 90],
            ['name' => 'Poly Mailer', 'unit' => 'Pack', 'stock' => 160],
            ['name' => 'Label A6', 'unit' => 'Pack', 'stock' => 240],
            ['name' => 'Label A7', 'unit' => 'Pack', 'stock' => 210],
            ['name' => 'Cutter', 'unit' => 'Pcs', 'stock' => 60],
            ['name' => 'Marker Permanent', 'unit' => 'Pcs', 'stock' => 85],
            ['name' => 'Cable Tie', 'unit' => 'Pack', 'stock' => 180],
            ['name' => 'Carton Box S', 'unit' => 'Pcs', 'stock' => 130],
            ['name' => 'Carton Box M', 'unit' => 'Pcs', 'stock' => 125],
            ['name' => 'Carton Box L', 'unit' => 'Pcs', 'stock' => 110],
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
