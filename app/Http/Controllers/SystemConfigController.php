<?php

namespace App\Http\Controllers;

use App\Models\ConsumableRequest;
use App\Models\ShiftDefinition;
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

        $keys = [
            'morning_shift_start', 'morning_shift_end', 'night_shift_start', 'night_shift_end',
            'shift_duration', 'non_productive_hours', 'effective_working_hours', 'max_weekly_hours',
            'bco_order_release_cutoff', 'bco_picking_cutoff', 'bco_packing_cutoff',
            'bco_qc_cutoff', 'bco_ready_to_ship_cutoff', 'bco_expedition_handover_cutoff',
        ];

        return view('administration.system.shift-settings', [
            'settings' => SystemSetting::query()->whereIn('setting_key', $keys)->pluck('setting_value', 'setting_key'),
            'definitions' => ShiftDefinition::query()->orderBy('sort_order')->orderBy('code')->get(),
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
            'shift_duration' => ['required', 'numeric', 'min:1', 'max:24'],
            'non_productive_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'effective_working_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'max_weekly_hours' => ['required', 'numeric', 'min:1', 'max:168'],
            'bco_order_release_cutoff' => ['nullable', 'date_format:H:i'],
            'bco_picking_cutoff' => ['nullable', 'date_format:H:i'],
            'bco_packing_cutoff' => ['nullable', 'date_format:H:i'],
            'bco_qc_cutoff' => ['nullable', 'date_format:H:i'],
            'bco_ready_to_ship_cutoff' => ['nullable', 'date_format:H:i'],
            'bco_expedition_handover_cutoff' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }

        return back()->with('success', 'Shift settings saved.');
    }

    public function saveShiftDefinitions(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'start_time' => ['required', 'array'],
            'start_time.*' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'array'],
            'end_time.*' => ['required', 'date_format:H:i'],
            'break_start' => ['nullable', 'array'],
            'break_start.*' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'array'],
            'break_end.*' => ['nullable', 'date_format:H:i'],
        ]);

        foreach ($data['start_time'] as $id => $startTime) {
            $definition = ShiftDefinition::query()->find($id);

            if (! $definition) {
                continue;
            }

            $endTime = $data['end_time'][$id];
            $breakStart = $data['break_start'][$id] ?? null;
            $breakEnd = $data['break_end'][$id] ?? null;

            $startMinutes = $this->minutesOfDay($startTime);
            $endMinutes = $this->minutesOfDay($endTime);
            $breakStartMinutes = $breakStart ? $this->minutesOfDay($breakStart) : null;
            $breakEndMinutes = $breakEnd ? $this->minutesOfDay($breakEnd) : null;

            $breakMinutes = ($breakStartMinutes !== null && $breakEndMinutes !== null)
                ? max(0, $breakEndMinutes - $breakStartMinutes)
                : 0;

            $effectiveHours = max(0, round(($endMinutes - $startMinutes - $breakMinutes) / 60, 2));

            $definition->update([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'break_start' => $breakStart ?: null,
                'break_end' => $breakEnd ?: null,
                'break_minutes' => $breakMinutes,
                'effective_hours' => $effectiveHours,
            ]);
        }

        return back()->with('success', 'Shift definitions saved.');
    }

    private function minutesOfDay(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    public function activityLogs()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $stockLogs = StockTransaction::query()->with('consumable')->latest('transaction_at')->take(30)->get()->map(function ($log) {
            return [
                'time' => $log->transaction_at,
                'type' => 'Stock '.$log->transaction_type,
                'description' => ($log->consumable?->name ?? '-').' ('.$log->quantity_before.' -> '.$log->quantity_after.')',
            ];
        });

        $requestLogs = ConsumableRequest::query()->with('dailyWorker')->latest('updated_at')->take(30)->get()->map(function ($log) {
            return [
                'time' => $log->updated_at,
                'type' => 'Consumable Request '.$log->status,
                'description' => ($log->dailyWorker?->name ?? '-').' - '.$log->request_number,
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
                    'description' => ($log->forceCloser?->name ?? 'Administrator').' -> '.($log->dailyWorker?->name ?? '-').' ('.$resource.')',
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
