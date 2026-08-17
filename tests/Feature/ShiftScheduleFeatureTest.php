<?php

use App\Models\Employee;
use App\Models\ShiftDefinition;
use App\Models\ShiftSchedule;
use App\Models\User;
use App\Models\WorkingCalendar;
use App\Services\ShiftScheduling\ShiftScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedCalendar(): void
{
    foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $dow => $name) {
        WorkingCalendar::query()->create([
            'day_of_week' => $dow,
            'day_name' => $name,
            'is_working_day' => $dow !== 7,
            'working_hours' => $dow === 6 ? 5 : 8,
        ]);
    }
}

function seedShiftDefinitions(): void
{
    $rows = [
        ['S1', 'Shift 1 (Morning)', '08:00', '16:00', '12:00', '13:00', 60, 7, false],
        ['S2', 'Shift 2 (Afternoon)', '14:00', '22:00', '18:00', '19:00', 60, 7, false],
        ['S1_SAT', 'Saturday Short Shift 1', '08:00', '14:00', '09:00', '10:00', 60, 5, true],
        ['S2_SAT', 'Saturday Short Shift 2', '12:00', '18:00', '14:00', '15:00', 60, 5, true],
    ];

    foreach ($rows as [$code, $name, $start, $end, $breakStart, $breakEnd, $breakMinutes, $effective, $short]) {
        ShiftDefinition::query()->create([
            'code' => $code,
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'break_minutes' => $breakMinutes,
            'effective_hours' => $effective,
            'is_short_day' => $short,
            'sort_order' => 0,
            'active' => true,
        ]);
    }
}

function createSchedule(): ShiftSchedule
{
    $service = new ShiftScheduleService;
    $schedule = ShiftSchedule::query()->create([
        'schedule_number' => 'SHIFT-202608-0001',
        'month' => 8,
        'year' => 2026,
        'status' => 'DRAFT',
    ]);
    $service->generate($schedule);

    return $schedule->fresh();
}

test('shift schedule page requires administrator', function () {
    $this->get(route('administration.shift-schedules'))
        ->assertRedirect(route('administration.login'));
});

test('generate maps saturday to short shift and sunday off with correct effective hours', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);

    $schedule = ShiftSchedule::query()->first();

    expect($schedule->schedule_number)->toStartWith('SHIFT-202608-')
        ->and($schedule->details()->count())->toBe(31);

    // 2026-08-01 is Saturday, 2026-08-02 is Sunday, 2026-08-03 is Monday.
    expect($schedule->details()->where('date', '2026-08-01')->first()->shift)->toBe('S1_SAT')
        ->and($schedule->details()->where('date', '2026-08-01')->first()->working_hours)->toBe(5.0)
        ->and($schedule->details()->where('date', '2026-08-02')->first()->shift)->toBe('OFF')
        ->and($schedule->details()->where('date', '2026-08-03')->first()->shift)->toBe('S1')
        ->and($schedule->details()->where('date', '2026-08-03')->first()->working_hours)->toBe(7.0);
});

test('scenario A: monday-friday shift 1 gives 7 effective hours per day', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();

    $weekdays = $schedule->details()
        ->whereIn('date', ['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'])
        ->get();

    expect($weekdays->every(fn ($d) => $d->shift === 'S1'))->toBeTrue()
        ->and($weekdays->every(fn ($d) => $d->working_hours == 7))->toBeTrue();
});

test('scenario B: monday-friday shift 2 gives 7 effective hours per day', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S2',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();

    $weekdays = $schedule->details()
        ->whereIn('date', ['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'])
        ->get();

    expect($weekdays->every(fn ($d) => $d->shift === 'S2'))->toBeTrue()
        ->and($weekdays->every(fn ($d) => $d->working_hours == 7))->toBeTrue();
});

test('scenario C: monday-friday plus saturday short shift totals 40 hours and is valid', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();
    $validation = (new ShiftScheduleService)->validate($schedule);

    // Week 1: Mon-Fri 7h*5 = 35 + Sat 5h = 40.
    expect($validation['weekly_hours']['EMP-001'][1])->toBe(40.0)
        ->and($validation['working_hours_valid'])->toBeTrue()
        ->and($validation['weekly_violations'])->toBeEmpty();
});

test('scenario D: an employee cannot be assigned two shifts on the same date', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();
    $existing = $schedule->details()->where('date', '2026-08-03')->first();

    expect(fn () => $schedule->details()->create([
        'employee_id' => $existing->employee_id,
        'date' => '2026-08-03',
        'week_number' => $existing->week_number,
        'shift' => 'S2',
        'working_hours' => 7,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('scenario E: weekly hours exceeding 40 produce a warning and require review', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();

    // Push a weekday from 7h to 8h so week 1 = 41h > 40.
    $schedule->details()->where('date', '2026-08-03')->update(['working_hours' => 8]);
    $validation = (new ShiftScheduleService)->validate($schedule->fresh());

    expect($validation['working_hours_valid'])->toBeFalse()
        ->and($validation['weekly_violations'])->not->toBeEmpty()
        ->and($validation['exceeding_weekly'])->not->toBeEmpty();
});

test('weekly hour excess with override is a warning but not a blocking error', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();

    $schedule->details()->where('date', '2026-08-03')->update(['working_hours' => 8, 'is_override' => true]);
    $validation = (new ShiftScheduleService)->validate($schedule->fresh());

    expect($validation['working_hours_valid'])->toBeTrue()
        ->and($validation['weekly_violations'])->not->toBeEmpty()
        ->and($validation['exceeding_weekly'][0]['overridden'])->toBeTrue();
});

test('rotating employees alternate shift weekly', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $empA = Employee::query()->create(['employee_code' => 'EMP-001', 'employee_name' => 'A', 'shift_pattern' => 'ROTATING', 'employment_start_date' => '2024-01-01', 'status' => 'ACTIVE']);
    $empB = Employee::query()->create(['employee_code' => 'EMP-002', 'employee_name' => 'B', 'shift_pattern' => 'ROTATING', 'employment_start_date' => '2024-01-01', 'status' => 'ACTIVE']);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);

    $schedule = ShiftSchedule::query()->first();

    $w1a = $schedule->details()->where('employee_id', $empA->id)->where('date', '2026-08-03')->first();
    $w1b = $schedule->details()->where('employee_id', $empB->id)->where('date', '2026-08-03')->first();
    $w2a = $schedule->details()->where('employee_id', $empA->id)->where('date', '2026-08-10')->first();
    $w2b = $schedule->details()->where('employee_id', $empB->id)->where('date', '2026-08-10')->first();

    expect($w1a->shift)->toBe('S1')
        ->and($w1b->shift)->toBe('S2')
        ->and($w2a->shift)->toBe('S2')
        ->and($w2b->shift)->toBe('S1');
});

test('administrator can assign sick and permission via assignment editor', function () {
    seedCalendar();
    seedShiftDefinitions();
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Employee::query()->create([
        'employee_code' => 'EMP-001',
        'employee_name' => 'Andi',
        'shift_pattern' => 'FIXED_S1',
        'employment_start_date' => '2024-01-01',
        'status' => 'ACTIVE',
    ]);

    $this->post(route('administration.shift-schedules.store'), ['month' => 8, 'year' => 2026]);
    $schedule = ShiftSchedule::query()->first();

    $monday = $schedule->details()->where('date', '2026-08-03')->first();
    $tuesday = $schedule->details()->where('date', '2026-08-04')->first();

    $this->post(route('administration.shift-schedules.assignments', $schedule), [
        'assignments' => [
            $monday->id => 'SICK',
            $tuesday->id => 'PERMISSION',
        ],
    ])->assertRedirect(route('administration.shift-schedules.show', $schedule));

    expect($monday->fresh()->shift)->toBe('SICK')
        ->and($monday->fresh()->working_hours)->toBe(0.0)
        ->and($tuesday->fresh()->shift)->toBe('PERMISSION');
});
