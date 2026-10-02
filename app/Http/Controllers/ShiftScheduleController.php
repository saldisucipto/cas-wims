<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Employee;
use App\Models\ManpowerPlanning;
use App\Models\ShiftHandover;
use App\Models\ShiftSchedule;
use App\Models\ShiftScheduleDetail;
use App\Services\ShiftScheduling\ShiftScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShiftScheduleController extends Controller
{
    public function __construct(private ShiftScheduleService $service) {}

    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = ShiftSchedule::query()->with(['creator', 'division'])->latest('year')->latest('month')->latest('id');

        if ($q = $request->string('q')->toString()) {
            $query->where('schedule_number', 'like', "%{$q}%");
        }

        return view('administration.shift-schedules.index', [
            'rows' => $query->paginate(15)->withQueryString(),
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function create(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $selectedDivisionId = $request->integer('division_id') ?: (int) old('division_id');
        $coreEmployees = $this->coreEmployeesByDivision($selectedDivisionId > 0 ? $selectedDivisionId : null);

        return view('administration.shift-schedules.create', [
            'divisions' => Division::query()->orderBy('name')->get(),
            'selectedDivisionId' => $selectedDivisionId > 0 ? $selectedDivisionId : null,
            'coreEmployees' => $coreEmployees,
            'plannings' => ManpowerPlanning::query()->latest('planning_date')->take(20)->get(),
        ]);
    }

    public function store(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'division_id' => ['required', 'exists:divisions,id'],
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'selected_employees' => ['required', 'array', 'min:1'],
            'selected_employees.*' => ['required', 'integer', 'exists:employees,id'],
            'initial_shift_map' => ['nullable', 'array'],
            'manpower_planning_id' => ['nullable', 'exists:manpower_plannings,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $startDate = Carbon::parse((string) $data['period_start_date'])->startOfDay();
        $endDate = Carbon::parse((string) $data['period_end_date'])->startOfDay();
        $employeeIds = collect($data['selected_employees'])->map(fn ($id) => (int) $id)->unique()->values();

        $eligibleEmployeeIds = $this->coreEmployeesByDivision((int) $data['division_id'])
            ->whereIn('id', $employeeIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($eligibleEmployeeIds) !== $employeeIds->count()) {
            return back()->withInput()->with('error', 'Sebagian employee tidak valid untuk Division terpilih atau bukan Core Employee.');
        }

        $overlap = $this->findOverlappingAssignment($eligibleEmployeeIds, $startDate, $endDate);

        if ($overlap) {
            $name = $overlap->employee?->employee_name ?? ('Employee #'.$overlap->employee_id);

            return back()->withInput()->with('error', "Jadwal bentrok: {$name} sudah memiliki schedule pada {$overlap->date}.");
        }

        $initialShiftMap = collect($data['initial_shift_map'] ?? [])
            ->mapWithKeys(fn ($shift, $employeeId) => [(int) $employeeId => strtoupper((string) $shift)])
            ->map(fn ($shift) => in_array($shift, ['S1', 'S2'], true) ? $shift : 'S1')
            ->only($employeeIds->all())
            ->all();

        foreach ($employeeIds as $employeeId) {
            if (! isset($initialShiftMap[$employeeId])) {
                $initialShiftMap[$employeeId] = 'S1';
            }
        }

        $year = (int) $startDate->year;
        $month = (int) $startDate->month;

        $schedule = ShiftSchedule::query()->create([
            'schedule_number' => $this->generateScheduleNumber($year, $month),
            'month' => $month,
            'year' => $year,
            'period_start_date' => $startDate->toDateString(),
            'period_end_date' => $endDate->toDateString(),
            'status' => 'DRAFT',
            'division_id' => (int) $data['division_id'],
            'selected_employee_ids' => $employeeIds->all(),
            'initial_shift_map' => $initialShiftMap,
            'manpower_planning_id' => $data['manpower_planning_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->service->generate($schedule);

        return redirect()
            ->route('administration.shift-schedules.show', $schedule)
            ->with('success', 'Shifting schedule generated.');
    }

    public function show(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $shiftSchedule->load(['details.employee', 'details.position', 'manpowerPlanning', 'creator', 'division']);

        return view('administration.shift-schedules.show', [
            'schedule' => $shiftSchedule,
            'employees' => $shiftSchedule->details->unique('employee_id')->sortBy(fn ($d) => $d->employee?->employee_code ?? ''),
            'dateColumns' => $this->dateColumns($shiftSchedule),
            'validation' => $this->service->validate($shiftSchedule),
            'timeline' => $this->service->timeline($shiftSchedule),
            'shifts' => $this->service->assignableShifts(),
        ]);
    }

    public function regenerate(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if ($shiftSchedule->status === 'FINAL') {
            return back()->with('error', 'Final schedule cannot be regenerated.');
        }

        $periodStart = $shiftSchedule->period_start_date
            ? Carbon::parse($shiftSchedule->period_start_date)
            : Carbon::create($shiftSchedule->year, $shiftSchedule->month, 1)->startOfDay();
        $periodEnd = $shiftSchedule->period_end_date
            ? Carbon::parse($shiftSchedule->period_end_date)
            : $periodStart->copy()->endOfMonth();
        $overlap = $this->findOverlappingAssignment(
            collect($shiftSchedule->selected_employee_ids ?? [])->map(fn ($id) => (int) $id)->all(),
            $periodStart,
            $periodEnd,
            $shiftSchedule->id,
        );

        if ($overlap) {
            $name = $overlap->employee?->employee_name ?? ('Employee #'.$overlap->employee_id);

            return back()->with('error', "Regenerate diblokir: {$name} sudah memiliki schedule lain pada {$overlap->date}.");
        }

        $this->service->generate($shiftSchedule);
        $shiftSchedule->update(['updated_by' => Auth::id()]);

        return back()->with('success', 'Schedule regenerated.');
    }

    public function finalize(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $validation = $this->service->validate($shiftSchedule);

        if ($validation['overall_status'] !== 'READY') {
            return back()->with('error', 'Schedule has '.count($validation['errors']).' validation issue(s). Resolve before finalizing.');
        }

        $shiftSchedule->update([
            'status' => 'FINAL',
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Schedule finalized.');
    }

    public function duplicate(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $periodStart = $shiftSchedule->period_start_date
            ? Carbon::parse($shiftSchedule->period_start_date)
            : Carbon::create($shiftSchedule->year, $shiftSchedule->month, 1);
        $periodEnd = $shiftSchedule->period_end_date
            ? Carbon::parse($shiftSchedule->period_end_date)
            : $periodStart->copy()->endOfMonth();
        $days = $periodStart->diffInDays($periodEnd);
        $nextStart = $periodEnd->copy()->addDay();
        $nextEnd = $nextStart->copy()->addDays($days);

        $duplicate = $shiftSchedule->replicate(['schedule_number', 'status', 'created_by', 'updated_by', 'finalized_by', 'finalized_at', 'created_at', 'updated_at']);
        $duplicate->schedule_number = $this->generateScheduleNumber($nextStart->year, $nextStart->month);
        $duplicate->month = $nextStart->month;
        $duplicate->year = $nextStart->year;
        $duplicate->period_start_date = $nextStart->toDateString();
        $duplicate->period_end_date = $nextEnd->toDateString();
        $duplicate->status = 'DRAFT';
        $duplicate->created_by = Auth::id();

        $overlap = $this->findOverlappingAssignment(
            collect($duplicate->selected_employee_ids ?? [])->map(fn ($id) => (int) $id)->all(),
            $nextStart,
            $nextEnd,
        );

        if ($overlap) {
            $name = $overlap->employee?->employee_name ?? ('Employee #'.$overlap->employee_id);

            return back()->with('error', "Duplicate diblokir: {$name} sudah memiliki schedule pada {$overlap->date}.");
        }

        $duplicate->save();

        $this->service->generate($duplicate);

        return redirect()
            ->route('administration.shift-schedules.show', $duplicate)
            ->with('success', 'Schedule duplicated to '.$nextStart->format('d M Y').' - '.$nextEnd->format('d M Y').'.');
    }

    public function print(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $shiftSchedule->load(['details.employee', 'details.position', 'manpowerPlanning', 'creator', 'division']);

        return view('administration.shift-schedules.print', [
            'schedule' => $shiftSchedule,
            'employees' => $shiftSchedule->details->unique('employee_id')->sortBy(fn ($d) => $d->employee?->employee_code ?? ''),
            'dateColumns' => $this->dateColumns($shiftSchedule),
            'validation' => $this->service->validate($shiftSchedule),
            'definitions' => $this->service->definitions(),
        ]);
    }

    public function edit(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $shiftSchedule->load(['details.employee', 'details.position']);

        return view('administration.shift-schedules.edit', [
            'schedule' => $shiftSchedule,
            'shifts' => $this->service->assignableShifts(),
            'dateColumns' => $this->dateColumns($shiftSchedule),
            'employees' => $shiftSchedule->details->unique('employee_id')->sortBy(fn ($d) => $d->employee?->employee_code ?? ''),
        ]);
    }

    public function updateAssignments(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if ($shiftSchedule->status === 'FINAL') {
            return back()->with('error', 'Final schedule cannot be modified.');
        }

        $assignments = $request->input('assignments', []);
        $overrides = $request->input('overrides', []);

        if (! is_array($assignments)) {
            return back()->with('error', 'Invalid assignment payload.');
        }

        $validShifts = array_keys($this->service->assignableShifts());
        $invalid = array_diff(array_map('strval', array_values($assignments)), $validShifts);

        if (! empty($invalid)) {
            return back()->with('error', 'Invalid shift code(s): '.implode(', ', $invalid));
        }

        DB::transaction(function () use ($shiftSchedule, $assignments, $overrides) {
            foreach ($assignments as $detailId => $shift) {
                $detail = $shiftSchedule->details()->with('employee')->find($detailId);

                if (! $detail) {
                    continue;
                }

                $detail->update([
                    'shift' => $shift,
                    'working_hours' => $this->service->effectiveHoursFor($shift),
                    'assignment_type' => $this->service->assignmentTypeFor($detail->employee?->shift_pattern ?? 'ROTATING', $shift),
                    'is_override' => isset($overrides[$detailId]),
                ]);
            }
        });

        $shiftSchedule->update(['updated_by' => Auth::id()]);

        return redirect()
            ->route('administration.shift-schedules.show', $shiftSchedule)
            ->with('success', 'Shift assignments updated.');
    }

    public function handover(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $shiftSchedule->load('creator');
        $handovers = $shiftSchedule->handovers()->with('recordedBy')->orderBy('handover_date')->orderBy('id')->get();

        return view('administration.shift-schedules.handover', [
            'schedule' => $shiftSchedule,
            'handovers' => $handovers,
            'shiftCodes' => $this->service->definitions()->pluck('code')->all(),
        ]);
    }

    public function storeHandover(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'handover_date' => ['required', 'date'],
            'shift_from' => ['required', Rule::in(['S1', 'S2', 'S1_SAT', 'S2_SAT'])],
            'shift_to' => ['required', Rule::in(['S1', 'S2', 'S1_SAT', 'S2_SAT'])],
            'job_type' => ['required', Rule::in(ShiftHandover::JOB_TYPES)],
            'description' => ['required', 'string'],
            'quantity' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        $shiftSchedule->handovers()->create([
            'handover_date' => $data['handover_date'],
            'shift_from' => $data['shift_from'],
            'shift_to' => $data['shift_to'],
            'job_type' => $data['job_type'],
            'description' => $data['description'],
            'quantity' => $data['quantity'] ?? null,
            'unit' => $data['unit'] ?? null,
            'status' => 'OPEN',
            'recorded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Handover job recorded.');
    }

    public function transferHandover(Request $request, ShiftSchedule $shiftSchedule, ShiftHandover $shiftHandover)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $this->assertHandoverBelongsTo($shiftSchedule, $shiftHandover);

        $shiftHandover->update(['status' => 'TRANSFERRED']);

        return back()->with('success', 'Handover job marked as transferred.');
    }

    public function closeHandover(Request $request, ShiftSchedule $shiftSchedule, ShiftHandover $shiftHandover)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $this->assertHandoverBelongsTo($shiftSchedule, $shiftHandover);

        $shiftHandover->update(['status' => 'CLOSED']);

        return back()->with('success', 'Handover job closed.');
    }

    private function assertHandoverBelongsTo(ShiftSchedule $shiftSchedule, ShiftHandover $shiftHandover): void
    {
        abort_unless($shiftHandover->shift_schedule_id === $shiftSchedule->id, 404);
    }

    private function generateScheduleNumber(int $year, int $month): string
    {
        $next = (ShiftSchedule::max('id') ?? 0) + 1;

        return 'SHIFT-'.$year.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function dateColumns(ShiftSchedule $schedule): array
    {
        $dates = $schedule->details->pluck('date')->unique()->sort()->values();

        if ($dates->isNotEmpty()) {
            return $dates->all();
        }

        if (! $schedule->period_start_date || ! $schedule->period_end_date) {
            return [];
        }

        $cursor = Carbon::parse($schedule->period_start_date)->startOfDay();
        $end = Carbon::parse($schedule->period_end_date)->startOfDay();
        $range = [];

        while ($cursor->lte($end)) {
            $range[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $range;
    }

    private function coreEmployeesByDivision(?int $divisionId)
    {
        if (! $divisionId) {
            return collect();
        }

        return Employee::query()
            ->with(['position', 'division'])
            ->where('status', 'ACTIVE')
            ->where('employment_type', 'CORE_EMPLOYEE')
            ->where('division_id', $divisionId)
            ->orderBy('employee_code')
            ->get();
    }

    private function findOverlappingAssignment(array $employeeIds, Carbon $startDate, Carbon $endDate, ?int $excludeScheduleId = null): ?ShiftScheduleDetail
    {
        if (empty($employeeIds)) {
            return null;
        }

        return ShiftScheduleDetail::query()
            ->select(['employee_id', 'date'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereHas('schedule', function ($query) use ($excludeScheduleId) {
                $query->where('status', '!=', 'CANCELLED');

                if ($excludeScheduleId) {
                    $query->where('id', '!=', $excludeScheduleId);
                }
            })
            ->with('employee')
            ->orderBy('date')
            ->first();
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
