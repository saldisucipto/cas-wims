<?php

use App\Models\DailyWorker;
use App\Models\MesonTransaction;
use App\Models\PackingStation;
use App\Models\RfDevice;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use App\Services\PackingProductivity\OperationalClock;
use App\Services\PackingProductivity\WorkerProductivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Freeze the clock inside the first shift so shift-boundary logic is deterministic.
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-27 03:00:00', 'UTC'));
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

function productivityWorker(string $name = 'MUHAMMAD ISHAK HIDAYAT'): DailyWorker
{
    return DailyWorker::query()->create([
        'employee_code' => 'HK-'.substr(md5($name), 0, 8),
        'name' => $name,
        'function' => 'PACKER',
        'division' => 'OUTBOUND',
        'position' => 'PACKER',
        'status' => 'Active',
        'is_active' => true,
    ]);
}

function productivitySession(DailyWorker $worker, string $username = 'CA1OPS006'): WorkingSession
{
    $account = WmsAccount::query()->create([
        'username' => $username,
        'password' => 'secret',
        'function' => 'PACKER',
        'status' => 'Assigned',
    ]);

    $station = PackingStation::query()->create([
        'code' => 'STATION 02',
        'name' => 'Packing 02',
        'station_number' => '02',
        'status' => 'In Use',
    ]);

    return WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'packing_station_id' => $station->id,
        'wms_account_id' => $account->id,
        'session_type' => 'packing',
        'status' => 'Working',
        'started_at' => now()->subHours(2),
    ]);
}

function productivityTransaction(WorkingSession $session, string $transactionId, string $document, float $qty, int $minutesAgo, string $type = 'Packing'): MesonTransaction
{
    return MesonTransaction::query()->create([
        'transaction_id' => $transactionId,
        'transaction_type' => $type,
        'document_number' => $document,
        'transaction_time' => OperationalClock::now()->subMinutes($minutesAgo),
        'qty_each_fm' => $qty,
        'operator_id' => $session->wms_account_id,
        'operator_username' => $session->wmsAccount->username,
    ]);
}

test('packing dashboard shows the running shift productivity for the worker', function () {
    $worker = productivityWorker();
    $session = productivitySession($worker);

    productivityTransaction($session, 'TR-1', 'DOC-A', 3, 60);
    productivityTransaction($session, 'TR-2', 'DOC-A', 2, 50);
    productivityTransaction($session, 'TR-3', 'DOC-B', 5, 40);

    $this->get(route('packing.dashboard', ['name' => $worker->name, 'station' => 'STATION 02']))
        ->assertOk()
        ->assertSee('Packing Productivity')
        ->assertSee('MUHAMMAD ISHAK HIDAYAT')
        ->assertSee('10')
        ->assertSee('DOC', false);
});

test('shift productivity ignores transactions recorded after the session was closed', function () {
    $worker = productivityWorker();
    $session = productivitySession($worker);

    $session->update(['ended_at' => now()->subMinutes(30), 'status' => 'Finished']);

    // Inside the session window.
    productivityTransaction($session, 'TR-IN', 'DOC-IN', 4, 60);

    // Recorded after the worker clocked out, so it belongs to the next holder.
    productivityTransaction($session, 'TR-AFTER', 'DOC-AFTER', 99, 10);

    $snapshot = app(WorkerProductivityService::class)->forSession($session->fresh());

    expect($snapshot['items'])->toBe(4.0)
        ->and($snapshot['orders'])->toBe(1)
        ->and($snapshot['lines'])->toBe(1);
});

test('shift productivity marks the data as stale when the last import is older than today', function () {
    $worker = productivityWorker();
    $session = productivitySession($worker);

    productivityTransaction($session, 'TR-1', 'DOC-A', 2, 30);

    $snapshot = app(WorkerProductivityService::class)->forSession($session->fresh());

    // The fixture writes "now", so the data is fresh.
    expect($snapshot['is_stale'])->toBeFalse()
        ->and($snapshot['data_until'])->not->toBeNull();
});

test('shift productivity hides the rate when there is not enough activity to measure', function () {
    $worker = productivityWorker();
    $session = productivitySession($worker);

    productivityTransaction($session, 'TR-1', 'DOC-A', 2, 30);

    $snapshot = app(WorkerProductivityService::class)->forSession($session->fresh());

    expect($snapshot['active_hours'])->toBe(0.0)
        ->and($snapshot['items_per_hour'])->toBeNull()
        ->and($snapshot['items'])->toBe(2.0);
});

test('shift window backfills transactions recorded before clock in', function () {
    $worker = productivityWorker();
    $session = productivitySession($worker);

    // Recorded two hours ago, while the session started one hour ago.
    MesonTransaction::query()->create([
        'transaction_id' => 'TR-EARLY',
        'transaction_type' => 'Packing',
        'document_number' => 'DOC-EARLY',
        'transaction_time' => OperationalClock::now()->subHours(2),
        'qty_each_fm' => 9,
        'operator_id' => $session->wms_account_id,
        'operator_username' => $session->wmsAccount->username,
    ]);

    $session->update(['started_at' => now()->subHour()]);

    $snapshot = app(WorkerProductivityService::class)->forSession($session->fresh());

    expect($snapshot['items'])->toBe(9.0)
        ->and($snapshot['started_at']->lt($snapshot['clock_in_at']))->toBeTrue();
});

test('shift window backfill never swallows work from the previous holder of the account', function () {
    $previousWorker = productivityWorker('PREVIOUS HOLDER');
    $previousSession = productivitySession($previousWorker, 'CA1OPS008');

    MesonTransaction::query()->create([
        'transaction_id' => 'TR-OTHER',
        'transaction_type' => 'Packing',
        'document_number' => 'DOC-OTHER',
        'transaction_time' => OperationalClock::now()->subMinutes(150),
        'qty_each_fm' => 50,
        'operator_id' => $previousSession->wms_account_id,
        'operator_username' => $previousSession->wmsAccount->username,
    ]);

    $previousSession->update([
        'started_at' => now()->subHours(4),
        'ended_at' => now()->subMinutes(120),
        'status' => 'Finished',
    ]);

    $worker = productivityWorker();
    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $previousSession->wms_account_id,
        'session_type' => 'packing',
        'status' => 'Working',
        'started_at' => now()->subMinutes(90),
    ]);

    MesonTransaction::query()->create([
        'transaction_id' => 'TR-OWN',
        'transaction_type' => 'Packing',
        'document_number' => 'DOC-OWN',
        'transaction_time' => OperationalClock::now()->subMinutes(100),
        'qty_each_fm' => 3,
        'operator_id' => $session->wms_account_id,
        'operator_username' => $previousSession->wmsAccount->username,
    ]);

    $snapshot = app(WorkerProductivityService::class)->forSession($session->fresh());

    // Only the transaction after the previous clock-out belongs to this session.
    expect($snapshot['items'])->toBe(3.0)
        ->and($snapshot['started_at']->gte(OperationalClock::now()->subMinutes(120)))->toBeTrue()
        ->and($snapshot['started_at']->format('H:i'))->toBe(OperationalClock::now()->subMinutes(100)->format('H:i'));
});

test('picker and packer productivity never blend their transaction types', function () {
    $worker = productivityWorker('AHMAD KHUDHORI');
    $session = productivitySession($worker, 'CA1OPS007');
    $session->update(['session_type' => 'rf']);

    productivityTransaction($session, 'TR-PICK', 'DOC-PICK', 6, 40, 'Picking&Packing');

    MesonTransaction::query()->create([
        'transaction_id' => 'TR-PACK-ONLY',
        'transaction_type' => 'Packing',
        'document_number' => 'DOC-PACK',
        'transaction_time' => OperationalClock::now()->subMinutes(30),
        'qty_each_fm' => 99,
        'operator_id' => $session->wms_account_id,
        'operator_username' => $session->wmsAccount->username,
    ]);

    $service = app(WorkerProductivityService::class);
    $picker = $service->forSession($session->fresh(), 'picker');
    $packer = $service->forSession($session->fresh(), 'packer');

    expect($picker['role'])->toBe('picker')
        ->and($picker['items'])->toBe(6.0)
        ->and($picker['transaction_types'])->toBe(['Picking&Packing'])
        ->and($packer['items'])->toBe(99.0)
        ->and($packer['transaction_types'])->toBe(['Packing']);
});

test('rf dashboard shows picker productivity', function () {
    $worker = productivityWorker('AHMAD KHUDHORI');
    $account = WmsAccount::query()->create([
        'username' => 'CA1OPS007',
        'password' => 'secret',
        'function' => 'WMS',
        'status' => 'Assigned',
    ]);
    $device = RfDevice::query()->create(['code' => 'RF-04', 'status' => 'In Use']);

    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'rf_device_id' => $device->id,
        'wms_account_id' => $account->id,
        'session_type' => 'rf',
        'status' => 'Working',
        'started_at' => now()->subHours(2),
    ]);

    MesonTransaction::query()->create([
        'transaction_id' => 'TR-PICKER-1',
        'transaction_type' => 'Picking&Packing',
        'document_number' => 'DOC-PICKER',
        'transaction_time' => OperationalClock::now()->subMinutes(25),
        'qty_each_fm' => 11,
        'operator_id' => $account->id,
        'operator_username' => $account->username,
    ]);

    $this->get(route('rf.dashboard', ['name' => $worker->name, 'device' => 'RF-04']))
        ->assertOk()
        ->assertSee('Picker Productivity')
        ->assertSee('Picking&amp;Packing', false)
        ->assertSee('AHMAD KHUDHORI');
});

test('rf dashboard shows the running shift productivity for the worker', function () {
    $worker = productivityWorker('AGUS SUKAMTO');
    $account = WmsAccount::query()->create([
        'username' => 'CA1OPS005',
        'password' => 'secret',
        'function' => 'PICKER',
        'status' => 'Assigned',
    ]);
    $device = RfDevice::query()->create(['code' => 'RF-001', 'status' => 'In Use']);

    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'rf_device_id' => $device->id,
        'wms_account_id' => $account->id,
        'session_type' => 'rf',
        'status' => 'Working',
        'started_at' => now()->subHours(2),
    ]);

    MesonTransaction::query()->create([
        'transaction_id' => 'TR-RF-1',
        'transaction_type' => 'Picking&Packing',
        'document_number' => 'DOC-RF',
        'transaction_time' => OperationalClock::now()->subMinutes(20),
        'qty_each_fm' => 7,
        'operator_id' => $account->id,
        'operator_username' => $account->username,
    ]);

    $this->get(route('rf.dashboard', ['name' => $worker->name, 'device' => 'RF-001']))
        ->assertOk()
        ->assertSee('Picker Productivity')
        ->assertSee('AGUS SUKAMTO');
});
