<?php

namespace App\Http\Controllers;

use App\Models\ConsumableRequest;
use App\Models\StockTransaction;
use App\Models\SystemSetting;
use App\Models\WorkingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemConfigController extends Controller
{
    public function warehouseSettings()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $keys = ['warehouse_name', 'warehouse_code', 'warehouse_location'];

        return view('administration.system.warehouse-settings', [
            'settings' => SystemSetting::query()->whereIn('setting_key', $keys)->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function saveWarehouseSettings(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'warehouse_name' => ['required', 'string', 'max:255'],
            'warehouse_code' => ['required', 'string', 'max:255'],
            'warehouse_location' => ['required', 'string', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }

        return back()->with('success', 'Warehouse settings saved.');
    }

    public function shiftSettings()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $keys = ['morning_shift_start', 'morning_shift_end', 'night_shift_start', 'night_shift_end'];

        return view('administration.system.shift-settings', [
            'settings' => SystemSetting::query()->whereIn('setting_key', $keys)->pluck('setting_value', 'setting_key'),
        ]);
    }

    public function saveShiftSettings(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'morning_shift_start' => ['required', 'string', 'max:10'],
            'morning_shift_end' => ['required', 'string', 'max:10'],
            'night_shift_start' => ['required', 'string', 'max:10'],
            'night_shift_end' => ['required', 'string', 'max:10'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }

        return back()->with('success', 'Shift settings saved.');
    }

    public function activityLogs()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $stockLogs = StockTransaction::query()->with('consumable')->latest('transaction_at')->take(30)->get()->map(function ($log) {
            return [
                'time' => $log->transaction_at,
                'type' => 'Stock ' . $log->transaction_type,
                'description' => ($log->consumable?->name ?? '-') . ' (' . $log->quantity_before . ' -> ' . $log->quantity_after . ')',
            ];
        });

        $requestLogs = ConsumableRequest::query()->with('dailyWorker')->latest('updated_at')->take(30)->get()->map(function ($log) {
            return [
                'time' => $log->updated_at,
                'type' => 'Consumable Request ' . $log->status,
                'description' => ($log->dailyWorker?->name ?? '-') . ' - ' . $log->request_number,
            ];
        });

        $forceCloseLogs = WorkingSession::query()
            ->with(['forceCloser', 'dailyWorker', 'rfDevice', 'packingStation'])
            ->where('close_type', 'Force Close')
            ->whereNotNull('force_closed_at')
            ->latest('force_closed_at')
            ->take(30)
            ->get()
            ->map(function ($log) {
                $resource = $log->rfDevice?->code ?? ($log->packingStation?->name ?? '-');

                return [
                    'time' => $log->force_closed_at,
                    'type' => 'Force Closed Working Session',
                    'description' => ($log->forceCloser?->name ?? 'Administrator') . ' -> ' . ($log->dailyWorker?->name ?? '-') . ' (' . $resource . ')',
                ];
            });

        $logs = $stockLogs->concat($requestLogs)->concat($forceCloseLogs)->sortByDesc('time')->values()->take(50);

        return view('administration.system.activity-logs', [
            'logs' => $logs,
        ]);
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
