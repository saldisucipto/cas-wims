<?php

namespace Database\Seeders;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkStockTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AtkActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrator = User::query()->where('role', 'Administrator')->orderBy('id')->first();
        $leader = User::query()->where('role', 'Leader')->orderBy('id')->first();
        $items = AtkItem::query()->orderBy('name')->get()->keyBy('name');

        if (! $administrator || ! $leader || $items->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($administrator, $leader, $items): void {
            $receivings = [
                ['name' => 'Pulpen', 'supplier' => 'CV Sinar Kantor', 'qty' => 40, 'days' => 7, 'number' => 'ATK-IN-001'],
                ['name' => 'Kertas A4', 'supplier' => 'PT Kertas Nusantara', 'qty' => 10, 'days' => 5, 'number' => 'ATK-IN-002'],
                ['name' => 'Sticky Notes', 'supplier' => 'UD Alat Tulis Maju', 'qty' => 8, 'days' => 3, 'number' => 'ATK-IN-003'],
            ];

            foreach ($receivings as $receiving) {
                $item = $items->get($receiving['name']);

                if (! $item) {
                    continue;
                }

                $exists = AtkStockTransaction::query()->where('transaction_number', $receiving['number'])->exists();

                if ($exists) {
                    continue;
                }

                $balance = $item->current_stock + $receiving['qty'];

                $item->update([
                    'current_stock' => $balance,
                ]);

                AtkStockTransaction::query()->create([
                    'atk_item_id' => $item->id,
                    'transaction_number' => $receiving['number'],
                    'transaction_type' => 'Receiving',
                    'reference' => $receiving['number'],
                    'supplier' => $receiving['supplier'],
                    'quantity_in' => $receiving['qty'],
                    'quantity_out' => 0,
                    'balance' => $balance,
                    'notes' => 'Initial ATK receiving history.',
                    'performed_by' => $administrator->id,
                    'transaction_at' => now()->subDays($receiving['days']),
                ]);
            }

            $approvedRequest = AtkRequest::query()->firstOrCreate(
                ['request_number' => 'ATK-REQ-900'],
                [
                    'requested_by' => $leader->id,
                    'notes' => 'Need office supplies for daily coordination.',
                    'status' => 'Approved',
                    'requested_at' => now()->subDays(2),
                    'approved_at' => now()->subDays(2)->addHour(),
                    'approved_by' => $administrator->id,
                ]
            );

            if ($approvedRequest->items()->count() === 0 && $items->has('Pulpen') && $items->has('Buku Tulis')) {
                $approvedRequest->items()->create([
                    'atk_item_id' => $items->get('Pulpen')->id,
                    'quantity' => 5,
                ]);

                $approvedRequest->items()->create([
                    'atk_item_id' => $items->get('Buku Tulis')->id,
                    'quantity' => 2,
                ]);
            }

            foreach ($approvedRequest->items as $index => $approvedItem) {
                $transactionNumber = 'ATK-OUT-900-'.($index + 1);

                if (AtkStockTransaction::query()->where('transaction_number', $transactionNumber)->exists()) {
                    continue;
                }

                $item = AtkItem::query()->find($approvedItem->atk_item_id);

                if (! $item) {
                    continue;
                }

                $balance = max(0, $item->current_stock - $approvedItem->quantity);

                $item->update([
                    'current_stock' => $balance,
                ]);

                AtkStockTransaction::query()->create([
                    'atk_item_id' => $item->id,
                    'transaction_number' => $transactionNumber,
                    'transaction_type' => 'Approval',
                    'reference' => $approvedRequest->request_number,
                    'supplier' => null,
                    'quantity_in' => 0,
                    'quantity_out' => $approvedItem->quantity,
                    'balance' => $balance,
                    'notes' => 'Approved ATK request release.',
                    'performed_by' => $administrator->id,
                    'transaction_at' => now()->subDays(2)->addHour(),
                ]);
            }

            $rejectedRequest = AtkRequest::query()->firstOrCreate(
                ['request_number' => 'ATK-REQ-901'],
                [
                    'requested_by' => $administrator->id,
                    'notes' => 'Replacement sticky notes for admin desk.',
                    'status' => 'Rejected',
                    'requested_at' => now()->subDay(),
                    'rejected_at' => now()->subDay()->addMinutes(40),
                    'rejected_by' => $administrator->id,
                    'rejection_notes' => 'Use existing open pack first.',
                ]
            );

            if ($rejectedRequest->items()->count() === 0 && $items->has('Sticky Notes')) {
                $rejectedRequest->items()->create([
                    'atk_item_id' => $items->get('Sticky Notes')->id,
                    'quantity' => 3,
                ]);
            }

            $pendingRequest = AtkRequest::query()->firstOrCreate(
                ['request_number' => 'ATK-REQ-902'],
                [
                    'requested_by' => $leader->id,
                    'notes' => 'Weekly office supply refill.',
                    'status' => 'Pending',
                    'requested_at' => now()->subHours(3),
                ]
            );

            if ($pendingRequest->items()->count() === 0 && $items->has('Map Folder') && $items->has('Pensil')) {
                $pendingRequest->items()->create([
                    'atk_item_id' => $items->get('Map Folder')->id,
                    'quantity' => 4,
                ]);

                $pendingRequest->items()->create([
                    'atk_item_id' => $items->get('Pensil')->id,
                    'quantity' => 6,
                ]);
            }
        });
    }
}
