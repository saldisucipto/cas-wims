<?php

use App\Http\Controllers\ManpowerPlanningController;
use App\Models\DailyWorker;
use App\Models\DeviceAvailability;
use App\Models\ManpowerActivity;
use App\Models\ManpowerPlanning;
use App\Models\MesonTransaction;
use App\Models\ShiftDefinition;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use App\Services\ManpowerPlanning\ManpowerPlanningEngine;
use App\Services\ManpowerPlanning\PackerManpowerService;
use App\Services\ManpowerPlanning\PickerManpowerService;
use App\Services\ManpowerPlanning\PlanningInput;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 03:00:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * One shift producing $count documents every $step minutes, each document
 * holding $qtyEach pieces, so the measured rate is controllable per worker.
 */
function standardShift(
    string $name,
    string $username,
    string $sessionType,
    string $transactionType,
    int $count,
    int $stepMinutes,
    float $qtyEach,
): DailyWorker {
    $worker = DailyWorker::query()->create([
        'employee_code' => 'PS-'.substr(md5($name), 0, 8),
        'name' => $name,
        'function' => $sessionType === 'rf' ? 'PICKER' : 'PACKER',
        'division' => 'OUTBOUND',
        'position' => $sessionType === 'rf' ? 'PICKER' : 'PACKER',
        'status' => 'Active',
        'is_active' => true,
    ]);

    $account = WmsAccount::query()->create([
        'username' => $username,
        'password' => 'secret',
        'function' => 'WMS',
        'status' => 'Assigned',
    ]);

    $start = Carbon::parse('2026-08-15')->setTime(8, 0);
    $end = $start->copy()->addMinutes(max(0, ($count - 1) * $stepMinutes));

    WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $account->id,
        'session_type' => $sessionType,
        'status' => 'Working',
        'started_at' => $start->copy()->subMinutes(45),
        'ended_at' => $end->copy()->subMinutes(45),
    ]);

    for ($index = 0; $index < $count; $index++) {
        MesonTransaction::query()->create([
            'transaction_id' => $username.'-'.$index,
            'transaction_type' => $transactionType,
            'document_number' => 'DOC-'.$username.'-'.$index,
            'transaction_time' => $start->copy()->addMinutes($index * $stepMinutes),
            'qty_each_fm' => $qtyEach,
            'operator_id' => $account->id,
            'operator_username' => $username,
        ]);
    }

    return $worker;
}

function standardPlanningDate(): Carbon
{
    return Carbon::parse('2026-08-16');
}

test('packer standard defaults to 24 orders per hour and picker to 30', function () {
    $packer = app(PackerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);
    $picker = app(PickerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);

    expect($packer['standard_rate'])->toBe(24.0)
        ->and($packer['standard_unit'] ?? 'order/jam')->toBe('order/jam')
        ->and($picker['standard_rate'])->toBe(30.0)
        ->and($picker['standard_unit'])->toBe('order/jam');
});

test('the standard is overridable through system settings', function () {
    SystemSetting::query()->create(['setting_key' => 'pp_default_packer_orders_per_hour', 'setting_value' => '18']);
    SystemSetting::query()->create(['setting_key' => 'pp_default_picker_orders_per_hour', 'setting_value' => '26']);

    $packer = app(PackerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);
    $picker = app(PickerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);

    expect($packer['standard_rate'])->toBe(18.0)
        ->and($picker['standard_rate'])->toBe(26.0);
});

test('packers at or above 24 orders per hour are ranked above everyone else', function () {
    standardShift('PACKER FAST', 'PS01', 'packing', 'Packing', 121, 1, 1);
    standardShift('PACKER MIDDLE', 'PS02', 'packing', 'Packing', 61, 2, 1);
    standardShift('PACKER SLOW', 'PS03', 'packing', 'Packing', 61, 4, 1);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 3000, 8.0);

    $ranking = array_column($plan['near_standard'], 'name');

    expect($plan['at_standard'])->toBe(2)
        ->and($ranking)->toBe(['PACKER FAST', 'PACKER MIDDLE', 'PACKER SLOW'])
        ->and($plan['near_standard'][0]['meets_standard'])->toBeTrue()
        ->and($plan['near_standard'][1]['meets_standard'])->toBeTrue()
        ->and($plan['near_standard'][2]['meets_standard'])->toBeFalse()
        ->and($plan['near_standard'][2]['gap_percent'])->toBeLessThan(0);
});

test('when nobody hits the standard the closest packers come first', function () {
    standardShift('PACKER NEAR', 'PS11', 'packing', 'Packing', 61, 4, 1);
    standardShift('PACKER FAR', 'PS12', 'packing', 'Packing', 31, 8, 1);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 2000, 8.0);

    expect($plan['at_standard'])->toBe(0)
        ->and($plan['selected'][0]['name'])->toBe('PACKER NEAR')
        ->and($plan['selected'][1]['name'])->toBe('PACKER FAR')
        ->and($plan['note'])->toContain('Belum ada packer yang menyentuh standar 24')
        ->and($plan['standard_headcount'])->toBe(12);
});

test('picker standard of 30 orders per hour is judged on order rate not piece rate', function () {
    standardShift('PICKER ABOVE', 'PS21', 'rf', 'Picking&Packing', 61, 1, 1);
    standardShift('PICKER BELOW', 'PS22', 'rf', 'Picking&Packing', 61, 4, 1);

    $plan = app(PickerManpowerService::class)->plan(standardPlanningDate(), 100, 8.0);

    $byName = collect($plan['near_standard'])->keyBy('name');

    expect($plan['at_standard'])->toBe(1)
        ->and($byName['PICKER ABOVE']['order_rate'])->toBe(61.0)
        ->and($byName['PICKER ABOVE']['meets_standard'])->toBeTrue()
        ->and($byName['PICKER BELOW']['meets_standard'])->toBeFalse()
        ->and($byName['PICKER BELOW']['order_rate'])->toBe(15.25);
});

test('the near standard ranking lists every candidate in standard order', function () {
    standardShift('PACKER FAST', 'PS31', 'packing', 'Packing', 121, 1, 1);
    standardShift('PACKER MIDDLE', 'PS32', 'packing', 'Packing', 61, 2, 1);
    standardShift('PACKER SLOW', 'PS33', 'packing', 'Packing', 61, 4, 1);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 100, 8.0);

    $ranking = array_column($plan['near_standard'], 'name');

    expect($plan['headcount'])->toBe(1)
        ->and($plan['selected'])->toHaveCount(1)
        ->and($ranking)->toBe(['PACKER FAST', 'PACKER MIDDLE', 'PACKER SLOW'])
        ->and($plan['near_standard'][0]['gap_percent'])->toBe(152.1)
        ->and($plan['near_standard'][2]['gap_percent'])->toBe(-36.5);
});

test('saving a planning stores the standard comparison per recommended worker', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    standardShift('PACKER MIDDLE', 'PS41', 'packing', 'Packing', 61, 2, 1);

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 500,
        'planning_date' => '2026-08-16',
        'status' => 'CALCULATED',
    ])->assertRedirect();

    $recommendation = ManpowerPlanning::query()
        ->latest('id')
        ->firstOrFail()
        ->recommendations()
        ->where('role', 'packer')
        ->firstOrFail();

    expect($recommendation->standard_rate)->toBe(24.0)
        ->and($recommendation->order_rate)->toBe(30.5)
        ->and($recommendation->meets_standard)->toBeTrue()
        ->and($recommendation->gap_percent)->toBe(27.1);
});

/**
 * A shift definition with an explicit wall span, so the hours under test are
 * the ones the operator typed in /administration/system/shift-settings.
 */
function standardShiftDefinition(
    string $code,
    string $startTime = '08:00',
    string $endTime = '17:00',
    int $breakMinutes = 60,
    int $sortOrder = 1,
): void {
    ShiftDefinition::query()->create([
        'code' => $code,
        'name' => 'Shift '.$code,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'break_minutes' => $breakMinutes,
        'effective_hours' => 8,
        'sort_order' => $sortOrder,
        'active' => true,
    ]);
}

test('the working day is the wall span minus the break, not the legacy setting', function () {
    SystemSetting::query()->create(['setting_key' => 'effective_working_hours', 'setting_value' => '6.88']);

    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);

    // 08:00-17:00 is nine clock hours; the 60 minute break leaves eight hours
    // of actual work, and eight hours is what produces orders.
    expect($plan['effective_hours'])->toBe(8.0)
        ->and($plan['shift_plan'][0]['capacity_per_person'])->toBe(round(24 * 8 * 0.9, 2))
        ->and($plan['shifts'][0]['wall_hours'])->toBe(9.0)
        ->and($plan['shifts'][0]['productive_hours'])->toBe(8.0);
});

test('every shift definition is exposed with its own wall and productive hours', function () {
    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 30, 2);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);

    expect($plan['shifts'])->toHaveCount(2)
        ->and($plan['shifts'][0]['wall_hours'])->toBe(9.0)
        ->and($plan['shifts'][0]['productive_hours'])->toBe(8.0)
        ->and($plan['shifts'][1]['wall_hours'])->toBe(9.0)
        ->and($plan['shifts'][1]['productive_hours'])->toBe(8.5);
});

test('saturday short shifts are ignored when no regular shift is defined', function () {
    ShiftDefinition::query()->create([
        'code' => 'S1_SAT',
        'name' => 'Saturday Short Shift 1',
        'start_time' => '08:00',
        'end_time' => '14:00',
        'break_minutes' => 60,
        'effective_hours' => 5,
        'is_short_day' => true,
        'sort_order' => 1,
        'active' => true,
    ]);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);

    expect($plan['shifts'])->toHaveCount(1)
        ->and($plan['effective_hours'])->toBe(8.0);
});

test('the planner splits the order over two shifts only when the stations cannot hold the crew', function () {
    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    ManpowerActivity::query()->create([
        'division' => 'Outbound',
        'name' => 'Checking & Packing',
        'code' => 'OB-CHECKPACK',
        'workload_source' => 'Outbound',
        'workload_unit' => 'ORDER',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 24,
        'productivity_unit' => 'order/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 6,
        'allowed_shifts' => 'S1,S2',
        'start_time' => '07:00',
        'end_time' => '23:00',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    DeviceAvailability::query()->create(['device_type' => 'PC', 'ready_quantity' => 6]);

    $fixture = app(PackerManpowerService::class)->plan(standardPlanningDate(), 0, 8.0);
    $perPerson = 24 * 8 * 0.9;

    // 3.000 order / 172,8 per orang = 18 orang, lebih dari 6 meja: pecah 2 shift.
    $overflow = app(PackerManpowerService::class)->plan(standardPlanningDate(), 3000, 8.0);

    expect($fixture['shift_plan'])->toHaveCount(2)
        ->and($fixture['shift_plan'][0]['payload'])->toBe(6)
        ->and($overflow['shift_plan'][0]['headcount'])->toBe(18)
        ->and($overflow['shift_plan'][0]['feasible'])->toBeFalse()
        ->and($overflow['recommended_shift'])->toBe(1)
        ->and($overflow['shift_plan'][1]['headcount'])->toBe(9)
        ->and($overflow['shift_plan'][1]['feasible'])->toBeFalse();
});

test('3000 orders fit one shift when 16 packing stations are ready', function () {
    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    ManpowerActivity::query()->create([
        'division' => 'Outbound',
        'name' => 'Checking & Packing',
        'code' => 'OB-CHECKPACK',
        'workload_source' => 'Outbound',
        'workload_unit' => 'ORDER',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 24,
        'productivity_unit' => 'order/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 300,
        'allowed_shifts' => 'S1,S2',
        'start_time' => '07:00',
        'end_time' => '23:00',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    DeviceAvailability::query()->create(['device_type' => 'PC', 'ready_quantity' => 16]);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 3000, 8.0);

    // 16 meja x 24 order/jam x 8 jam produktif x 0,9 allowance = 2.764,8 order.
    // 3.000 order memang butuh 18 packer, jadi 16 meja belum menutup 1 shift.
    expect($plan['shift_plan'][0]['payload'])->toBe(16)
        ->and($plan['shift_plan'][0]['headcount'])->toBe(18)
        ->and($plan['shift_plan'][0]['capacity_per_person'])->toBe(172.8)
        ->and($plan['shift_plan'][0]['capacity_total'])->toBe(3110.4)
        ->and($plan['shift_plan'][1]['headcount'])->toBe(9)
        ->and($plan['shift_plan'][1]['feasible'])->toBeTrue();
});

test('3000 orders close in one shift when the crew fits the 16 ready stations', function () {
    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    ManpowerActivity::query()->create([
        'division' => 'Outbound',
        'name' => 'Checking & Packing',
        'code' => 'OB-CHECKPACK',
        'workload_source' => 'Outbound',
        'workload_unit' => 'ORDER',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 24,
        'productivity_unit' => 'order/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 300,
        'allowed_shifts' => 'S1,S2',
        'start_time' => '07:00',
        'end_time' => '23:00',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    DeviceAvailability::query()->create(['device_type' => 'PC', 'ready_quantity' => 16]);

    // 2.640 order = 16 packer x 172,8, pas di 16 meja: satu shift cukup.
    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 2640, 8.0);

    expect($plan['recommended_shift'])->toBe(1)
        ->and($plan['shift_plan'][0]['headcount'])->toBe(16)
        ->and($plan['shift_plan'][0]['feasible'])->toBeTrue()
        ->and($plan['shift_plan'][0]['payload'])->toBe(16);
});

test('3000 orders need 16 packers on one shift sized against the shift hours', function () {
    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    $plan = app(PackerManpowerService::class)->plan(standardPlanningDate(), 3000, 8.0);

    // 24 order/jam x 8 jam efektif = 192 order per orang (sebelum allowance).
    // Tanpa kandidat terukur, hanya kapasitas standar yang bisa dilaporkan.
    expect($plan['effective_hours'])->toBe(8.0)
        ->and($plan['standard_rate'])->toBe(24.0)
        ->and($plan['shift_plan'][0]['capacity_per_person'])->toBe(172.8)
        ->and($plan['standard_headcount'])->toBe(18);
});

test('the engine sizes packing against the shift definition hours, not the legacy setting', function () {
    SystemSetting::query()->create(['setting_key' => 'effective_working_hours', 'setting_value' => '6.88']);

    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    ManpowerActivity::query()->create([
        'division' => 'Outbound',
        'name' => 'Checking & Packing',
        'code' => 'OB-CHECKPACK',
        'workload_source' => 'Outbound',
        'workload_unit' => 'ORDER',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 24,
        'productivity_unit' => 'order/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 300,
        'allowed_shifts' => 'S1,S2',
        'start_time' => '07:00',
        'end_time' => '23:00',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $controller = app(ManpowerPlanningController::class);
    $resolveConfig = (new ReflectionClass($controller))->getMethod('resolveConfig');
    $resolveConfig->setAccessible(true);
    $config = $resolveConfig->invoke($controller);

    $resolveDevices = (new ReflectionClass($controller))->getMethod('resolveDevices');
    $resolveDevices->setAccessible(true);

    $resolveRules = (new ReflectionClass($controller))->getMethod('resolveDivisionRules');
    $resolveRules->setAccessible(true);

    $result = (new ManpowerPlanningEngine)->run(new PlanningInput(
        inboundVolume: 0,
        outboundVolume: 3000,
        vasVolume: 0,
        effectiveHours: $config['effective_hours'],
        activities: ManpowerActivity::query()->where('is_active', true)->get()->map(fn (ManpowerActivity $activity): array => [
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
        ])->all(),
        devices: $resolveDevices->invoke($controller),
        divisionRules: $resolveRules->invoke($controller),
        shiftHours: $config['shift_hours'],
    ));

    $packing = $result->divisions['Outbound']->activities;
    $packing = collect($packing)->firstWhere('code', 'OB-CHECKPACK');

    // 3.000 / (24 x 8) = 16 packer, bukan 22 yang dihasilkan setelan 6,88 jam.
    expect($config['effective_hours'])->toBe(8.0)
        ->and($config['shift_hours'][1])->toBe(8.0)
        ->and($packing->requiredOneShift)->toBe(16)
        ->and($packing->requiredTwoShifts)->toBe(8);
});

test('the planning page renders the shift recommendation and effective hours', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    standardShiftDefinition('S1', '08:00', '17:00', 60);
    standardShiftDefinition('S2', '13:00', '22:00', 60, 2);

    standardShift('PACKER MIDDLE', 'PS61', 'packing', 'Packing', 61, 2, 1);

    $this->get(route('administration.manpower-planning', [
        'inbound_volume' => 0,
        'outbound_volume' => 100,
        'date' => '2026-08-16',
    ]))
        ->assertOk()
        ->assertSee('Rekomendasi shift')
        ->assertSee('Perbandingan shift')
        ->assertSee('Aktivitas')
        ->assertSee('9 jam');
});

test('the planning page shows the standard and the ranking against it', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    SystemSetting::query()->create(['setting_key' => 'effective_working_hours', 'setting_value' => '6.88']);

    standardShiftDefinition('S1', '08:00', '17:00', 60);

    standardShift('PACKER MIDDLE', 'PS71', 'packing', 'Packing', 61, 2, 1);

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 500,
        'planning_date' => '2026-08-16',
        'status' => 'CALCULATED',
    ])->assertRedirect();

    $planning = ManpowerPlanning::query()->latest('id')->firstOrFail();

    expect((float) $planning->effective_working_hours)->toBe(8.0);
});

test('the planning page shows the ranking against the standard', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    standardShift('PACKER MIDDLE', 'PS51', 'packing', 'Packing', 61, 2, 1);

    $this->get(route('administration.manpower-planning', [
        'inbound_volume' => 0,
        'outbound_volume' => 100,
        'date' => '2026-08-16',
    ]))
        ->assertOk()
        ->assertSee('Sudah di atas standar')
        ->assertSee('Vs standar')
        ->assertSee('Peringkat menuju standar')
        ->assertSee('30.5');
});
