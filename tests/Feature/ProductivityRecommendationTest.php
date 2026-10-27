<?php

use App\Models\DailyWorker;
use App\Models\ManpowerPlanning;
use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use App\Services\ManpowerPlanning\ProductivityRecommender;
use App\Services\PackingProductivity\OperationalClock;
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

/**
 * Create a session plus N orders spaced by $stepMinutes.
 */
function recoSession(DailyWorker $worker, string $username, string $type, int $orders, int $stepMinutes, string $transactionType): WorkingSession
{
    $account = WmsAccount::query()->create([
        'username' => $username,
        'password' => 'secret',
        'function' => 'WMS',
        'status' => 'Assigned',
    ]);

    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $account->id,
        'session_type' => $type,
        'status' => 'Working',
        'started_at' => now()->subHours(6),
    ]);

    for ($i = 0; $i < $orders; $i++) {
        MesonTransaction::query()->create([
            'transaction_id' => $username.'-'.$i,
            'transaction_type' => $transactionType,
            'document_number' => 'DOC-'.$username.'-'.$i,
            'transaction_time' => OperationalClock::now()->subMinutes(300 - ($i * $stepMinutes)),
            'qty_each_fm' => 1,
            'operator_id' => $account->id,
            'operator_username' => $username,
        ]);
    }

    return $session;
}

test('recommender ranks workers by measured orders per hour', function () {
    $fast = recoWorker('FAST PICKER');
    recoSession($fast, 'CA1OPS101', 'rf', 11, 5, 'Picking&Packing');

    $slow = recoWorker('SLOW PICKER');
    recoSession($slow, 'CA1OPS102', 'rf', 11, 10, 'Picking&Packing');

    $recommender = app(ProductivityRecommender::class);
    $performance = $recommender->performance(OperationalClock::today()->addDay(), ProductivityRecommender::ROLE_PICKER);

    $names = array_column($performance['rows'], 'name');

    expect($performance['has_data'])->toBeTrue()
        ->and($names[0])->toBe('FAST PICKER')
        ->and($performance['rows'][0]['orders'])->toBe(11)
        ->and($performance['rows'][0]['trusted'])->toBeTrue();
});

test('recommendation picks only the workers needed to cover the demand', function () {
    $fast = recoWorker('FAST PICKER');
    recoSession($fast, 'CA1OPS101', 'rf', 11, 5, 'Picking&Packing');

    $slow = recoWorker('SLOW PICKER');
    recoSession($slow, 'CA1OPS102', 'rf', 11, 10, 'Picking&Packing');

    $recommender = app(ProductivityRecommender::class);
    $performance = $recommender->performance(OperationalClock::today()->addDay(), ProductivityRecommender::ROLE_PICKER);

    $small = $recommender->recommend(ProductivityRecommender::ROLE_PICKER, 20, 2.0, $performance['rows']);

    expect($small['headcount'])->toBe(1)
        ->and($small['selected'][0]['name'])->toBe('FAST PICKER')
        ->and($small['coverage'])->toBe(100.0)
        ->and($small['shortfall'])->toBe(0);

    $big = $recommender->recommend(ProductivityRecommender::ROLE_PICKER, 60, 2.0, $performance['rows']);

    expect($big['headcount'])->toBe(2)
        ->and(array_column($big['selected'], 'name'))->toBe(['FAST PICKER', 'SLOW PICKER'])
        ->and($big['shortfall'])->toBeGreaterThan(0)
        ->and($big['note'])->toContain('order');
});

test('recommendation returns zero headcount when there is no demand', function () {
    $worker = recoWorker('ANY PICKER');
    recoSession($worker, 'CA1OPS103', 'rf', 11, 5, 'Picking&Packing');

    $recommender = app(ProductivityRecommender::class);
    $performance = $recommender->performance(OperationalClock::today()->addDay(), ProductivityRecommender::ROLE_PICKER);
    $result = $recommender->recommend(ProductivityRecommender::ROLE_PICKER, 0, 7.0, $performance['rows']);

    expect($result['headcount'])->toBe(0)
        ->and($result['selected'])->toBe([]);
});

test('packer recommendation falls back to the configured standard when Meson has no packing data', function () {
    SystemSetting::query()->create([
        'setting_key' => 'pp_default_packer_orders_per_hour',
        'setting_value' => '20',
    ]);

    $recommender = app(ProductivityRecommender::class);
    $performance = $recommender->performance(OperationalClock::today()->addDay(), ProductivityRecommender::ROLE_PACKER);

    $result = $recommender->recommend(ProductivityRecommender::ROLE_PACKER, 100, 5.0, $performance['rows']);

    expect($performance['has_data'])->toBeFalse()
        ->and($result['source'])->toBe('standard')
        ->and($result['headcount'])->toBe(1)
        ->and($result['note'])->toContain('standar');
});

test('recommendation stays unknown when neither Meson nor master standard is available', function () {
    $recommender = app(ProductivityRecommender::class);
    $performance = $recommender->performance(OperationalClock::today()->addDay(), ProductivityRecommender::ROLE_PACKER);
    $result = $recommender->recommend(ProductivityRecommender::ROLE_PACKER, 100, 5.0, $performance['rows']);

    expect($result['source'])->toBe('none')
        ->and($result['headcount'])->toBeNull()
        ->and($result['note'])->toContain('Belum ada');
});

test('manpower planning page shows the recommendations after calculating', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = recoWorker('BELA PICKER');
    recoSession($worker, 'CA1OPS104', 'rf', 11, 5, 'Picking&Packing');

    $this->get(route('administration.manpower-planning', [
        'inbound_volume' => 0,
        'outbound_volume' => 100,
        'date' => '2026-08-16',
    ]))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja')
        ->assertSee('BELA PICKER')
        ->assertSee('Produktivitas aktual Meson');
});

test('saving a planning stores the recommended workers and productivity basis', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = recoWorker('BELA PICKER');
    recoSession($worker, 'CA1OPS105', 'rf', 11, 5, 'Picking&Packing');

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 20,
        'planning_date' => '2026-08-16',
        'status' => 'CALCULATED',
    ])->assertRedirect();

    $planning = ManpowerPlanning::query()->latest('id')->firstOrFail();

    expect($planning->picker_headcount)->toBe(1)
        ->and($planning->recommendation_source)->toBe('measured')
        ->and($planning->productivity_period_start)->not->toBeNull()
        ->and($planning->productivity_period_end)->not->toBeNull()
        ->and($planning->recommendations)->toHaveCount(1)
        ->and($planning->recommendations->first()->name)->toBe('BELA PICKER')
        ->and($planning->recommendations->first()->role)->toBe('picker');
});

test('planning show and print render the recommended daily workers', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = recoWorker('BELA PICKER');
    recoSession($worker, 'CA1OPS106', 'rf', 11, 5, 'Picking&Packing');

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 20,
        'planning_date' => '2026-08-16',
        'status' => 'CALCULATED',
    ])->assertRedirect();

    $planning = ManpowerPlanning::query()->latest('id')->firstOrFail();

    $this->get(route('administration.manpower-planning.show', $planning))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja')
        ->assertSee('Basis produktivitas (data hari sebelumnya)')
        ->assertSee('BELA PICKER')
        ->assertSee('Nama DW');

    $this->get(route('administration.manpower-planning.print', $planning))
        ->assertOk()
        ->assertSee('Rekomendasi Tenaga Kerja (Daily Worker)')
        ->assertSee('Employee Code')
        ->assertSee('BELA PICKER')
        ->assertSee('Basis produktivitas (data hari sebelumnya)');
});
