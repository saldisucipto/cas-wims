<?php

namespace App\Services\PackingProductivity;

use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Models\WorkingSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shift-scoped productivity snapshot for a single worker dashboard.
 *
 * Everything is measured inside the worker's currently running session window,
 * so the numbers reset on every clock-in instead of accumulating per calendar day.
 */
class WorkerProductivityService
{
    public function __construct(private PackingProductivityService $productivity) {}

    /**
     * @return array<string, mixed>
     */
    public function forSession(?WorkingSession $session, ?string $role = null): array
    {
        $role ??= $this->roleForSession($session);
        $types = $this->productivity->typesForRole($role);

        $snapshot = [
            'has_session' => $session !== null,
            'role' => $role,
            'session_type' => $session?->session_type,
            'transaction_types' => $types,
            'started_at' => null,
            'clock_in_at' => null,
            'ends_at' => null,
            'elapsed_hours' => 0.0,
            'orders' => 0,
            'lines' => 0,
            'items' => 0.0,
            'active_hours' => 0.0,
            'items_per_hour' => null,
            'orders_per_hour' => null,
            'rank' => null,
            'rank_total' => 0,
            'data_until' => ($world = $this->latestSyncedAt()),
            'is_stale' => $world === null || $world->toDateString() < OperationalClock::today()->toDateString(),
            'has_data' => false,
        ];

        if ($session === null) {
            return $snapshot;
        }

        $window = $this->sessionWindow($session);
        $snapshot['started_at'] = $window['start'];
        $snapshot['clock_in_at'] = $window['clock_in'];
        $snapshot['ends_at'] = $window['end'];
        $snapshot['elapsed_hours'] = round(max(0, $window['start']->diffInMinutes($window['end'], false)) / 60, 2);

        $rows = $this->transactionsForWindow($session, $window, $types);

        $snapshot['orders'] = $rows->pluck('document_number')->filter()->unique()->count();
        $snapshot['lines'] = $rows->count();
        $snapshot['items'] = (float) $rows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0);
        $snapshot['has_data'] = $rows->isNotEmpty();

        $activeMinutes = $this->productivity->estimatedActiveMinutes(
            $rows->pluck('transaction_time'),
            $this->productivity->inactivityThresholdMinutes(),
        );

        $snapshot['active_hours'] = round($activeMinutes / 60, 2);

        if ($snapshot['active_hours'] > 0) {
            $snapshot['items_per_hour'] = round($snapshot['items'] / $snapshot['active_hours'], 2);
            $snapshot['orders_per_hour'] = round($snapshot['orders'] / $snapshot['active_hours'], 2);
        }

        [$rank, $total] = $this->rankForWindow($session, $window, $snapshot['items'], $role);
        $snapshot['rank'] = $rank;
        $snapshot['rank_total'] = $total;

        return $snapshot;
    }

    /**
     * Picking is performed from the RF handheld, packing from the station.
     */
    public function roleForSession(?WorkingSession $session): string
    {
        return $session?->session_type === 'rf' ? 'picker' : 'packer';
    }

    /**
     * Session window expressed in the Meson wall-clock domain.
     *
     * The window may start before clock-in to cover transactions the worker
     * already produced in Meson while WIMS was still closed. The backfill is
     * fenced so it can never absorb work from another person holding the same
     * operator account: it stops at the previous session's clock-out, at the
     * shift boundary, and at a hard look-back limit.
     *
     * @return array{start: Carbon, end: Carbon, clock_in: Carbon}
     */
    public function sessionWindow(WorkingSession $session): array
    {
        $clockIn = OperationalClock::toWallClock($session->started_at) ?? OperationalClock::now();
        $clockOut = OperationalClock::toWallClock($session->ended_at) ?? OperationalClock::now();

        if ($clockOut->lt($clockIn)) {
            $clockOut = $clockIn->copy();
        }

        return [
            'start' => $this->resolveWindowStart($session, $clockIn),
            'end' => $clockOut,
            'clock_in' => $clockIn,
        ];
    }

    /**
     * Earliest transaction time that still belongs to this session.
     */
    private function resolveWindowStart(WorkingSession $session, Carbon $clockIn): Carbon
    {
        $floor = $this->shiftStartFor($clockIn);

        $previousClockOut = $this->previousClockOut($session);

        if ($previousClockOut !== null && $previousClockOut->gt($floor)) {
            $floor = $previousClockOut;
        }

        $lookBackLimit = $clockIn->copy()->subMinutes($this->graceLookBackMinutes());

        if ($floor->lt($lookBackLimit)) {
            $floor = $lookBackLimit;
        }

        if ($floor->gte($clockIn)) {
            return $clockIn;
        }

        $earliest = $session->wms_account_id === null
            ? null
            : MesonTransaction::query()
                ->where('operator_id', $session->wms_account_id)
                ->whereBetween('transaction_time', [$floor, $clockIn])
                ->min('transaction_time');

        if ($earliest === null) {
            return $clockIn;
        }

        $earliest = Carbon::parse($earliest);

        return $earliest->lt($clockIn) ? $earliest : $clockIn;
    }

    private function previousClockOut(WorkingSession $session): ?Carbon
    {
        if ($session->wms_account_id === null) {
            return null;
        }

        $previous = WorkingSession::query()
            ->where('wms_account_id', $session->wms_account_id)
            ->whereKeyNot($session->getKey())
            ->whereNotNull('ended_at')
            ->where('started_at', '<', $session->started_at)
            ->orderByDesc('started_at')
            ->value('ended_at');

        return $previous ? OperationalClock::toWallClock(Carbon::parse($previous)) : null;
    }

    /**
     * Shift boundary used as the default backfill fence.
     */
    private function shiftStartFor(Carbon $wallClock): Carbon
    {
        $hour = (int) $wallClock->format('H');

        return match (true) {
            $hour >= 7 && $hour < 15 => $wallClock->copy()->setTime(7, 0),
            $hour >= 15 && $hour < 23 => $wallClock->copy()->setTime(15, 0),
            default => $hour < 7
                ? $wallClock->copy()->subDay()->setTime(23, 0)
                : $wallClock->copy()->setTime(23, 0),
        };
    }

    private function graceLookBackMinutes(): int
    {
        return (int) (SystemSetting::query()
            ->where('setting_key', 'pp_grace_lookback_minutes')
            ->value('setting_value') ?? 720);
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $window
     * @param  array<int, string>|null  $types
     * @return Collection<int, MesonTransaction>
     */
    public function transactionsForWindow(WorkingSession $session, array $window, ?array $types = null): Collection
    {
        $query = MesonTransaction::query()
            ->whereBetween('transaction_time', [$window['start'], $window['end']]);

        if ($types !== null) {
            $query->whereIn('transaction_type', $types);
        }

        if ($session->wms_account_id !== null) {
            $query->where('operator_id', $session->wms_account_id);
        } elseif ($session->daily_worker_id !== null) {
            $query->where('daily_worker_id', $session->daily_worker_id);
        } else {
            return collect();
        }

        return $query->get();
    }

    public function latestSyncedAt(): ?Carbon
    {
        $latest = MesonTransaction::query()->max('transaction_time');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * Ranking among the sessions of the same type that share the session's day.
     *
     * @param  array{start: Carbon, end: Carbon}  $window
     * @return array{0: int|null, 1: int}
     */
    private function rankForWindow(WorkingSession $session, array $window, float $items, ?string $role = null): array
    {
        $board = $this->leaderboard($window['start'], null, $role);

        $key = $session->daily_worker_id !== null
            ? 'worker:'.$session->daily_worker_id
            : 'operator:'.$session->wms_account_id;

        $position = collect($board['rows'])->search(fn (array $row) => $row['key'] === $key);

        return [$position === false ? null : $position + 1, count($board['rows'])];
    }

    /**
     * Aggregated performance per worker per operational date.
     *
     * One row represents one person on one day, so the manpower report can show
     * the shift duration, the productive hours and the output side by side.
     *
     * @return array{rows: array<int, array<string, mixed>>, from: Carbon, to: Carbon}
     */
    public function dailyPerformance(Carbon $from, Carbon $to, ?string $role = null): array
    {
        $fromWall = $from->copy()->startOfDay();
        $toWall = $to->copy()->endOfDay();

        $rangeStart = OperationalClock::fromWallClock($fromWall);
        $rangeEnd = OperationalClock::fromWallClock($toWall);
        $types = $this->productivity->typesForRole($role);
        $sessionType = match ($role) {
            'picker' => 'rf',
            'packer' => 'packing',
            default => null,
        };

        $sessions = WorkingSession::query()
            ->with([
                'dailyWorker:id,name,employee_code,function',
                'wmsAccount:id,username,function',
                'packingStation:id,name',
                'rfDevice:id,code',
            ])
            ->whereNotNull('wms_account_id')
            ->when($sessionType, fn ($query) => $query->where('session_type', $sessionType))
            ->where('started_at', '<=', $rangeEnd)
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $rangeStart);
            })
            ->orderBy('started_at')
            ->get();

        $transactionsByOperator = $sessions->isEmpty()
            ? collect()
            : MesonTransaction::query()
                ->whereIn('operator_id', $sessions->pluck('wms_account_id')->unique()->all())
                ->whereBetween('transaction_time', [$fromWall, $toWall])
                ->when($types !== null, fn ($query) => $query->whereIn('transaction_type', $types))
                ->get()
                ->groupBy('operator_id');

        $threshold = $this->productivity->inactivityThresholdMinutes();
        $rows = [];
        $counted = [];

        foreach ($sessions as $session) {
            if ($session->started_at === null) {
                continue;
            }

            $window = $this->sessionWindow($session);
            $workerKey = $session->daily_worker_id !== null
                ? 'worker:'.$session->daily_worker_id
                : 'operator:'.$session->wms_account_id;

            $sessionRows = ($transactionsByOperator->get($session->wms_account_id) ?? collect())
                ->filter(fn (MesonTransaction $row) => $row->transaction_time->between($window['start'], $window['end']))
                ->reject(function (MesonTransaction $row) use ($workerKey, &$counted) {
                    if (isset($counted[$workerKey][$row->transaction_id])) {
                        return true;
                    }

                    $counted[$workerKey][$row->transaction_id] = true;

                    return false;
                });

            // A session may stay open for days (force close), so it is split by
            // A session may stay open for days (force close), so it is split by
            // date. Only dates with real activity, the clock-in date, or the
            // closing date produce a row.
            $clockInDate = $window['start']->toDateString();
            $clockOutDate = $window['end']->toDateString();
            $cursor = $window['start']->copy()->startOfDay();
            $lastDay = $window['end']->copy()->startOfDay();

            while ($cursor->lte($lastDay)) {
                $dayStart = $cursor->copy()->startOfDay();
                $dayEnd = $cursor->copy()->endOfDay();
                $date = $dayStart->toDateString();
                $cursor->addDay();

                if ($date < $fromWall->toDateString() || $date > $toWall->toDateString()) {
                    continue;
                }

                $portionStart = $window['start']->gt($dayStart) ? $window['start']->copy() : $dayStart->copy();
                $portionEnd = $window['end']->lt($dayEnd) ? $window['end']->copy() : $dayEnd->copy();

                if ($portionEnd->lte($portionStart)) {
                    continue;
                }

                $portionRows = $sessionRows
                    ->filter(fn (MesonTransaction $transaction) => $transaction->transaction_time->between($portionStart, $portionEnd));

                $isClockInDate = $date === $clockInDate;
                $isClockOutDate = $session->ended_at !== null && $date === $clockOutDate;

                // Skip days the session merely straddled with no activity at all.
                if ($portionRows->isEmpty() && ! $isClockInDate && ! $isClockOutDate) {
                    continue;
                }

                $key = $workerKey.'|'.$date;

                $rows[$key] ??= [
                    'date' => $date,
                    'daily_worker_id' => $session->daily_worker_id,
                    'employee_code' => $session->dailyWorker?->employee_code,
                    'name' => $session->dailyWorker?->name ?? $session->wmsAccount?->username ?? 'Unknown',
                    'username' => $session->wmsAccount?->username,
                    'function' => $session->dailyWorker?->function ?? $session->wmsAccount?->function ?? '-',
                    'roles' => [],
                    'stations' => [],
                    'devices' => [],
                    'sessions' => 0,
                    'clock_in' => null,
                    'clock_out' => null,
                    'duration_minutes' => 0,
                    'active_minutes' => 0,
                    'orders' => 0,
                    'lines' => 0,
                    'items' => 0.0,
                    'is_working' => false,
                    'cross_day' => false,
                    'documents' => [],
                ];

                $row = &$rows[$key];

                $row['roles'][$session->session_type] = true;
                $row['sessions']++;
                $row['lines'] += $portionRows->count();
                $row['items'] += (float) $portionRows->sum(fn (MesonTransaction $transaction) => $transaction->qty_each_fm ?? 0);
                $row['active_minutes'] += $this->productivity->estimatedActiveMinutes(
                    $portionRows->pluck('transaction_time'),
                    $threshold,
                );

                foreach ($portionRows->pluck('document_number')->filter()->unique() as $document) {
                    $row['documents'][$document] = true;
                }

                if ($session->packingStation?->name) {
                    $row['stations'][$session->packingStation->name] = true;
                }

                if ($session->rfDevice?->code) {
                    $row['devices'][$session->rfDevice->code] = true;
                }

                if ($session->ended_at === null && $date === OperationalClock::now()->toDateString()) {
                    $row['is_working'] = true;
                }

                $crossDay = $clockInDate !== $clockOutDate;

                if ($crossDay) {
                    $row['cross_day'] = true;
                }

                // Clock-in only belongs to the day the session started; on the
                // following days the shift is measured from the first activity,
                // because the session itself was never closed on that day.
                $firstActivity = $portionRows->min('transaction_time');
                $lastActivity = $portionRows->max('transaction_time');

                $dayStartPoint = $isClockInDate
                    ? $window['start']->copy()
                    : ($firstActivity !== null ? Carbon::parse($firstActivity) : $portionStart->copy());

                if ($isClockInDate && $firstActivity !== null) {
                    $firstActivityAt = Carbon::parse($firstActivity);

                    if ($firstActivityAt->lt($dayStartPoint)) {
                        $dayStartPoint = $firstActivityAt;
                    }
                }

                $dayEndPoint = $isClockOutDate
                    ? $window['end']->copy()
                    : ($lastActivity !== null ? Carbon::parse($lastActivity) : $dayStartPoint->copy());

                if ($isClockOutDate && $lastActivity !== null) {
                    $lastActivityAt = Carbon::parse($lastActivity);

                    if ($lastActivityAt->gt($dayEndPoint)) {
                        $dayEndPoint = $lastActivityAt;
                    }
                }

                if ($dayEndPoint->lt($dayStartPoint)) {
                    $dayEndPoint = $dayStartPoint->copy();
                }

                if ($row['clock_in'] === null || $dayStartPoint->lt($row['clock_in'])) {
                    $row['clock_in'] = $dayStartPoint->copy();
                }

                if ($row['clock_out'] === null || $dayEndPoint->gt($row['clock_out'])) {
                    $row['clock_out'] = $dayEndPoint->copy();
                }

                $row['duration_minutes'] += max(0, (int) round($dayStartPoint->diffInMinutes($dayEndPoint, false)));

                unset($row);
            }
        }

        $result = array_map(function (array $row): array {
            $row['orders'] = count($row['documents']);
            $row['duration_hours'] = round($row['duration_minutes'] / 60, 2);
            $row['active_hours'] = round($row['active_minutes'] / 60, 2);
            $row['orders_per_hour'] = $row['active_hours'] > 0 ? round($row['orders'] / $row['active_hours'], 2) : null;
            $row['items_per_hour'] = $row['active_hours'] > 0 ? round($row['items'] / $row['active_hours'], 2) : null;
            $row['role'] = collect(array_keys($row['roles']))
                ->map(fn (string $type) => $type === 'rf' ? 'picker' : 'packer')
                ->unique()
                ->implode('+');
            $row['role'] = $row['role'] === '' ? '-' : $row['role'];
            $row['station'] = implode(', ', array_keys($row['stations']));
            $row['device'] = implode(', ', array_keys($row['devices']));

            unset($row['documents'], $row['roles'], $row['stations'], $row['devices'], $row['duration_minutes'], $row['active_minutes']);

            return $row;
        }, array_values($rows));

        $result = array_values(array_filter($result, function (array $row): bool {
            // Drop rows that carry no work evidence at all (clock-in only, no
            // transaction, already closed): they would pollute the report.
            return $row['duration_hours'] > 0 || $row['orders'] > 0 || $row['is_working'];
        }));

        usort($result, function (array $a, array $b) {
            return [$b['date'], $a['name']] <=> [$a['date'], $b['name']];
        });

        return ['rows' => $result, 'from' => $fromWall, 'to' => $toWall];
    }

    /**
     * Leaderboard for one operational day, aggregated per person.
     *
     * @return array{rows: array<int, array<string, mixed>>, date: Carbon, generated_at: Carbon, data_until: Carbon|null, is_stale: bool}
     */
    public function leaderboard(Carbon $date, ?string $sessionType = null, ?string $role = null): array
    {
        $range = $this->aggregateRange($date, $date, $role, $sessionType);

        $dataUntil = $this->latestSyncedAt();

        return [
            'rows' => $range['rows'],
            'date' => $date->copy()->startOfDay(),
            'generated_at' => OperationalClock::now(),
            'data_until' => $dataUntil,
            'is_stale' => $dataUntil === null || $dataUntil->toDateString() < $date->toDateString(),
        ];
    }

    /**
     * Aggregated performance of every worker across a date range.
     *
     * Used by the manpower planner to rank real people by measured output.
     *
     * @return array{rows: array<int, array<string, mixed>>, from: Carbon, to: Carbon}
     */
    public function performance(Carbon $from, Carbon $to, ?string $role = null, ?string $sessionType = null): array
    {
        $sessionType ??= match ($role) {
            'picker' => 'rf',
            'packer' => 'packing',
            default => null,
        };

        return $this->aggregateRange($from, $to, $role, $sessionType);
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, from: Carbon, to: Carbon}
     */
    private function aggregateRange(Carbon $from, Carbon $to, ?string $role, ?string $sessionType): array
    {
        $fromWall = $from->copy()->startOfDay();
        $toWall = $to->copy()->endOfDay();

        $rangeStart = OperationalClock::fromWallClock($fromWall);
        $rangeEnd = OperationalClock::fromWallClock($toWall);
        $types = $this->productivity->typesForRole($role);

        $sessions = WorkingSession::query()
            ->with(['dailyWorker:id,name,function,position', 'wmsAccount:id,username,function'])
            ->whereNotNull('wms_account_id')
            ->when($sessionType, fn ($query) => $query->where('session_type', $sessionType))
            ->where('started_at', '<=', $rangeEnd)
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $rangeStart);
            })
            ->orderBy('started_at')
            ->get();

        $transactionsByOperator = $sessions->isEmpty()
            ? collect()
            : MesonTransaction::query()
                ->whereIn('operator_id', $sessions->pluck('wms_account_id')->unique()->all())
                ->whereBetween('transaction_time', [$fromWall, $toWall])
                ->when($types !== null, fn ($query) => $query->whereIn('transaction_type', $types))
                ->get()
                ->groupBy('operator_id');

        $threshold = $this->productivity->inactivityThresholdMinutes();
        $rows = [];
        $counted = [];

        foreach ($sessions as $session) {
            $window = $this->sessionWindow($session);
            $key = $session->daily_worker_id !== null
                ? 'worker:'.$session->daily_worker_id
                : 'operator:'.$session->wms_account_id;

            $sessionRows = ($transactionsByOperator->get($session->wms_account_id) ?? collect())
                ->filter(fn (MesonTransaction $row) => $row->transaction_time->between($window['start'], $window['end']))
                ->reject(function (MesonTransaction $row) use ($key, &$counted) {
                    // Two overlapping sessions of the same worker must never
                    // count the same transaction twice.
                    if (isset($counted[$key][$row->transaction_id])) {
                        return true;
                    }

                    $counted[$key][$row->transaction_id] = true;

                    return false;
                });

            $rows[$key] ??= [
                'key' => $key,
                'daily_worker_id' => $session->daily_worker_id,
                'employee_code' => $session->dailyWorker?->employee_code,
                'name' => $session->dailyWorker?->name ?? $session->wmsAccount?->username ?? 'Unknown',
                'username' => $session->wmsAccount?->username,
                'function' => $session->dailyWorker?->function ?? $session->wmsAccount?->function ?? '-',
                'sessions' => 0,
                'orders' => 0,
                'lines' => 0,
                'items' => 0.0,
                'active_minutes' => 0,
                'first_in' => null,
                'last_out' => null,
                'is_working' => false,
                'days' => [],
                'documents' => [],
            ];

            $row = &$rows[$key];

            $row['sessions']++;
            $row['lines'] += $sessionRows->count();
            $row['items'] += (float) $sessionRows->sum(fn (MesonTransaction $transaction) => $transaction->qty_each_fm ?? 0);
            $row['active_minutes'] += $this->productivity->estimatedActiveMinutes(
                $sessionRows->pluck('transaction_time'),
                $threshold,
            );

            foreach ($sessionRows->pluck('document_number')->filter()->unique() as $document) {
                $row['documents'][$document] = true;
            }

            if ($window['start'] !== null) {
                $row['days'][$window['start']->toDateString()] = true;
            }

            $row['first_in'] = $row['first_in'] === null || $window['start']->lt($row['first_in'])
                ? $window['start']
                : $row['first_in'];

            $row['last_out'] = $session->ended_at === null
                ? null
                : ($row['last_out'] === null || $window['end']->gt($row['last_out']) ? $window['end'] : $row['last_out']);

            $row['is_working'] = $row['is_working'] || $session->ended_at === null;

            unset($row);
        }

        $result = array_map(function (array $row): array {
            $row['orders'] = count($row['documents']);
            $row['active_minutes'] = (int) round($row['active_minutes']);
            $row['active_hours'] = round($row['active_minutes'] / 60, 2);
            $row['days_worked'] = count($row['days']);

            // Rates are derived from the exact same active_hours value the UI
            // prints, so orders / displayed hours always matches the rate.
            $row['items_per_hour'] = $row['active_hours'] > 0 ? round($row['items'] / $row['active_hours'], 2) : null;
            $row['orders_per_hour'] = $row['active_hours'] > 0 ? round($row['orders'] / $row['active_hours'], 2) : null;
            $row['orders_per_day'] = $row['days_worked'] > 0 ? round($row['orders'] / $row['days_worked'], 2) : null;

            unset($row['documents'], $row['days']);

            return $row;
        }, array_values($rows));

        usort($result, fn (array $a, array $b) => [$b['orders'], $b['items']] <=> [$a['orders'], $a['items']]);

        return ['rows' => $result, 'from' => $fromWall, 'to' => $toWall];
    }
}
