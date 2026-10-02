<?php

namespace App\Services\ShiftScheduling;

use App\Models\DeviceAvailability;
use App\Models\Employee;
use App\Models\ShiftDefinition;
use App\Models\ShiftSchedule;
use App\Models\SystemSetting;
use App\Models\WorkingCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftScheduleService
{
    public const SHIFT_S1 = 'S1';

    public const SHIFT_S2 = 'S2';

    public const SHIFT_S1_SAT = 'S1_SAT';

    public const SHIFT_S2_SAT = 'S2_SAT';

    public const SHIFT_OFF = 'OFF';

    public const SHIFT_LEAVE = 'LEAVE';

    public const SHIFT_SICK = 'SICK';

    public const SHIFT_PERMISSION = 'PERMISSION';

    /**
     * Human-readable labels for every assignable shift code.
     *
     * @return array<string, string>
     */
    public function assignableShifts(): array
    {
        return [
            self::SHIFT_S1 => 'Shift 1 (Morning)',
            self::SHIFT_S2 => 'Shift 2 (Afternoon)',
            self::SHIFT_S1_SAT => 'Saturday Short Shift 1',
            self::SHIFT_S2_SAT => 'Saturday Short Shift 2',
            self::SHIFT_OFF => 'Off',
            self::SHIFT_LEAVE => 'Leave',
            self::SHIFT_SICK => 'Sick',
            self::SHIFT_PERMISSION => 'Permission',
        ];
    }

    /**
     * @return Collection<int, ShiftDefinition>
     */
    public function definitions(): Collection
    {
        return ShiftDefinition::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * Generate daily schedule details for a month based on each employee's
     * shift pattern (rotating / fixed S1 / fixed S2) and the working calendar.
     * Saturday is treated as a short working day (S1_SAT / S2_SAT).
     */
    public function generate(ShiftSchedule $schedule): void
    {
        $employees = $this->activeEmployeesFor($schedule);
        $calendars = WorkingCalendar::query()->get()->keyBy('day_of_week');
        $definitions = $this->definitions()->keyBy('code');

        $startDate = $schedule->period_start_date
            ? Carbon::parse($schedule->period_start_date)->startOfDay()
            : Carbon::create($schedule->year, $schedule->month, 1)->startOfDay();
        $endDate = $schedule->period_end_date
            ? Carbon::parse($schedule->period_end_date)->startOfDay()
            : $startDate->copy()->endOfMonth()->startOfDay();
        $startWeek = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $initialShiftMap = collect($schedule->initial_shift_map ?? [])
            ->mapWithKeys(fn ($value, $key) => [(int) $key => strtoupper((string) $value)])
            ->all();

        $details = [];

        foreach ($employees as $employee) {
            $cursor = $startDate->copy();

            while ($cursor->lte($endDate)) {
                $date = $cursor->copy();
                $dayOfWeek = $date->dayOfWeekIso;
                $calendar = $calendars->get($dayOfWeek);
                $weekNumber = $startWeek->diffInWeeks($date->copy()->startOfWeek(Carbon::MONDAY)) + 1;

                $shift = $this->resolveShift(
                    $employee,
                    $weekNumber,
                    $calendar,
                    $dayOfWeek,
                    $initialShiftMap[$employee->id] ?? null,
                );

                $details[] = [
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'week_number' => $weekNumber,
                    'shift' => $shift,
                    'position_id' => $employee->position_id,
                    'division_id' => $employee->division_id,
                    'working_hours' => $definitions->has($shift) ? $definitions->get($shift)->effective_hours : 0,
                    'assignment_type' => $this->assignmentType($employee, $shift),
                    'is_override' => false,
                ];

                $cursor->addDay();
            }
        }

        $schedule->details()->delete();
        $schedule->details()->createMany($details);
    }

    /**
     * Validate a generated schedule and return a structured summary.
     *
     * @return array<string, mixed>
     */
    public function validate(ShiftSchedule $schedule): array
    {
        $schedule->load(['details.employee', 'details.position', 'manpowerPlanning.items']);

        $calendars = WorkingCalendar::query()->get()->keyBy('day_of_week');
        $definitions = $this->definitions()->keyBy('code');
        $maxWeeklyHours = (float) (SystemSetting::query()->where('setting_key', 'max_weekly_hours')->value('setting_value') ?? 40);

        $errors = $this->validateShiftDefinitions($definitions);
        $weeklyHours = [];
        $overriddenWeeks = [];
        $weeklyViolations = [];
        $exceedingWeekly = [];
        $employeeValid = true;
        $shiftValid = true;
        $workingHoursValid = empty($errors);

        foreach ($schedule->details as $detail) {
            $employee = $detail->employee;

            if (! $employee || $employee->status !== 'ACTIVE') {
                $employeeValid = false;
                $errors[] = "{$detail->employee_id}: employee inactive.";

                continue;
            }

            if (! $this->employeeCoversDate($employee, $detail->date)) {
                $employeeValid = false;
                $errors[] = "{$employee->employee_code}: employment period does not cover {$detail->date}.";
            }

            if ($detail->position && ! $this->shiftAllowed($detail->position->allowed_shifts, $detail->shift)) {
                $shiftValid = false;
                $errors[] = "{$employee->employee_code}: {$detail->position->name} not allowed on {$detail->shift}.";
            }

            $date = Carbon::parse($detail->date);
            $calendar = $calendars->get($date->dayOfWeekIso);

            if ($calendar && ! $calendar->is_working_day && $detail->shift !== self::SHIFT_OFF) {
                $workingHoursValid = false;
                $errors[] = "{$employee->employee_code}: {$detail->date} is a non-working day but assigned {$detail->shift}.";
            }

            $weeklyHours[$employee->employee_code][$detail->week_number] = ($weeklyHours[$employee->employee_code][$detail->week_number] ?? 0) + $detail->working_hours;
            $overriddenWeeks[$employee->employee_code][$detail->week_number] = ($overriddenWeeks[$employee->employee_code][$detail->week_number] ?? false) || $detail->is_override;
        }

        foreach ($weeklyHours as $code => $weeks) {
            foreach ($weeks as $week => $hours) {
                if ($hours <= $maxWeeklyHours) {
                    continue;
                }

                $warning = "{$code} (week {$week}): Weekly working hours exceed the normal {$maxWeeklyHours}-hour limit. Please review the employee schedule or classify the additional hours according to the applicable overtime/company policy.";
                $weeklyViolations[] = $warning;
                $exceedingWeekly[] = ['employee' => $code, 'week' => $week, 'hours' => $hours, 'overridden' => (bool) ($overriddenWeeks[$code][$week] ?? false)];

                if (! ($overriddenWeeks[$code][$week] ?? false)) {
                    $workingHoursValid = false;
                    $errors[] = $warning;
                }
            }
        }

        $coverage = $this->manpowerCoverage($schedule);
        $devices = $this->deviceCoverage($schedule, $coverage);

        $manpowerValid = ! collect($coverage)->contains(fn ($row) => $row['status'] === 'SHORTAGE');
        $deviceValid = ! collect($devices)->contains(fn ($row) => $row['status'] === 'SHORTAGE');

        $daily = $this->dailyCoverage($schedule);

        return [
            'errors' => $errors,
            'employee_valid' => $employeeValid,
            'shift_valid' => $shiftValid,
            'working_hours_valid' => $workingHoursValid,
            'manpower_valid' => $manpowerValid,
            'device_valid' => $deviceValid,
            'weekly_hours' => $weeklyHours,
            'weekly_violations' => $weeklyViolations,
            'exceeding_weekly' => $exceedingWeekly,
            'coverage' => $coverage,
            'devices' => $devices,
            'daily_coverage' => $daily,
            'overall_status' => empty($errors) ? 'READY' : 'ERRORS',
            'summary' => $this->summary($schedule, $weeklyViolations, $exceedingWeekly, $coverage, $devices),
        ];
    }

    /**
     * Timeline and per-date headcount data for the coverage visualization.
     *
     * @return array<string, mixed>
     */
    public function timeline(ShiftSchedule $schedule): array
    {
        $definitions = $this->definitions()->map(fn (ShiftDefinition $d) => [
            'code' => $d->code,
            'name' => $d->name,
            'start_time' => $d->start_time,
            'end_time' => $d->end_time,
            'break_start' => $d->break_start,
            'break_end' => $d->break_end,
            'effective_hours' => $d->effective_hours,
            'is_short_day' => $d->is_short_day,
        ])->keyBy('code');

        $s1 = $definitions->get(self::SHIFT_S1);
        $s2 = $definitions->get(self::SHIFT_S2);
        $overlap = null;

        if ($s1 && $s2) {
            $overlap = [
                'start' => max($s1['start_time'], $s2['start_time']),
                'end' => min($s1['end_time'], $s2['end_time']),
            ];
        }

        return [
            'definitions' => $definitions->all(),
            'overlap' => $overlap,
            'daily' => $this->dailyCoverage($schedule),
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function dailyCoverage(ShiftSchedule $schedule): array
    {
        $daily = [];

        foreach ($schedule->details as $detail) {
            $daily[$detail->date][$detail->shift] = ($daily[$detail->date][$detail->shift] ?? 0) + 1;
        }

        ksort($daily);

        return $daily;
    }

    /**
     * @param  array<int, string>  $weeklyViolations
     * @param  array<int, array<string, mixed>>  $exceedingWeekly
     * @param  array<int, array<string, mixed>>  $coverage
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function summary(ShiftSchedule $schedule, array $weeklyViolations, array $exceedingWeekly, array $coverage, array $devices): array
    {
        $count = fn (string $shift) => $schedule->details->where('shift', $shift)->unique('employee_id')->count();

        return [
            'total_employees' => $schedule->details->unique('employee_id')->count(),
            'shift_1' => $count(self::SHIFT_S1),
            'shift_2' => $count(self::SHIFT_S2),
            'shift_1_sat' => $count(self::SHIFT_S1_SAT),
            'shift_2_sat' => $count(self::SHIFT_S2_SAT),
            'off' => $count(self::SHIFT_OFF),
            'leave' => $count(self::SHIFT_LEAVE),
            'sick' => $count(self::SHIFT_SICK),
            'permission' => $count(self::SHIFT_PERMISSION),
            'weekly_violations' => count($weeklyViolations),
            'exceeding_weekly' => $exceedingWeekly,
            'saturday_coverage' => [
                'shift_1' => $count(self::SHIFT_S1_SAT),
                'shift_2' => $count(self::SHIFT_S2_SAT),
            ],
            'manpower_shortage' => collect($coverage)->filter(fn ($row) => $row['status'] === 'SHORTAGE')->count(),
            'device_shortage' => collect($devices)->filter(fn ($row) => $row['status'] === 'SHORTAGE')->count(),
        ];
    }

    /**
     * Validate shift definitions themselves (config-level errors).
     *
     * @param  Collection<int, ShiftDefinition>  $definitions
     * @return array<int, string>
     */
    private function validateShiftDefinitions(Collection $definitions): array
    {
        $errors = [];

        foreach ($definitions as $definition) {
            $start = $this->minutesOfDay($definition->start_time);
            $end = $this->minutesOfDay($definition->end_time);

            if ($start === null || $end === null) {
                $errors[] = "Shift definition {$definition->code}: invalid start/end time.";

                continue;
            }

            if ($end <= $start) {
                $errors[] = "Shift definition {$definition->code}: end time ({$definition->end_time}) must be after start time ({$definition->start_time}).";
            }

            if ($definition->break_start || $definition->break_end) {
                $breakStart = $this->minutesOfDay($definition->break_start);
                $breakEnd = $this->minutesOfDay($definition->break_end);

                if ($breakStart === null || $breakEnd === null || $breakEnd <= $breakStart) {
                    $errors[] = "Shift definition {$definition->code}: invalid break period.";
                } elseif ($breakStart < $start || $breakEnd > $end) {
                    $errors[] = "Shift definition {$definition->code}: break period must fall inside the shift working time.";
                }
            }

            if ($definition->break_minutes < 0 || $definition->break_minutes >= ($end - $start)) {
                $errors[] = "Shift definition {$definition->code}: invalid break duration.";
            }
        }

        return $errors;
    }

    private function minutesOfDay(?string $time): ?int
    {
        if (! $time || ! preg_match('/^(\d{2}):(\d{2})$/', $time, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manpowerCoverage(ShiftSchedule $schedule): array
    {
        $planning = $schedule->manpowerPlanning;

        if (! $planning) {
            return [];
        }

        $required = [];

        foreach ($planning->items as $item) {
            if ($item->mpp_shift_1 !== null) {
                $required[$item->name][self::SHIFT_S1] = ($required[$item->name][self::SHIFT_S1] ?? 0) + $item->mpp_shift_1;
            }

            if ($item->mpp_shift_2 !== null) {
                $required[$item->name][self::SHIFT_S2] = ($required[$item->name][self::SHIFT_S2] ?? 0) + $item->mpp_shift_2;
            }
        }

        $rows = [];

        foreach ($required as $positionName => $shifts) {
            foreach ($shifts as $shift => $req) {
                $core = $schedule->details
                    ->where('shift', $shift)
                    ->where('position.name', $positionName)
                    ->unique('employee_id')
                    ->count();

                $gap = max(0, $req - $core);
                $coverage = $req > 0 ? round($core / $req * 100) : 100;

                $rows[] = [
                    'position' => $positionName,
                    'shift' => $shift,
                    'required' => $req,
                    'core' => $core,
                    'gap' => $gap,
                    'coverage' => $coverage,
                    'status' => $gap > 0 ? 'SHORTAGE' : 'FEASIBLE',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $coverage
     * @return array<int, array<string, mixed>>
     */
    private function deviceCoverage(ShiftSchedule $schedule, array $coverage): array
    {
        $deviceShift = [];

        foreach ($schedule->details as $detail) {
            $device = $detail->position?->device_type;

            if (! $device || $detail->shift === self::SHIFT_OFF) {
                continue;
            }

            // Count unique employees per shift, not every day-row.
            $deviceShift[$device][$detail->shift][$detail->employee_id] = true;
        }

        $rows = [];

        foreach ($deviceShift as $device => $shifts) {
            $ready = (int) (DeviceAvailability::query()->where('device_type', $device)->value('ready_quantity') ?? 0);

            foreach ($shifts as $shift => $employeeIds) {
                $required = count($employeeIds);
                $shortage = max(0, $required - $ready);

                $rows[] = [
                    'device' => $device,
                    'shift' => $shift,
                    'required' => $required,
                    'ready' => $ready,
                    'shortage' => $shortage,
                    'status' => $shortage > 0 ? 'SHORTAGE' : 'FEASIBLE',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function activeEmployeesFor(ShiftSchedule $schedule): Collection
    {
        $start = $schedule->period_start_date
            ? Carbon::parse($schedule->period_start_date)->startOfDay()
            : Carbon::create($schedule->year, $schedule->month, 1)->startOfDay();
        $end = $schedule->period_end_date
            ? Carbon::parse($schedule->period_end_date)->endOfDay()
            : $start->copy()->endOfMonth();

        $selectedEmployeeIds = collect($schedule->selected_employee_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        $query = Employee::query()
            ->where('status', 'ACTIVE')
            ->where('employment_type', 'CORE_EMPLOYEE')
            ->orderBy('employee_code');

        if ($schedule->division_id) {
            $query->where('division_id', $schedule->division_id);
        }

        if ($selectedEmployeeIds->isNotEmpty()) {
            $query->whereIn('id', $selectedEmployeeIds->all());
        } else {
            $query->where(function ($query) use ($end) {
                $query->whereNull('employment_end_date')->orWhere('employment_end_date', '>=', $end->toDateString());
            })
                ->where(function ($query) use ($start) {
                    $query->whereNull('employment_start_date')->orWhere('employment_start_date', '<=', $start->toDateString());
                });
        }

        return $query->get();
    }

    private function resolveShift(Employee $employee, int $weekNumber, ?WorkingCalendar $calendar, int $dayOfWeek, ?string $initialShift): string
    {
        if ($calendar && ! $calendar->is_working_day) {
            return self::SHIFT_OFF;
        }

        $initialBand = in_array($initialShift, [self::SHIFT_S1, self::SHIFT_S2], true)
            ? $initialShift
            : match ($employee->shift_pattern) {
                'FIXED_S2' => self::SHIFT_S2,
                default => self::SHIFT_S1,
            };

        $band = $weekNumber % 2 === 1
            ? $initialBand
            : ($initialBand === self::SHIFT_S1 ? self::SHIFT_S2 : self::SHIFT_S1);

        if ($dayOfWeek === Carbon::SATURDAY) {
            $saturdayBand = $band === self::SHIFT_S1 ? self::SHIFT_S2 : self::SHIFT_S1;

            return $saturdayBand === self::SHIFT_S1 ? self::SHIFT_S1_SAT : self::SHIFT_S2_SAT;
        }

        return $band;
    }

    private function assignmentType(Employee $employee, string $shift): string
    {
        return $this->assignmentTypeFor($employee->shift_pattern, $shift);
    }

    /**
     * Effective working hours for a shift code, resolved from its definition.
     */
    public function effectiveHoursFor(string $shift): float
    {
        return $this->definitions()->firstWhere('code', $shift)?->effective_hours ?? 0;
    }

    /**
     * Assignment type label for a shift code + employee shift pattern.
     */
    public function assignmentTypeFor(string $shiftPattern, string $shift): string
    {
        return match ($shift) {
            self::SHIFT_OFF => 'OFF',
            self::SHIFT_LEAVE => 'LEAVE',
            self::SHIFT_SICK => 'SICK',
            self::SHIFT_PERMISSION => 'PERMISSION',
            self::SHIFT_S1_SAT, self::SHIFT_S2_SAT => 'SHORT',
            default => $shiftPattern === 'ROTATING' ? 'ROTATION' : 'FIXED',
        };
    }

    private function shiftAllowed(string $allowedShifts, string $shift): bool
    {
        if (in_array($shift, [self::SHIFT_OFF, self::SHIFT_LEAVE, self::SHIFT_SICK, self::SHIFT_PERMISSION], true)) {
            return true;
        }

        $band = str_ends_with($shift, '_SAT') ? substr($shift, 0, 2) : $shift;

        return in_array($band, array_map('trim', explode(',', $allowedShifts)), true);
    }

    private function employeeCoversDate(Employee $employee, string $date): bool
    {
        if ($employee->employment_start_date && $employee->employment_start_date > $date) {
            return false;
        }

        if ($employee->employment_end_date && $employee->employment_end_date < $date) {
            return false;
        }

        return true;
    }
}
