<?php

namespace Database\Seeders;

use App\Models\AtkItem;
use Illuminate\Database\Seeder;

class AtkItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['code' => 'ATK-001', 'name' => 'Pulpen', 'category' => 'Writing', 'unit' => 'Pcs', 'minimum_stock' => 20, 'current_stock' => 120, 'status' => 'Active', 'notes' => 'Pulpen tinta hitam.'],
            ['code' => 'ATK-002', 'name' => 'Pensil', 'category' => 'Writing', 'unit' => 'Pcs', 'minimum_stock' => 20, 'current_stock' => 90, 'status' => 'Active', 'notes' => 'Pensil HB.'],
            ['code' => 'ATK-003', 'name' => 'Spidol', 'category' => 'Writing', 'unit' => 'Pcs', 'minimum_stock' => 12, 'current_stock' => 45, 'status' => 'Active', 'notes' => 'Spidol untuk whiteboard.'],
            ['code' => 'ATK-004', 'name' => 'Buku Tulis', 'category' => 'Paper', 'unit' => 'Pcs', 'minimum_stock' => 15, 'current_stock' => 70, 'status' => 'Active', 'notes' => 'Buku catatan harian.'],
            ['code' => 'ATK-005', 'name' => 'Kertas A4', 'category' => 'Paper', 'unit' => 'Rim', 'minimum_stock' => 10, 'current_stock' => 40, 'status' => 'Active', 'notes' => 'Kertas printer ukuran A4.'],
            ['code' => 'ATK-006', 'name' => 'Kertas A5', 'category' => 'Paper', 'unit' => 'Rim', 'minimum_stock' => 8, 'current_stock' => 24, 'status' => 'Active', 'notes' => 'Kertas ukuran A5.'],
            ['code' => 'ATK-007', 'name' => 'Sticky Notes', 'category' => 'Paper', 'unit' => 'Pack', 'minimum_stock' => 10, 'current_stock' => 35, 'status' => 'Active', 'notes' => 'Memo tempel warna campur.'],
            ['code' => 'ATK-008', 'name' => 'Stapler', 'category' => 'Desk Tools', 'unit' => 'Pcs', 'minimum_stock' => 5, 'current_stock' => 12, 'status' => 'Active', 'notes' => 'Stapler meja sedang.'],
            ['code' => 'ATK-009', 'name' => 'Isi Stapler', 'category' => 'Desk Tools', 'unit' => 'Box', 'minimum_stock' => 10, 'current_stock' => 28, 'status' => 'Active', 'notes' => 'Isi stapler ukuran standar.'],
            ['code' => 'ATK-010', 'name' => 'Map Folder', 'category' => 'Filing', 'unit' => 'Pcs', 'minimum_stock' => 15, 'current_stock' => 55, 'status' => 'Active', 'notes' => 'Map arsip plastik.'],
            ['code' => 'ATK-011', 'name' => 'Cutter', 'category' => 'Desk Tools', 'unit' => 'Pcs', 'minimum_stock' => 6, 'current_stock' => 16, 'status' => 'Active', 'notes' => 'Cutter sedang.'],
            ['code' => 'ATK-012', 'name' => 'Penggaris', 'category' => 'Desk Tools', 'unit' => 'Pcs', 'minimum_stock' => 6, 'current_stock' => 18, 'status' => 'Active', 'notes' => 'Penggaris 30 cm.'],
        ];

        foreach ($items as $item) {
            AtkItem::query()->updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
