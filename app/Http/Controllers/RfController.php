<?php

namespace App\Http\Controllers;

use App\Models\DailyWorker;
use App\Models\RfDevice;
use App\Models\WorkingSession;
use App\Models\WmsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RfController extends Controller
{
    public function registration()
    {
        return view('rf.registration', [
            'employees' => DailyWorker::query()
                ->where('is_active', true)
                ->whereDoesntHave('workingSessions', function ($query) {
                    $query->where('status', 'Working');
                })
                ->orderBy('name')
                ->get(['name', 'employee_code', 'function', 'position']),
            'devices' => RfDevice::query()
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        ]);
    }

    public function dashboard(Request $request)
    {
        $worker = $this->resolveWorker($request->query('name'));

        if (! $worker) {
            return redirect()->route('rf.registration')->with('error', 'Please select a valid employee first.');
        }

        $device = $this->resolveDevice($request->query('device'));

        if (! $device) {
            return redirect()->route('rf.registration')->with('error', 'Please select a valid RF Device first.');
        }

        if ($request->boolean('finish')) {
            try {
                $this->finishRfSession($worker);

                return redirect()->route('rf.registration')->with('success', 'RF session finished and resources released.');
            } catch (RuntimeException $exception) {
                return redirect()->route('rf.registration')->with('error', $exception->getMessage());
            }
        }

        try {
            $session = $this->resolveOrCreateRfSession($worker, $device);
        } catch (RuntimeException $exception) {
            return redirect()->route('rf.registration')->with('error', $exception->getMessage());
        }

        return view('rf.dashboard', [
            'employeeName' => $worker->name,
            'deviceName' => $device->code,
            'wmsUsername' => $session->wmsAccount?->username ?? '-',
            'wmsPassword' => $session->wmsAccount?->password ?? '-',
            'sessionStartedAt' => $session->started_at?->toIso8601String(),
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

    private function resolveDevice(?string $deviceCode): ?RfDevice
    {
        if (! $deviceCode) {
            return null;
        }

        return RfDevice::query()->where('code', $deviceCode)->first();
    }

    private function resolveOrCreateRfSession(DailyWorker $worker, ?RfDevice $device): WorkingSession
    {
        if (! $device) {
            throw new RuntimeException('Please select a valid RF Device first.');
        }

        return DB::transaction(function () use ($worker, $device) {
            $existingSession = WorkingSession::query()
                ->with(['wmsAccount', 'rfDevice'])
                ->where('daily_worker_id', $worker->id)
                ->where('session_type', 'rf')
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

            $lockedDevice = RfDevice::query()->lockForUpdate()->find($device->id);

            if (! $lockedDevice) {
                throw new RuntimeException('Selected RF Device was not found.');
            }

            if ($lockedDevice->status !== 'Available') {
                throw new RuntimeException('This RF Device is currently being used by another employee.');
            }

            $deviceUsed = WorkingSession::query()
                ->lockForUpdate()
                ->where('rf_device_id', $lockedDevice->id)
                ->where('status', 'Working')
                ->exists();

            if ($deviceUsed) {
                throw new RuntimeException('This RF Device is currently being used by another employee.');
            }

            $wmsAccount = null;

            if ($lockedDevice->wms_account_id) {
                $wmsAccount = WmsAccount::query()->lockForUpdate()->find($lockedDevice->wms_account_id);

                if (! $wmsAccount || $wmsAccount->status !== 'Available') {
                    throw new RuntimeException('Assigned WMS Account for this RF Device is not available.');
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
                'packing_station_id' => null,
                'rf_device_id' => $lockedDevice->id,
                'wms_account_id' => $wmsAccount->id,
                'session_type' => 'rf',
                'status' => 'Working',
                'started_at' => now(),
            ]);

            $lockedDevice->update(['status' => 'In Use']);
            $wmsAccount->update(['status' => 'Assigned']);

            return $session->load('wmsAccount');
        });
    }

    private function finishRfSession(DailyWorker $worker): void
    {
        DB::transaction(function () use ($worker) {
            $session = WorkingSession::query()
                ->lockForUpdate()
                ->where('daily_worker_id', $worker->id)
                ->where('session_type', 'rf')
                ->where('status', 'Working')
                ->latest('started_at')
                ->first();

            if (! $session) {
                throw new RuntimeException('No active RF session found.');
            }

            $session->update([
                'status' => 'Finished',
                'ended_at' => now(),
            ]);

            if ($session->rf_device_id) {
                $device = RfDevice::query()->lockForUpdate()->find($session->rf_device_id);

                if ($device) {
                    $device->update(['status' => 'Available']);
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
