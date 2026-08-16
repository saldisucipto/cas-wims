<?php

namespace App\Http\Controllers;

use App\Models\DeviceAvailability;
use App\Models\ManpowerActivity;
use App\Models\ManpowerDivisionRule;
use App\Models\ManpowerPlanning;
use App\Models\ManpowerVasSchedule;
use App\Models\SystemSetting;
use App\Services\ManpowerPlanning\ManpowerPlanningEngine;
use App\Services\ManpowerPlanning\PlanningInput;
use App\Services\ManpowerPlanning\PlanningResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ManpowerPlanningController extends Controller
{
    private const DIVISION_ORDER = ['Inbound', 'Outbound'];

    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $date = $request->string('date')->toString() ?: now()->toDateString();
        $config = $this->resolveConfig();

        $inboundVolume = max(0, (int) $request->input('inbound_volume', 0));
        $outboundVolume = max(0, (int) $request->input('outbound_volume', 0));
        $vasVolume = $this->resolveVasVolume($date);

        $result = null;

        if ($request->has('inbound_volume') || $request->has('outbound_volume')) {
            $result = $this->calculate($inboundVolume, $outboundVolume, $vasVolume, $config['effective_hours']);
        }

        return view('administration.manpower-planning.index', [
            'result' => $result,
            'inboundVolume' => $inboundVolume,
            'outboundVolume' => $outboundVolume,
            'vasVolume' => $vasVolume,
            'date' => $date,
            'config' => $config,
        ]);
    }

    public function store(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $this->validatePlanning($request);
        $config = $this->resolveConfig();
        $vasVolume = $this->resolveVasVolume($data['planning_date']);

        $result = $this->calculate((int) $data['inbound_volume'], (int) $data['outbound_volume'], $vasVolume, $config['effective_hours']);

        $planning = $this->persistPlanning($result, $data, $config, $vasVolume, null);

        return redirect()
            ->route('administration.manpower-planning.show', $planning)
            ->with('success', 'Planning tersimpan dengan nomor '.$planning->planning_number.'.');
    }

    public function history(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = ManpowerPlanning::query()->with('creator')->latest('planning_date')->latest('id');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($q) {
                $builder->where('planning_number', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($date = $request->string('date')->toString()) {
            $query->whereDate('planning_date', $date);
        }

        return view('administration.manpower-planning.history', [
            'rows' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'date' => $request->string('date')->toString(),
            ],
        ]);
    }

    public function show(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerPlanning->load(['items' => fn ($query) => $query->orderBy('sort_order'), 'devices', 'creator', 'updater']);

        return view('administration.manpower-planning.show', [
            'planning' => $manpowerPlanning,
            'divisions' => $this->planningSummary($manpowerPlanning),
        ]);
    }

    public function edit(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if (in_array($manpowerPlanning->status, ['FINAL', 'CANCELLED'], true)) {
            return redirect()
                ->route('administration.manpower-planning.show', $manpowerPlanning)
                ->with('error', 'Planning dengan status '.$manpowerPlanning->status.' tidak dapat diedit. Duplicate untuk membuat versi baru.');
        }

        return view('administration.manpower-planning.edit', [
            'planning' => $manpowerPlanning,
            'config' => $this->resolveConfig(),
        ]);
    }

    public function update(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if (in_array($manpowerPlanning->status, ['FINAL', 'CANCELLED'], true)) {
            return redirect()
                ->route('administration.manpower-planning.show', $manpowerPlanning)
                ->with('error', 'Planning dengan status '.$manpowerPlanning->status.' tidak dapat diedit.');
        }

        $data = $this->validatePlanning($request);
        $config = $this->resolveConfig();
        $vasVolume = $this->resolveVasVolume($data['planning_date']);

        $result = $this->calculate((int) $data['inbound_volume'], (int) $data['outbound_volume'], $vasVolume, $config['effective_hours']);

        $planning = $this->persistPlanning($result, $data, $config, $vasVolume, $manpowerPlanning);

        return redirect()
            ->route('administration.manpower-planning.show', $planning)
            ->with('success', 'Planning diperbarui.');
    }

    public function duplicate(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $duplicate = $manpowerPlanning->replicate(['planning_number', 'status', 'revision', 'created_by', 'updated_by', 'created_at', 'updated_at']);
        $duplicate->planning_number = $this->generatePlanningNumber(now()->toDateString());
        $duplicate->planning_date = now()->toDateString();
        $duplicate->status = 'DRAFT';
        $duplicate->revision = 1;
        $duplicate->created_by = Auth::id();
        $duplicate->save();

        $duplicate->items()->createMany(
            $manpowerPlanning->items
                ->map(fn ($item) => $item->replicate(['manpower_planning_id', 'created_at', 'updated_at'])->toArray())
                ->all()
        );

        return redirect()
            ->route('administration.manpower-planning.edit', $duplicate)
            ->with('success', 'Planning diduplikasi. Ubah tanggal/volume lalu simpan.');
    }

    public function finalize(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerPlanning->update(['status' => 'FINAL', 'updated_by' => Auth::id()]);

        return back()->with('success', 'Planning ditandai FINAL.');
    }

    public function cancel(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerPlanning->update(['status' => 'CANCELLED', 'updated_by' => Auth::id()]);

        return back()->with('success', 'Planning dibatalkan.');
    }

    public function print(Request $request, ManpowerPlanning $manpowerPlanning)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerPlanning->load(['items' => fn ($query) => $query->orderBy('sort_order'), 'devices', 'creator', 'updater']);

        return view('administration.manpower-planning.print', [
            'planning' => $manpowerPlanning,
            'divisions' => $this->planningSummary($manpowerPlanning),
            'printedBy' => Auth::user()?->name ?? '-',
        ]);
    }

    public function deviceAvailabilities(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.master.manpower-device-availabilities', [
            'rows' => DeviceAvailability::query()->orderBy('device_type')->get(),
        ]);
    }

    public function storeDeviceAvailability(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'device_type' => ['required', 'string', 'max:50', 'unique:device_availabilities,device_type'],
            'ready_quantity' => ['required', 'integer', 'min:0'],
        ]);

        DeviceAvailability::query()->create($data);

        return back()->with('success', 'Device availability saved.');
    }

    public function updateDeviceAvailability(Request $request, DeviceAvailability $deviceAvailability)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'device_type' => ['required', 'string', 'max:50', 'unique:device_availabilities,device_type,'.$deviceAvailability->id],
            'ready_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $deviceAvailability->update($data);

        return back()->with('success', 'Device availability updated.');
    }

    public function destroyDeviceAvailability(Request $request, DeviceAvailability $deviceAvailability)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $deviceAvailability->delete();

        return back()->with('success', 'Device availability deleted.');
    }

    public function divisionRules(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.master.manpower-division-rules', [
            'rows' => ManpowerDivisionRule::query()->orderBy('division')->get(),
        ]);
    }

    public function storeDivisionRule(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'division' => ['required', 'string', 'max:255', 'unique:manpower_division_rules,division'],
            'minimum_shift' => ['required', 'integer', 'min:1', 'max:2'],
            'reason' => ['nullable', 'string'],
        ]);

        ManpowerDivisionRule::query()->create($data);

        return back()->with('success', 'Division rule saved.');
    }

    public function updateDivisionRule(Request $request, ManpowerDivisionRule $manpowerDivisionRule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'division' => ['required', 'string', 'max:255', 'unique:manpower_division_rules,division,'.$manpowerDivisionRule->id],
            'minimum_shift' => ['required', 'integer', 'min:1', 'max:2'],
            'reason' => ['nullable', 'string'],
        ]);

        $manpowerDivisionRule->update($data);

        return back()->with('success', 'Division rule updated.');
    }

    public function destroyDivisionRule(Request $request, ManpowerDivisionRule $manpowerDivisionRule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerDivisionRule->delete();

        return back()->with('success', 'Division rule deleted.');
    }

    public function activities(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = ManpowerActivity::query()->orderBy('sort_order')->orderBy('name');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('division', 'like', "%{$q}%");
            });
        }

        return view('administration.master.manpower-activities', [
            'rows' => $query->paginate(20)->withQueryString(),
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function storeActivity(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        ManpowerActivity::query()->create($this->validateActivity($request));

        return back()->with('success', 'Manpower activity created.');
    }

    public function updateActivity(Request $request, ManpowerActivity $manpowerActivity)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerActivity->update($this->validateActivity($request, $manpowerActivity->id));

        return back()->with('success', 'Manpower activity updated.');
    }

    public function destroyActivity(Request $request, ManpowerActivity $manpowerActivity)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerActivity->delete();

        return back()->with('success', 'Manpower activity deleted.');
    }

    public function vasSchedules(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.master.manpower-vas-schedules', [
            'rows' => ManpowerVasSchedule::query()->orderBy('schedule_date')->paginate(20)->withQueryString(),
        ]);
    }

    public function storeVasSchedule(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $this->validateVasSchedule($request);

        ManpowerVasSchedule::query()->updateOrCreate(
            ['schedule_date' => $data['schedule_date']],
            ['volume' => $data['volume']],
        );

        return back()->with('success', 'VAS schedule saved.');
    }

    public function updateVasSchedule(Request $request, ManpowerVasSchedule $manpowerVasSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerVasSchedule->update($this->validateVasSchedule($request, $manpowerVasSchedule->id));

        return back()->with('success', 'VAS schedule updated.');
    }

    public function destroyVasSchedule(Request $request, ManpowerVasSchedule $manpowerVasSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $manpowerVasSchedule->delete();

        return back()->with('success', 'VAS schedule deleted.');
    }

    private function calculate(int $inboundVolume, int $outboundVolume, int $vasVolume, float $effectiveHours): PlanningResult
    {
        $activities = ManpowerActivity::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ManpowerActivity $activity) => [
                'division' => $activity->division,
                'name' => $activity->name,
                'code' => $activity->code,
                'workload_source' => $activity->workload_source,
                'workload_unit' => $activity->workload_unit,
                'conversion_ratio' => $activity->conversion_ratio,
                'productivity_per_hour' => $activity->productivity_per_hour,
                'productivity_unit' => $activity->productivity_unit,
                'manpower_type' => $activity->manpower_type,
                'minimum_manpower' => $activity->minimum_manpower,
                'available_manpower' => $activity->available_manpower,
                'device_type' => $activity->device_type,
                'allowed_shifts' => $activity->allowed_shifts,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
            ])
            ->all();

        return (new ManpowerPlanningEngine)->run(new PlanningInput(
            inboundVolume: $inboundVolume,
            outboundVolume: $outboundVolume,
            vasVolume: $vasVolume,
            effectiveHours: $effectiveHours,
            activities: $activities,
            devices: $this->resolveDevices(),
            divisionRules: $this->resolveDivisionRules(),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{shift_duration: float, non_productive_hours: float, effective_hours: float}  $config
     */
    private function persistPlanning(PlanningResult $result, array $data, array $config, int $vasVolume, ?ManpowerPlanning $planning): ManpowerPlanning
    {
        $summary = $this->planningSummaryFromResult($result);

        $attributes = [
            'planning_date' => $data['planning_date'],
            'inbound_volume' => (int) $data['inbound_volume'],
            'outbound_volume' => (int) $data['outbound_volume'],
            'vas_volume' => $vasVolume,
            'shift_duration' => $config['shift_duration'],
            'non_productive_hours' => $config['non_productive_hours'],
            'effective_working_hours' => $config['effective_hours'],
            'total_mpp' => $summary['total_mpp'],
            'recommendation' => $summary['recommendation'],
            'overall_status' => $summary['overall_status'],
            'notes' => $data['notes'] ?? null,
        ];

        if (! $planning) {
            $attributes['planning_number'] = $this->generatePlanningNumber($data['planning_date']);
            $attributes['status'] = $data['status'] ?? 'CALCULATED';
            $attributes['revision'] = 1;
            $attributes['created_by'] = Auth::id();

            $planning = ManpowerPlanning::query()->create($attributes);
        } else {
            $attributes['revision'] = $planning->revision + 1;
            $attributes['updated_by'] = Auth::id();

            if (! in_array($planning->status, ['FINAL', 'CANCELLED'], true)) {
                $attributes['status'] = $data['status'] ?? 'CALCULATED';
            }

            $planning->update($attributes);
            $planning->items()->delete();
            $planning->devices()->delete();
        }

        $sort = 0;
        $items = [];

        foreach ($result->divisions as $division) {
            $bottlenecks = $division->bottlenecks;
            $shift1ByCode = [];
            $shift2ByCode = [];

            foreach ($division->shift1 as $entry) {
                $shift1ByCode[$entry->code] = $entry->mpp;
            }

            foreach ($division->shift2 as $entry) {
                $shift2ByCode[$entry->code] = $entry->mpp;
            }

            foreach ($division->activities as $activity) {
                $items[] = [
                    'division' => $activity->division,
                    'name' => $activity->name,
                    'code' => $activity->code,
                    'workload_source' => $activity->workloadSource,
                    'workload' => $activity->workload,
                    'workload_unit' => $activity->workloadUnit,
                    'productivity_per_hour' => $activity->productivityPerHour,
                    'productivity_unit' => $activity->productivityUnit,
                    'manpower_type' => $activity->manpowerType,
                    'device_type' => $activity->deviceType,
                    'allowed_shifts' => implode(',', array_map(fn ($shift) => 'S'.$shift, $activity->allowedShifts)),
                    'start_time' => $activity->startTime,
                    'end_time' => $activity->endTime,
                    'minimum_shift' => $division->minimumShift,
                    'division_reason' => $division->reason,
                    'minimum_manpower' => $activity->minimumManpower,
                    'shift_duration' => $config['shift_duration'],
                    'non_productive_hours' => $config['non_productive_hours'],
                    'effective_working_hours' => $config['effective_hours'],
                    'required_mpp' => min($activity->requiredOneShift, 2147483647),
                    'mpp_per_shift' => min($activity->requiredTwoShifts, 2147483647),
                    'mpp_shift_1' => $shift1ByCode[$activity->code] ?? null,
                    'mpp_shift_2' => $shift2ByCode[$activity->code] ?? null,
                    'number_of_shift' => $division->recommendedShifts,
                    'available_mpp' => $activity->availableManpower,
                    'feasibility_status' => $activity->status,
                    'bottleneck' => in_array($activity->name, $bottlenecks, true),
                    'sort_order' => ++$sort,
                ];
            }
        }

        $planning->items()->createMany($items);

        $deviceItems = [];

        foreach ($result->devices as $device) {
            $deviceItems[] = [
                'device_type' => $device->deviceType,
                'ready_quantity' => $device->readyQuantity,
                'required_one_shift' => $device->requiredOneShift,
                'required_per_shift' => $device->requiredPerShift,
                'physical_required' => $device->physicalRequired,
                'shortage' => $device->shortage,
                'status' => $device->status,
            ];
        }

        $planning->devices()->createMany($deviceItems);

        return $planning;
    }

    /**
     * @return array{total_mpp: int, recommendation: string}
     */
    private function planningSummaryFromResult(PlanningResult $result): array
    {
        $totalMpp = 0;

        foreach ($result->divisions as $division) {
            $totalMpp += $division->totalMpp;
        }

        $recommendation = match (true) {
            $result->recommendedShifts === 1 => '1 Shift',
            $result->recommendedShifts === 2 => '2 Shift',
            (! $result->manpowerFeasible && ! $result->deviceFeasible) => 'Critical Operational Shortage',
            ! $result->manpowerFeasible => 'Critical Manpower Shortage',
            default => 'Critical Device Shortage',
        };

        return [
            'total_mpp' => $totalMpp,
            'recommendation' => $recommendation,
            'overall_status' => $result->overallStatus,
        ];
    }

    /**
     * @return array<int, array{division: string, items: mixed, shift: int, mpp_per_shift: int, total_mpp: int, status: string, bottlenecks: array<int, string>}>
     */
    private function planningSummary(ManpowerPlanning $planning): array
    {
        $grouped = [];

        foreach ($planning->items as $item) {
            $grouped[$item->division][] = $item;
        }

        $divisions = [];

        foreach ($grouped as $division => $divisionItems) {
            $shift = 0;
            $minimumShift = 1;
            $reason = null;
            $shift1 = [];
            $shift2 = [];
            $totalMpp = 0;
            $bottlenecks = [];

            foreach ($divisionItems as $item) {
                $shift = max($shift, (int) $item->number_of_shift);
                $minimumShift = (int) $item->minimum_shift;
                $reason = $item->division_reason;

                if ($item->mpp_shift_1 !== null) {
                    $shift1[] = [
                        'name' => $item->name,
                        'code' => $item->code,
                        'mpp' => $item->mpp_shift_1,
                        'device' => $item->device_type,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                    ];
                    $totalMpp += $item->mpp_shift_1;
                }

                if ($item->mpp_shift_2 !== null) {
                    $shift2[] = [
                        'name' => $item->name,
                        'code' => $item->code,
                        'mpp' => $item->mpp_shift_2,
                        'device' => $item->device_type,
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                    ];
                    $totalMpp += $item->mpp_shift_2;
                }

                if ($item->bottleneck) {
                    $bottlenecks[] = $item->name;
                }
            }

            $divisions[] = [
                'division' => $division,
                'items' => $divisionItems,
                'shift' => $shift,
                'minimum_shift' => $minimumShift,
                'reason' => $reason,
                'shift1' => $shift1,
                'shift2' => $shift2,
                'total_mpp' => $totalMpp,
                'status' => $shift === 0 ? 'CRITICAL' : 'FEASIBLE',
                'bottlenecks' => $bottlenecks,
            ];
        }

        usort($divisions, function (array $a, array $b): int {
            $ao = array_search($a['division'], self::DIVISION_ORDER, true);
            $bo = array_search($b['division'], self::DIVISION_ORDER, true);
            $ao = $ao === false ? 99 : $ao;
            $bo = $bo === false ? 99 : $bo;

            return $ao <=> $bo ?: strcmp($a['division'], $b['division']);
        });

        return $divisions;
    }

    /**
     * @return array{shift_duration: float, non_productive_hours: float, effective_hours: float}
     */
    private function resolveConfig(): array
    {
        $settings = SystemSetting::query()
            ->whereIn('setting_key', ['shift_duration', 'non_productive_hours', 'effective_working_hours'])
            ->pluck('setting_value', 'setting_key');

        return [
            'shift_duration' => (float) ($settings['shift_duration'] ?? 8),
            'non_productive_hours' => (float) ($settings['non_productive_hours'] ?? 1),
            'effective_hours' => (float) ($settings['effective_working_hours'] ?? 7),
        ];
    }

    private function resolveVasVolume(string $date): int
    {
        return (int) (ManpowerVasSchedule::query()->where('schedule_date', $date)->value('volume') ?? 0);
    }

    /**
     * @return array<string, int>
     */
    private function resolveDevices(): array
    {
        return DeviceAvailability::query()
            ->pluck('ready_quantity', 'device_type')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * @return array<string, array{minimum_shift: int, reason: string|null}>
     */
    private function resolveDivisionRules(): array
    {
        return ManpowerDivisionRule::query()
            ->get()
            ->mapWithKeys(fn (ManpowerDivisionRule $rule) => [
                $rule->division => ['minimum_shift' => (int) $rule->minimum_shift, 'reason' => $rule->reason],
            ])
            ->all();
    }

    private function generatePlanningNumber(string $date): string
    {
        $next = (ManpowerPlanning::max('id') ?? 0) + 1;
        $dateSegment = Carbon::parse($date)->format('Ymd');

        return 'MP-'.$dateSegment.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlanning(Request $request): array
    {
        return $request->validate([
            'inbound_volume' => ['required', 'integer', 'min:0'],
            'outbound_volume' => ['required', 'integer', 'min:0'],
            'planning_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:DRAFT,CALCULATED,FINAL,CANCELLED'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateActivity(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = $ignoreId
            ? 'unique:manpower_activities,code,'.$ignoreId
            : 'unique:manpower_activities,code';

        $data = $request->validate([
            'division' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', $codeRule],
            'workload_source' => ['required', 'string', 'max:255'],
            'workload_unit' => ['required', 'string', 'max:50'],
            'conversion_ratio' => ['required', 'numeric', 'min:0'],
            'productivity_per_hour' => ['required', 'numeric', 'min:0'],
            'productivity_unit' => ['required', 'string', 'max:50'],
            'manpower_type' => ['required', 'in:Fixed,Variable,Hybrid'],
            'minimum_manpower' => ['nullable', 'integer', 'min:0', 'required_if:manpower_type,Fixed'],
            'available_manpower' => ['required', 'integer', 'min:0'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'allowed_shifts' => ['nullable', Rule::in(['S1,S2', 'S1', 'S2'])],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['minimum_manpower'] = $data['minimum_manpower'] ?? null;
        $data['device_type'] = ($data['device_type'] ?? '') ?: null;
        $data['allowed_shifts'] = $data['allowed_shifts'] ?? 'S1,S2';
        $data['start_time'] = $data['start_time'] ?? '07:00';
        $data['end_time'] = $data['end_time'] ?? '23:00';
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVasSchedule(Request $request, ?int $ignoreId = null): array
    {
        $dateRule = $ignoreId
            ? 'unique:manpower_vas_schedules,schedule_date,'.$ignoreId
            : 'unique:manpower_vas_schedules,schedule_date';

        return $request->validate([
            'schedule_date' => ['required', 'date', $dateRule],
            'volume' => ['required', 'integer', 'min:0'],
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
