<?php

use App\Models\DailyWorker;
use App\Models\ManpowerPlanning;
use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use App\Services\ManpowerPlanning\PackerManpowerService;
use App\Services\ManpowerPlanning\PickerManpowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-08-15 03:00:00', 'UTC'));
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

function recoWorker(string $name, string $function = 'PICKER'): DailyWorker
{
    return DailyWorker::query()->create([
        'employee_code' => 'RC-'.substr(md5($name), 0, 8),
        'name' => $name,
        'function' => $function,
        'division' => 'OUTBOUND',
        'position' => $function,
        'status' => 'Active',
        'is_active' => true,
    ]);
}

function recoAccount(string $username): WmsAccount
{
    return WmsAccount::query()->create([
        'username' => $username,
        'password' => 'secret',
        'function' => 'WMS',
        'status' => 'Assigned',
    ]);
}

/**
 * One shift of transactions inside the given wall-clock day, clocking in at
 * 08:00 and producing $count documents every $stepMinutes.
 */
function recoShift(
    DailyWorker $worker,
    string $username,
    string $sessionType,
    Carbon\Carbon $wallDay,
    int $count,
    int $stepMinutes,
    float $qtyEach,
    string $transactionType,
): WorkingSession {
    $account = recoAccount($username);
    $start = $wallDay->copy()->setTime(8, 0);
    $end = $start->copy()->addMinutes(max(0, ($count - 1) * $stepMinutes));

    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $account->id,
        'session_type' => $sessionType,
        'status' => 'Working',
        'started_at' => $start->copy()->subHours(7),
        'ended_at' => $end->copy()->subHours(7),
    ]);

    for ($index = 0; $index < $count; $index++) {
        MesonTransaction::query()->create([
            'transaction_id' => $username.'-'.$wallDay->toDateString().'-'.$index,
            'transaction_type' => $transactionType,
            'document_number' => 'DOC-'.$username.'-'.$wallDay->toDateString().'-'.$index,
            'transaction_time' => $start->copy()->addMinutes($index * $stepMinutes),
            'qty_each_fm' => $qtyEach,
            'operator_id' => $account->id,
            'operator_username' => $username,
        ]);
    }

    return $session;
}

function recoPlanningDate(): Carbon\Carbon
{
    return Carbon\Carbon::parse('2026-08-16');
}

function recoBasisDay(): Carbon\Carbon
{
    return Carbon\Carbon::parse('2026-08-15');
}

test('picker plan measures pieces from picking transactions only', function () {
    $fast = recoWorker('FAST PICKER');
    $middle = recoWorker('MIDDLE PICKER');
    $slow = recoWorker('SLOW PICKER');

    recoShift($fast, 'CA1OPS201', 'rf', recoBasisDay(), 61, 2, 2, 'Picking&Packing');
    recoShift($middle, 'CA1OPS202', 'rf', recoBasisDay(), 61, 3, 2, 'Picking&Packing');
    recoShift($slow, 'CA1OPS203', 'rf', recoBasisDay(), 61, 4, 2, 'Picking&Packing');

    $packer = recoWorker('FAST PACKER', 'PACKER');
    recoShift($packer, 'CA1OPS204', 'packing', recoBasisDay(), 61, 2, 2, 'Packing');

    $plan = app(PickerManpowerService::class)->plan(recoPlanningDate(), 100, 8.0);

    expect($plan['unit'])->toBe('pcs')
        ->and($plan['source'])->toBe('measured')
        ->and($plan['pieces_per_order'])->toBe(2.0)
        ->and($plan['demand'])->toBe(200)
        ->and($plan['pool_size'])->toBe(3)
        ->and($plan['pool_rate'])->toBeGreaterThan(39.0)
        ->and($plan['pool_rate'])->toBeLessThan(42.0)
        ->and($plan['headcount'])->toBe(1)
        ->and($plan['selected'][0]['name'])->toBe('FAST PICKER')
        ->and($plan['coverage'])->toBe(100.0)
        ->and($plan['shortfall'])->toBe(0);
});

test('packer plan is measured in orders and ignores picking transactions', function () {
    $picker = recoWorker('ONLY PICKER');
    recoShift($picker, 'CA1OPS211', 'rf', recoBasisDay(), 61, 2, 2, 'Picking&Packing');

    $first = recoWorker('PACKER ONE', 'PACKER');
    $second = recoWorker('PACKER TWO', 'PACKER');
    recoShift($first, 'CA1OPS212', 'packing', recoBasisDay(), 61, 2, 1, 'Packing');
    recoShift($second, 'CA1OPS213', 'packing', recoBasisDay(), 41, 2, 1, 'Packing');

    $plan = app(PackerManpowerService::class)->plan(recoPlanningDate(), 1000, 8.0);

    expect($plan['unit'])->toBe('order')
        ->and($plan['demand'])->toBe(1000)
        ->and($plan['pool_size'])->toBe(2)
        ->and($plan['pool_rate'])->toBeGreaterThan(29.0)
        ->and($plan['pool_rate'])->toBeLessThan(32.0)
        ->and($plan['headcount'])->toBeGreaterThan(2)
        ->and(array_column($plan['selected'], 'name'))->not->toContain('ONLY PICKER')
        ->and($plan['note'])->toContain('hanya 2 kandidat');
});

test('capacity per person applies the non productive allowance', function () {
    $worker = recoWorker('PICKER ONE');
    recoShift($worker, 'CA1OPS221', 'rf', recoBasisDay(), 61, 2, 1, 'Picking&Packing');

    $plan = app(PickerManpowerService::class)->plan(recoPlanningDate(), 10, 8.0);

    expect($plan['allowance'])->toBe(0.10)
        ->and($plan['pool_rate'])->toBe(30.5)
        ->and($plan['capacity_per_person'])->toBe(round($plan['pool_rate'] * 8.0 * 0.9, 2))
        ->and($plan['capacity_per_person'])->toBe(219.6);
});

test('basis averages the three most recent days that hold data', function () {
    $worker = recoWorker('PICKER TEN');
    $latest = recoBasisDay()->copy()->subDay();

    foreach ([4, 3, 2, 1] as $daysAgo) {
        recoShift(
            $worker,
            'CA1OPS23'.$daysAgo,
            'rf',
            recoBasisDay()->copy()->subDays($daysAgo),
            31,
            2,
            1,
            'Picking&Packing',
        );
    }

    $plan = app(PickerManpowerService::class)->plan(recoPlanningDate(), 50, 8.0);

    expect($plan['basis']['days'])->toHaveCount(3)
        ->and($plan['basis']['days'][0])->toBe($latest->toDateString())
        ->and($plan['basis']['days'][2])->toBe($latest->copy()->subDays(2)->toDateString())
        ->and($plan['basis']['from']->toDateString())->toBe($latest->copy()->subDays(2)->toDateString())
        ->and($plan['basis']['to']->toDateString())->toBe($latest->toDateString())
        ->and($plan['basis']['is_exact_previous_day'])->toBeFalse()
        ->and($plan['pool_rate'])->toBe(31.0);
});

test('both roles return zero headcount when the input has no orders', function () {
    $picker = app(PickerManpowerService::class)->plan(recoPlanningDate(), 0, 8.0);
    $packer = app(PackerManpowerService::class)->plan(recoPlanningDate(), 0, 8.0);

    expect($picker['headcount'])->toBe(0)
        ->and($picker['demand'])->toBe(0)
        ->and($packer['headcount'])->toBe(0)
        ->and($packer['demand'])->toBe(0);
});

test('picker plan reports no headcount when Meson and the master standard are both empty', function () {
    $plan = app(PickerManpowerService::class)->plan(recoPlanningDate(), 100, 8.0);

    expect($plan['source'])->toBe('none')
        ->and($plan['headcount'])->toBeNull()
        ->and($plan['note'])->toContain('OB-PICKER');
});

test('packer plan falls back to the configured standard when Meson has no packing data', function () {
    SystemSetting::query()->create([
        'setting_key' => 'pp_default_packer_orders_per_hour',
        'setting_value' => '20',
    ]);

    $plan = app(PackerManpowerService::class)->plan(recoPlanningDate(), 100, 5.0);

    expect($plan['source'])->toBe('standard')
        ->and($plan['pool_rate'])->toBe(20.0)
        ->and($plan['capacity_per_person'])->toBe(90.0)
        ->and($plan['headcount'])->toBe(2)
        ->and($plan['note'])->toContain('standar');
});

test('manpower planning page shows both role recommendations after calculating', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $picker = recoWorker('BELA PICKER');
    recoShift($picker, 'CA1OPS241', 'rf', recoBasisDay(), 11, 5, 1, 'Picking&Packing');

    $packer = recoWorker('BELA PACKER', 'PACKER');
    recoShift($packer, 'CA1OPS242', 'packing', recoBasisDay(), 11, 5, 1, 'Packing');

    $this->get(route('administration.manpower-planning', [
        'inbound_volume' => 0,
        'outbound_volume' => 100,
        'date' => '2026-08-16',
    ]))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja')
        ->assertSee('BELA PICKER')
        ->assertSee('BELA PACKER')
        ->assertSee('Produktivitas aktual Meson')
        ->assertSee('pcs/jam');
});

test('manpower planning page renders both roles when Meson has no data at all', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $this->get(route('administration.manpower-planning', [
        'inbound_volume' => 0,
        'outbound_volume' => 250,
        'date' => '2026-08-16',
    ]))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja')
        ->assertSee('Belum ada data')
        ->assertSee('Belum ada transaksi picking di Meson')
        ->assertSee('Belum ada transaksi packing di Meson');
});

test('saving a planning stores the per role headcount, source and unit', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $picker = recoWorker('BELA PICKER');
    recoShift($picker, 'CA1OPS251', 'rf', recoBasisDay(), 61, 2, 2, 'Picking&Packing');

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 100,
        'planning_date' => '2026-08-16',
        'status' => 'CALCULATED',
    ])->assertRedirect();

    $planning = ManpowerPlanning::query()->latest('id')->firstOrFail();

    expect($planning->picker_source)->toBe('measured')
        ->and($planning->packer_source)->toBe('none')
        ->and($planning->recommendation_source)->toBe('measured')
        ->and($planning->picker_headcount)->toBe(1)
        ->and($planning->packer_headcount)->toBeNull()
        ->and($planning->productivity_period_start)->not->toBeNull()
        ->and($planning->productivity_period_end)->not->toBeNull()
        ->and($planning->recommendations)->toHaveCount(1)
        ->and($planning->recommendations->first()->role)->toBe('picker')
        ->and($planning->recommendations->first()->unit)->toBe('pcs')
        ->and($planning->recommendations->first()->name)->toBe('BELA PICKER');
});

test('planning show and print render the recommended daily workers with their unit', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $picker = recoWorker('BELA PICKER');
    recoShift($picker, 'CA1OPS261', 'rf', recoBasisDay(), 61, 2, 2, 'Picking&Packing');

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 100,
        'planning_date' => '2026-08-16',
        'status' => 'CALCULATED',
    ])->assertRedirect();

    $planning = ManpowerPlanning::query()->latest('id')->firstOrFail();

    $this->get(route('administration.manpower-planning.show', $planning))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja')
        ->assertSee('Basis produktivitas (data hari sebelumnya)')
        ->assertSee('BELA PICKER')
        ->assertSee('Nama DW')
        ->assertSee('pcs/jam');

    $this->get(route('administration.manpower-planning.print', $planning))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja (Daily Worker)')
        ->assertSee('Employee Code')
        ->assertSee('BELA PICKER')
        ->assertSee('Picker (pcs)')
        ->assertSee('Basis produktivitas (data hari sebelumnya)');
});
