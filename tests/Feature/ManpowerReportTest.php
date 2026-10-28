<?php

use App\Models\DailyWorker;
use App\Models\MesonTransaction;
use App\Models\PackingStation;
use App\Models\User;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use App\Services\PackingProductivity\OperationalClock;
use App\Services\PackingProductivity\WorkerProductivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-28 03:00:00', 'UTC'));
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

function reportWorker(string $name = 'REPORT WORKER'): DailyWorker
{
    return DailyWorker::query()->create([
        'employee_code' => 'MR-'.substr(md5($name), 0, 8),
        'name' => $name,
        'function' => 'PICKER',
        'division' => 'OUTBOUND',
        'position' => 'PICKER',
        'status' => 'Active',
        'is_active' => true,
    ]);
}

function reportSession(DailyWorker $worker, int $startHoursAgo, int $endHoursAgo, string $type = 'rf'): WorkingSession
{
    $account = WmsAccount::query()->firstOrCreate(
        ['username' => 'MR-OP-'.$worker->id],
        ['password' => 'secret', 'function' => 'WMS', 'status' => 'Assigned'],
    );

    $station = PackingStation::query()->firstOrCreate(
        ['code' => 'ST-'.$worker->id],
        ['name' => 'Station '.$worker->id, 'station_number' => (string) $worker->id, 'status' => 'In Use'],
    );

    return WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $account->id,
        'packing_station_id' => $type === 'packing' ? $station->id : null,
        'session_type' => $type,
        'status' => 'Finished',
        'started_at' => now()->subHours($startHoursAgo),
        'ended_at' => now()->subHours($endHoursAgo),
    ]);
}

function reportTransaction(WorkingSession $session, string $id, string $document, int $minutesAgo, string $type = 'Picking&Packing'): MesonTransaction
{
    return MesonTransaction::query()->create([
        'transaction_id' => $id,
        'transaction_type' => $type,
        'document_number' => $document,
        'transaction_time' => OperationalClock::now()->subMinutes($minutesAgo),
        'qty_each_fm' => 1,
        'operator_id' => $session->wms_account_id,
        'operator_username' => $session->wmsAccount->username,
    ]);
}

test('manpower report requires administrator', function () {
    $this->get(route('administration.reports.manpower'))
        ->assertRedirect(route('administration.login'));
});

test('manpower report lists one row per worker per date with duration and output', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = reportWorker('REPORT WORKER');
    $session = reportSession($worker, 5, 3); // 2 jam kerja

    reportTransaction($session, 'MR-1', 'DOC-1', 240);
    reportTransaction($session, 'MR-2', 'DOC-2', 200);
    reportTransaction($session, 'MR-3', 'DOC-2', 180);

    $data = app(WorkerProductivityService::class)
        ->dailyPerformance(OperationalClock::today(), OperationalClock::today(), 'picker');

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['name'])->toBe('REPORT WORKER')
        ->and($data['rows'][0]['orders'])->toBe(2)
        ->and($data['rows'][0]['lines'])->toBe(3)
        ->and($data['rows'][0]['duration_hours'])->toBe(2.0)
        ->and($data['rows'][0]['role'])->toBe('picker');

    $this->get(route('administration.reports.manpower', ['period' => 'today']))
        ->assertOk()
        ->assertSee('Manpower Report')
        ->assertSee('REPORT WORKER')
        ->assertSee('Ringkasan per Manpower')
        ->assertSee('Detail per Manpower per Tanggal');
});

test('manpower report splits a session that stays open across days', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = reportWorker('CROSSDAY WORKER');
    $session = reportSession($worker, 40, 14);

    reportTransaction($session, 'CD-1', 'DOC-A', 60 * 39);
    reportTransaction($session, 'CD-2', 'DOC-B', 60 * 15);

    $data = app(WorkerProductivityService::class)
        ->dailyPerformance(OperationalClock::today()->subDays(3), OperationalClock::today(), 'picker');

    $dates = array_column($data['rows'], 'date');

    expect(count($dates))->toBeGreaterThan(1)
        ->and(collect($data['rows'])->every(fn (array $row) => $row['duration_hours'] <= 24))->toBeTrue();
});

test('manpower report can be filtered by role', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $picker = reportWorker('PICKER WORKER');
    $pickerSession = reportSession($picker, 5, 3, 'rf');
    reportTransaction($pickerSession, 'R-1', 'DOC-P', 200);

    $packer = reportWorker('PACKER WORKER');
    $packerSession = reportSession($packer, 5, 3, 'packing');
    reportTransaction($packerSession, 'R-2', 'DOC-K', 200, 'Packing');

    $service = app(WorkerProductivityService::class);

    expect(array_column($service->dailyPerformance(OperationalClock::today(), OperationalClock::today(), 'picker')['rows'], 'name'))
        ->toBe(['PICKER WORKER'])
        ->and(array_column($service->dailyPerformance(OperationalClock::today(), OperationalClock::today(), 'packer')['rows'], 'name'))
        ->toBe(['PACKER WORKER']);
});

test('manpower report print page renders the tables', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = reportWorker('PRINT WORKER');
    $session = reportSession($worker, 5, 3);
    reportTransaction($session, 'P-1', 'DOC-X', 200);
    reportTransaction($session, 'P-2', 'DOC-Y', 180);

    $this->get(route('administration.reports.manpower.print', ['period' => 'today']))
        ->assertOk()
        ->assertSee('Manpower Report')
        ->assertSee('Ringkasan per Manpower')
        ->assertSee('Detail per Manpower per Tanggal')
        ->assertSee('PRINT WORKER')
        ->assertSee('Dicetak');
});
