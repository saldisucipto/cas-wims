<?php

namespace App\Http\Controllers;

use App\Models\ManpowerPlanning;
use App\Models\ShiftHandover;
use App\Models\ShiftSchedule;
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

        $query = ShiftSchedule::query()->with('creator')->latest('year')->latest('month')->latest('id');

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

        return view('administration.shift-schedules.create', [
            'plannings' => ManpowerPlanning::query()->latest('planning_date')->take(20)->get(),
        ]);
    }

    public function store(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'manpower_planning_id' => ['nullable', 'exists:manpower_plannings,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $schedule = ShiftSchedule::query()->create([
            'schedule_number' => $this->generateScheduleNumber((int) $data['year'], (int) $data['month']),
            'month' => (int) $data['month'],
            'year' => (int) $data['year'],
            'status' => 'DRAFT',
            'manpower_planning_id' => $data['manpower_planning_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->service->generate($schedule);

        return redirect()
            ->route('administration.shift-schedules.show', $schedule)
            ->with('success', 'Monthly shift schedule generated.');
    }

    public function show(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $shiftSchedule->load(['details.employee', 'details.position', 'manpowerPlanning', 'creator']);

        return view('administration.shift-schedules.show', [
            'schedule' => $shiftSchedule,
            'employees' => $shiftSchedule->details->unique('employee_id')->sortBy(fn ($d) => $d->employee?->employee_code ?? ''),
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

        $next = Carbon::create($shiftSchedule->year, $shiftSchedule->month, 1)->addMonth();

        $duplicate = $shiftSchedule->replicate(['schedule_number', 'status', 'created_by', 'updated_by', 'finalized_by', 'finalized_at', 'created_at', 'updated_at']);
        $duplicate->schedule_number = $this->generateScheduleNumber($next->year, $next->month);
        $duplicate->month = $next->month;
        $duplicate->year = $next->year;
        $duplicate->status = 'DRAFT';
        $duplicate->created_by = Auth::id();
        $duplicate->save();

        $this->service->generate($duplicate);

        return redirect()
            ->route('administration.shift-schedules.show', $duplicate)
            ->with('success', 'Schedule duplicated to '.$next->translatedFormat('F Y').'.');
    }

    public function print(Request $request, ShiftSchedule $shiftSchedule)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $shiftSchedule->load(['details.employee', 'details.position', 'manpowerPlanning', 'creator']);

        return view('administration.shift-schedules.print', [
            'schedule' => $shiftSchedule,
            'employees' => $shiftSchedule->details->unique('employee_id')->sortBy(fn ($d) => $d->employee?->employee_code ?? ''),
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
            'days' => $this->daysInMonth($shiftSchedule),
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

    private function daysInMonth(ShiftSchedule $schedule): int
    {
        return Carbon::create($schedule->year, $schedule->month, 1)->daysInMonth;
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
