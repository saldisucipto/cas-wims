<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\ConsumableRequest;
use App\Models\DailyWorker;
use App\Models\PackingStation;
use App\Models\RfDevice;
use App\Models\WorkingSession;
use App\Models\WmsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PackingController extends Controller
{
    public function registration()
    {
        return view('packing.registration', [
            'employees' => DailyWorker::query()
                ->where('is_active', true)
                ->whereDoesntHave('workingSessions', function ($query) {
                    $query->where('status', 'Working');
                })
                ->orderBy('name')
                ->get(['name', 'employee_code', 'function', 'position']),
        ]);
    }

    public function dashboard(Request $request)
    {
        $worker = $this->resolveWorker($request->query('name'));

        if (! $worker) {
            return redirect()->route('packing.registration')->with('error', 'Please select a valid employee first.');
        }

        if ($request->boolean('finish')) {
            try {
                $this->finishPackingSession($worker);

                return redirect()->route('packing.registration')->with('success', 'Packing session finished and resources released.');
            } catch (RuntimeException $exception) {
                return redirect()->route('packing.registration')->with('error', $exception->getMessage());
            }
        }

        try {
            $session = $this->resolveOrCreatePackingSession($worker);
        } catch (RuntimeException $exception) {
            return redirect()->route('packing.registration')->with('error', $exception->getMessage());
        }

        $latestRequest = ConsumableRequest::query()
            ->where('daily_worker_id', $worker->id)
            ->latest('requested_at')
            ->first();

        return view('packing.dashboard', [
            'employeeName' => $worker->name,
            'packingStationName' => $session->packingStation?->name ?? '-',
            'wmsUsername' => $session->wmsAccount?->username ?? '-',
            'wmsPassword' => $session->wmsAccount?->password ?? '-',
            'sessionStartedAt' => $session->started_at?->toIso8601String(),
            'latestRequestStatus' => $latestRequest?->status ?? 'No Request',
        ]);
    }

    public function requestConsumable(Request $request)
    {
        $worker = $this->resolveWorker($request->query('name'));

        if (! $worker) {
            return redirect()->route('packing.registration')->with('error', 'Please select a valid employee first.');
        }

        return view('packing.request-consumable', [
            'employeeName' => $worker->name,
            'consumableItems' => Consumable::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    public function submitConsumableRequest(Request $request)
    {
        $payload = $request->validate([
            'employeeName' => ['required', 'string'],
            'rfDevice' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', 'exists:consumables,name'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $worker = DailyWorker::query()->where('name', $payload['employeeName'])->first();

        if (! $worker) {
            return response()->json(['message' => 'Employee not found.'], 422);
        }

        $session = WorkingSession::query()
            ->where('daily_worker_id', $worker->id)
            ->where('session_type', 'packing')
            ->where('status', 'Working')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return response()->json(['message' => 'No active packing session found. Please start working again.'], 422);
        }

        $rfDevice = null;

        if (! empty($payload['rfDevice'])) {
            $rfDevice = RfDevice::query()->where('code', $payload['rfDevice'])->first();
        }

        $nextNumber = (ConsumableRequest::max('id') ?? 0) + 1;
        $requestNumber = 'REQ-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        $consumableRequest = ConsumableRequest::create([
            'request_number' => $requestNumber,
            'working_session_id' => $session->id,
            'daily_worker_id' => $worker->id,
            'rf_device_id' => $rfDevice?->id,
            'notes' => $payload['notes'] ?? null,
            'status' => 'Pending',
            'requested_at' => now(),
        ]);

        $consumables = Consumable::query()
            ->whereIn('name', collect($payload['items'])->pluck('item')->all())
            ->get()
            ->keyBy('name');

        foreach ($payload['items'] as $item) {
            $consumable = $consumables->get($item['item']);

            if (! $consumable) {
                continue;
            }

            $consumableRequest->items()->create([
                'consumable_id' => $consumable->id,
                'quantity' => (int) $item['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Consumable Request Submitted',
            'requestNumber' => $requestNumber,
        ]);
    }

    public function waitingLeaderValidation(Request $request)
    {
        $workerName = $request->query('name');

        $pendingRequest = ConsumableRequest::query()
            ->with('items.consumable')
            ->when($workerName, function ($query, $name) {
                $query->whereHas('dailyWorker', function ($workerQuery) use ($name) {
                    $workerQuery->where('name', $name);
                });
            })
            ->where('status', 'Pending')
            ->latest('requested_at')
            ->first();

        return view('packing.waiting-leader-validation', [
            'requestItems' => $pendingRequest?->items ?? collect(),
        ]);
    }

    private function resolveWorker(?string $workerName): ?DailyWorker
    {
        if (! $workerName) {
            return null;
        }

        return DailyWorker::query()
            ->where('is_active', true)
            ->where('name', $workerName)
            ->first();
    }

    private function resolveOrCreatePackingSession(DailyWorker $worker): WorkingSession
    {
        return DB::transaction(function () use ($worker) {
            $existingSession = WorkingSession::query()
                ->with(['wmsAccount', 'packingStation'])
                ->where('daily_worker_id', $worker->id)
                ->where('session_type', 'packing')
                ->where('status', 'Working')
                ->latest('started_at')
                ->first();

            if ($existingSession) {
                return $existingSession;
            }

            $workerHasActiveSession = WorkingSession::query()
                ->lockForUpdate()
                ->where('daily_worker_id', $worker->id)
                ->where('status', 'Working')
                ->exists();

            if ($workerHasActiveSession) {
                throw new RuntimeException('Karyawan masih memiliki sesi kerja yang aktif. Silakan selesaikan sesi sebelumnya terlebih dahulu.');
            }

            $station = PackingStation::query()
                ->lockForUpdate()
                ->whereIn('status', ['Available', 'Active'])
                ->whereDoesntHave('workingSessions', function ($query) {
                    $query->where('status', 'Working');
                })
                ->orderBy('id')
                ->first();

            if (! $station) {
                throw new RuntimeException('This Packing Station is already occupied.');
            }

            $wmsAccount = null;

            if ($station->wms_account_id) {
                $wmsAccount = WmsAccount::query()->lockForUpdate()->find($station->wms_account_id);

                if (! $wmsAccount || $wmsAccount->status !== 'Available') {
                    throw new RuntimeException('Assigned WMS Account for this Packing Station is not available.');
                }

                $wmsUsed = WorkingSession::query()
                    ->lockForUpdate()
                    ->where('wms_account_id', $wmsAccount->id)
                    ->where('status', 'Working')
                    ->exists();

                if ($wmsUsed) {
                    throw new RuntimeException('This WMS Account is currently assigned to another employee.');
                }
            } else {
                $wmsAccount = WmsAccount::query()
                    ->lockForUpdate()
                    ->where('status', 'Available')
                    ->whereDoesntHave('workingSessions', function ($query) {
                        $query->where('status', 'Working');
                    })
                    ->orderBy('id')
                    ->first();

                if (! $wmsAccount) {
                    throw new RuntimeException('No available WMS Account found for this session.');
                }
            }

            $session = WorkingSession::query()->create([
                'daily_worker_id' => $worker->id,
                'packing_station_id' => $station->id,
                'rf_device_id' => null,
                'wms_account_id' => $wmsAccount->id,
                'session_type' => 'packing',
                'status' => 'Working',
                'started_at' => now(),
            ]);

            $station->update(['status' => 'In Use']);
            $wmsAccount->update(['status' => 'Assigned']);

            return $session->load(['wmsAccount', 'packingStation']);
        });
    }

    private function finishPackingSession(DailyWorker $worker): void
    {
        DB::transaction(function () use ($worker) {
            $session = WorkingSession::query()
                ->lockForUpdate()
                ->where('daily_worker_id', $worker->id)
                ->where('session_type', 'packing')
                ->where('status', 'Working')
                ->latest('started_at')
                ->first();

            if (! $session) {
                throw new RuntimeException('No active Packing session found.');
            }

            $session->update([
                'status' => 'Finished',
                'ended_at' => now(),
            ]);

            if ($session->packing_station_id) {
                $station = PackingStation::query()->lockForUpdate()->find($session->packing_station_id);

                if ($station) {
                    $station->update(['status' => 'Available']);
                }
            }

            if ($session->wms_account_id) {
                $wmsAccount = WmsAccount::query()->lockForUpdate()->find($session->wms_account_id);

                if ($wmsAccount) {
                    $wmsAccount->update(['status' => 'Available']);
                }
            }
        });
    }
}
