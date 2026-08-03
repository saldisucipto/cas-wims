<?php

namespace Database\Seeders;

use App\Models\Consumable;
use App\Models\ConsumableRequest;
use App\Models\DailyWorker;
use App\Models\PackingStation;
use App\Models\RfDevice;
use App\Models\User;
use App\Models\WorkingSession;
use App\Models\WmsAccount;
use Illuminate\Database\Seeder;

class WorkingSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workers = DailyWorker::query()->orderBy('name')->take(4)->get();
        $stations = PackingStation::query()->orderBy('code')->take(4)->get();
        $devices = RfDevice::query()->orderBy('code')->take(4)->get();
        $accounts = WmsAccount::query()->where('status', 'Available')->orderBy('username')->take(8)->get()->values();
        $consumables = Consumable::query()->orderBy('name')->take(6)->get()->values();
        $leader = User::query()->where('role', 'Leader')->first();

        foreach ($workers as $index => $worker) {
            $account = $accounts->get($index);

            $packingSession = WorkingSession::query()->create([
                'daily_worker_id' => $worker->id,
                'packing_station_id' => $stations->get($index)?->id,
                'rf_device_id' => null,
                'wms_account_id' => $account?->id,
                'session_type' => 'packing',
                'status' => 'Working',
                'started_at' => now()->subHours(1)->subMinutes($index * 5),
            ]);

            if ($account) {
                $account->update(['status' => 'Assigned']);
            }

            $pendingRequest = ConsumableRequest::query()->create([
                'request_number' => 'REQ-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'working_session_id' => $packingSession->id,
                'daily_worker_id' => $worker->id,
                'rf_device_id' => $devices->get($index)?->id,
                'notes' => 'Need consumables for outbound packing tasks.',
                'status' => 'Pending',
                'requested_at' => now()->subMinutes(20 - ($index * 2)),
            ]);

            $pendingRequest->items()->create([
                'consumable_id' => $consumables->get($index)?->id,
                'quantity' => 2,
            ]);

            $pendingRequest->items()->create([
                'consumable_id' => $consumables->get($index + 1)?->id ?? $consumables->first()->id,
                'quantity' => 1,
            ]);
        }

        $validatedRequest = ConsumableRequest::query()->create([
            'request_number' => 'REQ-900',
            'daily_worker_id' => $workers->first()?->id,
            'status' => 'Validated',
            'requested_at' => now()->subHours(3),
            'validated_at' => now()->subHours(2),
            'validated_by' => $leader?->id,
        ]);

        if ($validatedRequest && $consumables->isNotEmpty()) {
            $validatedRequest->items()->create([
                'consumable_id' => $consumables->first()->id,
                'quantity' => 1,
            ]);
        }

        $rejectedRequest = ConsumableRequest::query()->create([
            'request_number' => 'REQ-901',
            'daily_worker_id' => $workers->last()?->id,
            'status' => 'Rejected',
            'requested_at' => now()->subHours(4),
            'rejected_at' => now()->subHours(2),
            'rejected_by' => $leader?->id,
        ]);

        if ($rejectedRequest && $consumables->count() > 1) {
            $rejectedRequest->items()->create([
                'consumable_id' => $consumables->get(1)->id,
                'quantity' => 3,
            ]);
        }
    }
}
