<?php

use App\Models\DailyWorker;
use App\Models\PackingStation;
use App\Models\User;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('working sessions report auto force closes active sessions from previous day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-04 08:00:00'));

    $admin = User::factory()->create(['role' => 'Administrator']);

    $worker = DailyWorker::query()->create([
        'name' => 'Andi Pratama',
        'function' => 'Outbound',
        'division' => 'Packer',
        'position' => 'Packer',
        'is_active' => true,
    ]);

    $wmsAccount = WmsAccount::query()->create([
        'username' => 'CA1OPS100',
        'password' => 'secret',
        'function' => 'Outbound',
        'status' => 'Assigned',
    ]);

    $station = PackingStation::query()->create([
        'code' => 'PS-001',
        'name' => 'Station 1',
        'status' => 'In Use',
        'wms_account_id' => $wmsAccount->id,
    ]);

    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'packing_station_id' => $station->id,
        'wms_account_id' => $wmsAccount->id,
        'session_type' => 'packing',
        'status' => 'Working',
        'started_at' => Carbon::parse('2026-09-03 23:00:00'),
    ]);

    $this->actingAs($admin)
        ->get(route('administration.reports.working-sessions'))
        ->assertOk();

    $this->assertDatabaseHas('working_sessions', [
        'id' => $session->id,
        'status' => 'Closed',
        'close_type' => 'Force Close',
        'force_closed_by' => $admin->id,
        'force_close_reason' => 'Auto force close karena sesi lintas hari.',
    ]);

    $this->assertDatabaseHas('packing_stations', [
        'id' => $station->id,
        'status' => 'Available',
    ]);

    $this->assertDatabaseHas('wms_accounts', [
        'id' => $wmsAccount->id,
        'status' => 'Available',
    ]);

    Carbon::setTestNow();
});

test('working sessions report keeps active sessions from current day open', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-04 08:00:00'));

    $admin = User::factory()->create(['role' => 'Administrator']);

    $worker = DailyWorker::query()->create([
        'name' => 'Budi Santoso',
        'function' => 'Outbound',
        'division' => 'Packer',
        'position' => 'Packer',
        'is_active' => true,
    ]);

    $wmsAccount = WmsAccount::query()->create([
        'username' => 'CA1OPS101',
        'password' => 'secret',
        'function' => 'Outbound',
        'status' => 'Assigned',
    ]);

    $station = PackingStation::query()->create([
        'code' => 'PS-002',
        'name' => 'Station 2',
        'status' => 'In Use',
        'wms_account_id' => $wmsAccount->id,
    ]);

    $session = WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'packing_station_id' => $station->id,
        'wms_account_id' => $wmsAccount->id,
        'session_type' => 'packing',
        'status' => 'Working',
        'started_at' => Carbon::parse('2026-09-04 07:00:00'),
    ]);

    $this->actingAs($admin)
        ->get(route('administration.reports.working-sessions'))
        ->assertOk();

    $this->assertDatabaseHas('working_sessions', [
        'id' => $session->id,
        'status' => 'Working',
        'close_type' => 'Normal',
        'force_closed_by' => null,
        'force_closed_at' => null,
    ]);

    $this->assertDatabaseHas('packing_stations', [
        'id' => $station->id,
        'status' => 'In Use',
    ]);

    $this->assertDatabaseHas('wms_accounts', [
        'id' => $wmsAccount->id,
        'status' => 'Assigned',
    ]);

    Carbon::setTestNow();
});
