<?php

use App\Models\DailyWorker;
use App\Models\MesonTransaction;
use App\Models\WmsAccount;
use App\Models\WorkingSession;
use App\Services\PackingProductivity\OperationalClock;
use App\Services\PackingProductivity\WorkerProductivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function boardWorker(string $name, string $function = 'PACKER'): DailyWorker
{
    return DailyWorker::query()->create([
        'employee_code' => 'BD-'.substr(md5($name), 0, 8),
        'name' => $name,
        'function' => $function,
        'division' => 'OUTBOUND',
        'position' => 'PACKER',
        'status' => 'Active',
        'is_active' => true,
    ]);
}

function boardSession(DailyWorker $worker, string $username, string $type = 'packing'): WorkingSession
{
    $account = WmsAccount::query()->create([
        'username' => $username,
        'password' => 'secret',
        'function' => $worker->function,
        'status' => 'Assigned',
    ]);

    return WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $account->id,
        'session_type' => $type,
        'status' => 'Working',
        'started_at' => now()->subHours(3),
    ]);
}

function boardTransaction(WorkingSession $session, string $id, string $document, float $qty, int $minutesAgo, string $type = 'Picking&Packing'): MesonTransaction
{
    return MesonTransaction::query()->create([
        'transaction_id' => $id,
        'transaction_type' => $type,
        'document_number' => $document,
        'transaction_time' => OperationalClock::now()->subMinutes($minutesAgo),
        'qty_each_fm' => $qty,
        'operator_id' => $session->wms_account_id,
        'operator_username' => $session->wmsAccount->username,
    ]);
}

test('leaderboard renders picking and packing panels separately', function () {
    $picker = boardWorker('PICKER ALPHA', 'PICKER');
    $pickerSession = boardSession($picker, 'CA1OPS020', 'rf');
    boardTransaction($pickerSession, 'TR-PICK-1', 'DOC-PICK', 7, 40);

    $packer = boardWorker('PACKER BETA', 'PACKER');
    $packerSession = boardSession($packer, 'CA1OPS021', 'packing');
    boardTransaction($packerSession, 'TR-PACK-1', 'DOC-PACK', 5, 30, 'Packing');

    $this->get(route('leaderboard.productivity'))
        ->assertOk()
        ->assertSee('Picking Productivity')
        ->assertSee('Packing Productivity')
        ->assertSee('Transaction type: Picking&amp;Packing', false)
        ->assertSee('Transaction type: Packing', false)
        ->assertSee('PICKER ALPHA')
        ->assertSee('PACKER BETA');
});

test('leaderboard page is public', function () {
    $this->get(route('leaderboard.productivity'))
        ->assertOk()
        ->assertSee('Leaderboard Productivity');
});

test('leaderboard ranks workers by items produced', function () {
    $top = boardWorker('TOP PACKER');
    $topSession = boardSession($top, 'CA1OPS001');
    boardTransaction($topSession, 'TR-1', 'DOC-A', 10, 120);
    boardTransaction($topSession, 'TR-2', 'DOC-B', 15, 60);

    $second = boardWorker('SECOND PACKER');
    $secondSession = boardSession($second, 'CA1OPS002');
    boardTransaction($secondSession, 'TR-3', 'DOC-C', 4, 90);

    $board = app(WorkerProductivityService::class)
        ->leaderboard(OperationalClock::today());

    expect($board['rows'])->toHaveCount(2)
        ->and($board['rows'][0]['name'])->toBe('TOP PACKER')
        ->and($board['rows'][0]['items'])->toBe(25.0)
        ->and($board['rows'][0]['orders'])->toBe(2)
        ->and($board['rows'][1]['name'])->toBe('SECOND PACKER');

    $this->get(route('leaderboard.productivity'))
        ->assertOk()
        ->assertSee('TOP PACKER')
        ->assertSee('SECOND PACKER');
});

test('leaderboard can be scoped to packing or rf sessions', function () {
    $packer = boardWorker('PACKER ONLY');
    $packingSession = boardSession($packer, 'CA1OPS010', 'packing');
    boardTransaction($packingSession, 'TR-1', 'DOC-A', 6, 60);

    $picker = boardWorker('PICKER ONLY', 'PICKER');
    $rfSession = boardSession($picker, 'CA1OPS011', 'rf');
    boardTransaction($rfSession, 'TR-2', 'DOC-B', 9, 30);

    $service = app(WorkerProductivityService::class);

    expect($service->leaderboard(OperationalClock::today(), 'packing')['rows'])->toHaveCount(1)
        ->and($service->leaderboard(OperationalClock::today(), 'packing')['rows'][0]['name'])->toBe('PACKER ONLY')
        ->and($service->leaderboard(OperationalClock::today(), 'rf')['rows'][0]['name'])->toBe('PICKER ONLY');
});

test('leaderboard shows an empty state for a day without sessions', function () {
    $this->get(route('leaderboard.productivity', ['date' => '2026-01-01']))
        ->assertOk()
        ->assertSee('Belum ada output');
});
