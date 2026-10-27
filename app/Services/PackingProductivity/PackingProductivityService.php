<?php

namespace App\Services\PackingProductivity;

use App\Models\MesonImportBatch;
use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Models\WmsAccount;
use App\Models\WorkingCalendar;
use App\Models\WorkingSession;
use App\Services\Import\SpreadsheetReader;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackingProductivityService
{
    /**
     * Columns that must be present in the Meson export before an import can proceed.
     */
    public const REQUIRED_COLUMNS = ['Transaction ID', 'Transaction Time', 'Operator', 'Document number'];

    /**
     * Every Meson type the importer is allowed to store.
     */
    public const DEFAULT_TRANSACTION_TYPES = ['Packing', 'Picking&Packing'];

    /**
     * Picking work is recorded by Meson as Picking&Packing.
     */
    public const DEFAULT_PICKER_TRANSACTION_TYPES = ['Picking&Packing'];

    /**
     * Packing work is recorded by Meson as Packing. The two types are distinct
     * and must never be blended into one productivity figure.
     */
    public const DEFAULT_PACKER_TRANSACTION_TYPES = ['Packing'];

    /**
     * Meson header name => meson_transactions column.
     */
    private const COLUMN_MAP = [
        'Warehouse ID' => 'warehouse_id',
        'Transaction ID' => 'transaction_id',
        'Transaction Type' => 'transaction_type',
        'DOC Type' => 'doc_type',
        'Document number' => 'document_number',
        'Doc Line No' => 'doc_line_no',
        'Status' => 'status',
        'Transaction Time' => 'transaction_time',
        'Customer ID FM' => 'customer_id_fm',
        'SKU FM' => 'sku_fm',
        'LOTNUM FM' => 'lotnum_fm',
        'Location FM' => 'location_fm',
        'Fm Muid' => 'fm_muid',
        'ID FM' => 'id_fm',
        'Pack ID FM' => 'pack_id_fm',
        'UOM FM' => 'uom_fm',
        'QTY FM' => 'qty_fm',
        'QTY Each FM' => 'qty_each_fm',
        'Customer ID TO' => 'customer_id_to',
        'SKU TO' => 'sku_to',
        'LOTNUM TO' => 'lotnum_to',
        'Location TO' => 'location_to',
        'To Muid' => 'to_muid',
        'ID TO' => 'id_to',
        'Pack ID TO' => 'pack_id_to',
        'UOM TO' => 'uom_to',
        'QTY TO' => 'qty_to',
        'QTY Each TO' => 'qty_each_to',
        'Total Price' => 'total_price',
        'Total Net Weight' => 'total_net_weight',
        'Total Gross Weight' => 'total_gross_weight',
        'Total Cubic' => 'total_cubic',
        'UDF01' => 'udf01',
        'UDF02' => 'udf02',
        'UDF03' => 'udf03',
        'UDF04' => 'udf04',
        'UDF05' => 'udf05',
        'System Time' => 'system_time',
        'Operator' => 'operator_username',
        'System Operator' => 'system_operator',
    ];

    private const NUMERIC_COLUMNS = [
        'qty_fm',
        'qty_each_fm',
        'qty_to',
        'qty_each_to',
        'total_price',
        'total_net_weight',
        'total_gross_weight',
        'total_cubic',
    ];

    public function __construct(private SpreadsheetReader $reader) {}

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function preview(string $path, string $extension, Carbon $start, Carbon $end): array
    {
        $analysis = $this->analyze($path, $extension, $start, $end);

        return [
            ...$analysis,
            'existing_count' => $this->existingPeriodCount($start, $end),
            'after_count' => count($analysis['rows']),
            'transaction_types' => $this->transactionTypes(),
        ];
    }

    public function commit(string $path, string $extension, Carbon $start, Carbon $end, string $fileName, ?int $importedBy): MesonImportBatch
    {
        $analysis = $this->analyze($path, $extension, $start, $end);

        // Never delete a period only to insert nothing: a mismatched transaction
        // type filter would otherwise silently wipe the imported history.
        if ($analysis['rows'] === []) {
            throw ValidationException::withMessages([
                'file' => [sprintf(
                    'Tidak ada baris yang lolos filter. Total %d baris: %d di luar periode, %d jenis transaksi tidak cocok (%s), %d waktu tidak valid. Data periode tidak diubah.',
                    $analysis['total_rows'],
                    $analysis['outside_period'],
                    $analysis['excluded_by_type'],
                    implode(', ', $this->transactionTypes()),
                    $analysis['invalid_time'],
                )],
            ]);
        }

        return DB::transaction(function () use ($analysis, $start, $end, $fileName, $importedBy) {
            MesonTransaction::query()
                ->whereBetween('transaction_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->delete();

            foreach (array_chunk($analysis['rows'], 500) as $chunk) {
                MesonTransaction::query()->insert($chunk);
            }

            return MesonImportBatch::query()->create([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'file_name' => $fileName,
                'total_rows' => $analysis['total_rows'],
                'valid_rows' => $analysis['matching_period'],
                'imported_rows' => count($analysis['rows']),
                'duplicate_rows' => $analysis['duplicate_rows'],
                'invalid_operator_rows' => $analysis['invalid_operator_rows'],
                'status' => 'COMPLETED',
                'imported_by' => $importedBy,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // Config
    // ------------------------------------------------------------------

    /**
     * Transaction types accepted by the importer.
     *
     * The import allow-list is the union of every role mapping, so narrowing a
     * role never makes the importer discard rows it used to accept.
     *
     * @return array<int, string>
     */
    public function transactionTypes(): array
    {
        $types = array_merge(
            $this->csvSetting('pp_transaction_types'),
            $this->pickerTransactionTypes(),
            $this->packerTransactionTypes(),
            self::DEFAULT_TRANSACTION_TYPES,
        );

        return array_values(array_unique(array_filter($types)));
    }

    /**
     * Meson transaction types that represent picking work (RF handheld).
     *
     * @return array<int, string>
     */
    public function pickerTransactionTypes(): array
    {
        $types = $this->csvSetting('pp_picker_transaction_types');

        return $types !== [] ? $types : self::DEFAULT_PICKER_TRANSACTION_TYPES;
    }

    /**
     * Meson transaction types that represent packing work (packing station).
     *
     * @return array<int, string>
     */
    public function packerTransactionTypes(): array
    {
        $types = $this->csvSetting('pp_packer_transaction_types');

        return $types !== [] ? $types : self::DEFAULT_PACKER_TRANSACTION_TYPES;
    }

    /**
     * Resolve the transaction types for a productivity role, or null when the
     * role is unknown and no filtering should apply.
     *
     * @return array<int, string>|null
     */
    public function typesForRole(?string $role): ?array
    {
        return match ($role) {
            'picker' => $this->pickerTransactionTypes(),
            'packer' => $this->packerTransactionTypes(),
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function csvSetting(string $key): array
    {
        $value = SystemSetting::query()->where('setting_key', $key)->value('setting_value');

        return collect(explode(',', (string) $value))
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->values()
            ->all();
    }

    public function scheduledHoursPerDay(): float
    {
        return (float) (SystemSetting::query()->where('setting_key', 'pp_scheduled_hours_per_day')->value('setting_value') ?? 7);
    }

    public function inactivityThresholdMinutes(): int
    {
        return (int) (SystemSetting::query()->where('setting_key', 'pp_inactivity_threshold_minutes')->value('setting_value') ?? 30);
    }

    // ------------------------------------------------------------------
    // Productivity calculation (on-the-fly)
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        $rows = $this->queryRows($filters);
        $start = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : ($this->dataStart() ?? now()->startOfDay());
        $end = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : ($this->dataEnd() ?? now()->endOfDay());
        $scheduledHours = $this->scheduledHours($start, $end);

        return [
            'summary' => $this->summarize($rows, $scheduledHours),
            'per_worker' => $this->aggregateByWorker($rows),
            'hourly' => $this->aggregateByHour($rows),
            'daily' => $this->aggregateByDay($rows),
            'role' => $filters['role'] ?? null,
            'transaction_types' => $this->typesForRole($filters['role'] ?? null),
        ];
    }

    /**
     * Scheduled hours for the period, driven by the warehouse working calendar.
     *
     * Falls back to "every day except Sunday" only when the calendar has not been
     * seeded, so Saturday short shifts and non-working days are respected.
     */
    public function scheduledHours(Carbon $start, Carbon $end): float
    {
        $calendar = WorkingCalendar::query()->get()->keyBy('day_of_week');
        $fallbackHoursPerDay = $this->scheduledHoursPerDay();
        $hours = 0.0;

        for ($date = $start->copy()->startOfDay(); $date->lte($end->copy()->startOfDay()); $date->addDay()) {
            $day = $calendar->get($date->dayOfWeekIso);

            if ($day !== null) {
                if ($day->is_working_day) {
                    $hours += (float) $day->working_hours;
                }

                continue;
            }

            if ($date->dayOfWeekIso !== 7) {
                $hours += $fallbackHoursPerDay;
            }
        }

        return round($hours, 2);
    }

    /**
     * Estimated active minutes from a list of transaction timestamps.
     *
     * The span alone overstates activity across idle gaps, while subtracting whole
     * gaps collapses sparse workers to zero. Each interval is therefore capped at
     * the inactivity threshold: a gap never contributes more than one threshold
     * worth of work, and never negative.
     *
     * @param  Collection<int, Carbon>  $timestamps
     */
    public function estimatedActiveMinutes(Collection $timestamps, int $thresholdMinutes): int
    {
        $timestamps = $timestamps->filter()->sort()->values();

        if ($timestamps->count() < 2) {
            return 0;
        }

        $total = 0.0;
        $previous = $timestamps->first();

        foreach ($timestamps->slice(1) as $timestamp) {
            $gap = abs((float) $previous->diffInMinutes($timestamp, false));

            if ($gap > 0) {
                $total += min($gap, (float) $thresholdMinutes);
            }

            $previous = $timestamp;
        }

        return (int) round($total);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function analyze(string $path, string $extension, Carbon $start, Carbon $end): array
    {
        $rows = $this->readFile($path, $extension)['rows'];
        $types = $this->transactionTypes();
        $startTs = $start->copy()->startOfDay();
        $endTs = $end->copy()->endOfDay();

        $totalRows = count($rows);
        $outsidePeriod = 0;
        $excludedByType = 0;
        $invalidTime = 0;
        $operators = [];
        $parsed = [];

        foreach ($rows as $row) {
            $time = $this->parseTransactionTime($row['transaction_time'] ?? null);

            if ($time === null) {
                $invalidTime++;

                continue;
            }

            $type = trim((string) ($row['transaction_type'] ?? ''));

            if (! in_array($type, $types, true)) {
                $excludedByType++;

                continue;
            }

            if ($time->lt($startTs) || $time->gt($endTs)) {
                $outsidePeriod++;

                continue;
            }

            $operator = trim((string) ($row['operator_username'] ?? ''));
            $operators[] = $operator;

            $parsed[] = [
                'row' => $row,
                'time' => $time,
                'operator' => $operator,
            ];
        }

        $uniqueOperators = array_values(array_unique(array_filter($operators)));
        $wmsAccounts = WmsAccount::query()->whereIn('username', $uniqueOperators)->get();
        $operatorMap = $wmsAccounts->pluck('id', 'username');
        $fallbackWorkerByOperatorId = $wmsAccounts->pluck('daily_worker_id', 'id');

        // Preload working sessions for the valid operators, so the person (daily worker)
        // can be resolved per transaction from the actual session, not a static account link.
        // Session timestamps are real instants while the period bounds are wall-clock
        // values, so the query window is widened to absorb any timezone offset.
        $sessionsByOperator = WorkingSession::query()
            ->whereIn('wms_account_id', $operatorMap->values()->filter()->all())
            ->whereBetween('started_at', [$startTs->copy()->subDays(2), $endTs->copy()->addDays(2)])
            ->get(['id', 'daily_worker_id', 'wms_account_id', 'started_at', 'ended_at'])
            ->groupBy('wms_account_id');

        $seen = [];
        $duplicateRows = 0;
        $validOperatorRows = 0;
        $invalidOperatorRows = 0;
        $invalidOperatorValues = [];
        $insertRows = [];

        foreach ($parsed as $entry) {
            $transactionId = trim((string) ($entry['row']['transaction_id'] ?? ''));

            if (isset($seen[$transactionId])) {
                $duplicateRows++;

                continue;
            }

            $seen[$transactionId] = true;

            $operator = $entry['operator'];
            $operatorId = $operator !== '' ? ($operatorMap[$operator] ?? null) : null;

            if ($operatorId === null) {
                $invalidOperatorRows++;

                if ($operator !== '' && ! in_array($operator, $invalidOperatorValues, true)) {
                    $invalidOperatorValues[] = $operator;
                }
            } else {
                $validOperatorRows++;
            }

            $dailyWorkerId = $this->resolveDailyWorker(
                $operatorId,
                $entry['time'],
                $sessionsByOperator->get($operatorId, collect()),
                $fallbackWorkerByOperatorId[$operatorId] ?? null,
            );

            $insertRows[] = $this->toAttributes($entry['row'], $entry['time'], $operatorId, $operator, $dailyWorkerId);
        }

        return [
            'rows' => $insertRows,
            'total_rows' => $totalRows,
            'matching_period' => count($parsed),
            'imported_rows' => count($insertRows),
            'outside_period' => $outsidePeriod,
            'excluded_by_type' => $excludedByType,
            'invalid_time' => $invalidTime,
            'duplicate_rows' => $duplicateRows,
            'valid_operator_rows' => $validOperatorRows,
            'invalid_operator_rows' => $invalidOperatorRows,
            'invalid_operator_values' => $invalidOperatorValues,
        ];
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string|null>>}
     */
    private function readFile(string $path, string $extension): array
    {
        $rows = $this->reader->parsePath($path, $extension);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['Import file is empty.']]);
        }

        $headerRow = array_shift($rows) ?? [];
        $headers = array_map(fn ($value): string => trim((string) $value), $headerRow);
        $indexByHeader = array_flip($headers);

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($indexByHeader)));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => ['Missing required columns: '.implode(', ', $missing)],
            ]);
        }

        $normalized = [];

        foreach ($rows as $row) {
            $mapped = [];
            $nonEmpty = false;

            foreach (self::COLUMN_MAP as $header => $dbColumn) {
                $index = $indexByHeader[$header] ?? null;
                $value = $index !== null ? ($row[$index] ?? null) : null;

                if ($value !== null && trim((string) $value) !== '') {
                    $nonEmpty = true;
                }

                $mapped[$dbColumn] = $value;
            }

            if ($nonEmpty) {
                $normalized[] = $mapped;
            }
        }

        return ['headers' => $headers, 'rows' => $normalized];
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function toAttributes(array $row, Carbon $time, ?int $operatorId, string $operator, ?int $dailyWorkerId): array
    {
        $now = now();

        $attributes = [
            'transaction_time' => $time,
            'system_time' => $this->parseTransactionTime($row['system_time'] ?? null),
            'operator_id' => $operatorId,
            'operator_username' => $operator !== '' ? $operator : null,
            'daily_worker_id' => $dailyWorkerId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($row as $dbColumn => $value) {
            if (in_array($dbColumn, ['transaction_time', 'system_time', 'operator_username', 'operator_id'], true)) {
                continue;
            }

            $trimmed = $value === null ? '' : trim((string) $value);

            if (in_array($dbColumn, self::NUMERIC_COLUMNS, true)) {
                $attributes[$dbColumn] = $trimmed === '' ? null : $this->toFloat($value);
            } else {
                $attributes[$dbColumn] = $trimmed === '' ? null : $trimmed;
            }
        }

        return $attributes;
    }

    /**
     * Resolve the daily worker (person) who performed a transaction from the working
     * session that bound the operator account to a person on that date/shift.
     *
     * `$time` is a naive Meson wall-clock value; session timestamps are real
     * instants, so they are converted before any comparison.
     *
     * @param  Collection<int, WorkingSession>  $sessions
     */
    private function resolveDailyWorker(?int $operatorId, Carbon $time, Collection $sessions, ?int $fallbackWorkerId): ?int
    {
        if ($operatorId === null) {
            return null;
        }

        foreach ($sessions as $session) {
            $start = OperationalClock::toWallClock($session->started_at);
            $end = OperationalClock::toWallClock($session->ended_at) ?? $time->copy()->endOfDay();

            if ($start && $time->between($start, $end)) {
                return $session->daily_worker_id;
            }
        }

        foreach ($sessions as $session) {
            $start = OperationalClock::toWallClock($session->started_at);

            if ($start && $start->toDateString() === $time->toDateString()) {
                return $session->daily_worker_id;
            }
        }

        return $fallbackWorkerId;
    }

    private function existingPeriodCount(Carbon $start, Carbon $end): int
    {
        return MesonTransaction::query()
            ->whereBetween('transaction_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->count();
    }

    private function parseTransactionTime(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Excel serial date/time (e.g. 46279.672).
        if (is_numeric($value) && (float) $value > 1 && (float) $value < 100000) {
            $serial = (float) $value;
            $days = (int) floor($serial);
            $seconds = (int) round(($serial - $days) * 86400);

            return Carbon::create(1899, 12, 30, 0, 0, 0)->addDays($days)->addSeconds($seconds);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat(?string $value): float
    {
        $normalized = str_replace(',', '', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MesonTransaction>
     */
    private function queryRows(array $filters): Collection
    {
        $query = MesonTransaction::query()->with(['operator', 'dailyWorker']);

        if (! empty($filters['start_date'])) {
            $query->where('transaction_time', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (! empty($filters['end_date'])) {
            $query->where('transaction_time', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }

        if (! empty($filters['daily_worker_id'])) {
            $query->where('daily_worker_id', $filters['daily_worker_id']);
        }

        if (! empty($filters['function'])) {
            $query->whereHas('operator', fn ($q) => $q->where('function', $filters['function']));
        }

        if (! empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        $roleTypes = $this->typesForRole($filters['role'] ?? null);

        if ($roleTypes !== null) {
            $query->whereIn('transaction_type', $roleTypes);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, MesonTransaction>  $rows
     * @return array<string, mixed>
     */
    private function summarize(Collection $rows, float $scheduledHours): array
    {
        $threshold = $this->inactivityThresholdMinutes();

        $orders = $rows->pluck('document_number')->filter()->unique()->count();
        $lines = $rows->count();
        $items = (float) $rows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0);
        $operators = $rows->pluck('operator_id')->filter()->unique()->count();
        $workers = $rows->pluck('daily_worker_id')->filter()->unique()->count();

        // Labour hours are the sum of each person's capped-gap active time, so the
        // period throughput is divided by time actually spent working, not by the
        // calendar span of the whole period.
        $laborHours = 0.0;

        foreach ($this->groupRowsByWorker($rows) as $workerRows) {
            $laborHours += $this->estimatedActiveMinutes($workerRows->pluck('transaction_time'), $threshold) / 60;
        }

        $laborHours = round($laborHours, 2);
        $rateBasis = $laborHours > 0 ? $laborHours : $scheduledHours;
        $workerCount = count($this->groupRowsByWorker($rows));
        $capacityHours = $scheduledHours * max(1, $workerCount);

        return [
            'total_orders' => $orders,
            'total_lines' => $lines,
            'total_items' => $items,
            'total_operators' => $operators,
            'total_workers' => $workerCount,
            'scheduled_hours' => $scheduledHours,
            'labor_hours' => $laborHours,
            'utilization' => $capacityHours > 0 ? round($laborHours / $capacityHours * 100, 1) : 0.0,
            'orders_per_hour' => $this->rate($orders, $rateBasis),
            'lines_per_hour' => $this->rate($lines, $rateBasis),
            'items_per_hour' => $this->rate($items, $rateBasis),
        ];
    }

    /**
     * Group transactions by the person who performed them.
     *
     * @param  Collection<int, MesonTransaction>  $rows
     * @return Collection<string, Collection<int, MesonTransaction>>
     */
    private function groupRowsByWorker(Collection $rows): Collection
    {
        return $rows->groupBy(fn (MesonTransaction $row) => $row->daily_worker_id !== null
            ? 'worker:'.$row->daily_worker_id
            : 'operator:'.($row->operator_id ?? 'unknown'));
    }

    /**
     * @param  Collection<int, MesonTransaction>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByWorker(Collection $rows): array
    {
        $threshold = $this->inactivityThresholdMinutes();
        $result = [];

        foreach ($this->groupRowsByWorker($rows) as $workerRows) {
            $first = $workerRows->first();
            $dailyWorker = $first->dailyWorker;
            $operator = $first->operator;

            $orders = $workerRows->pluck('document_number')->filter()->unique()->count();
            $lines = $workerRows->count();
            $items = (float) $workerRows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0);
            $activeMinutes = $this->estimatedActiveMinutes($workerRows->pluck('transaction_time'), $threshold);
            $activeHours = round($activeMinutes / 60, 2);

            // A worker's rate must use their own time, never the period calendar.
            $hasRate = $activeHours > 0;

            $result[] = [
                'daily_worker_id' => $first->daily_worker_id,
                'operator_id' => $first->operator_id,
                'name' => $dailyWorker?->name ?? $operator?->username ?? 'Unknown',
                'username' => $operator?->username,
                'function' => $operator?->function ?? '-',
                'orders' => $orders,
                'lines' => $lines,
                'items' => $items,
                'orders_per_hour' => $hasRate ? $this->rate($orders, $activeHours) : null,
                'lines_per_hour' => $hasRate ? $this->rate($lines, $activeHours) : null,
                'items_per_hour' => $hasRate ? $this->rate($items, $activeHours) : null,
                'estimated_active_hours' => $activeHours,
            ];
        }

        usort($result, fn ($a, $b) => [$b['orders'], $b['items']] <=> [$a['orders'], $a['items']]);

        return $result;
    }

    /**
     * Hourly buckets keyed by date + hour.
     *
     * Merging every day into one 24-hour bucket turns a three-day import into a
     * single inflated bar, so the date is kept as part of the key.
     *
     * @param  Collection<int, MesonTransaction>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByHour(Collection $rows): array
    {
        $result = [];

        foreach ($rows->groupBy(fn (MesonTransaction $row) => $row->transaction_time?->format('Y-m-d H:00')) as $bucket => $hourRows) {
            if ($bucket === null) {
                continue;
            }

            [$date, $hour] = explode(' ', $bucket, 2);

            $result[] = [
                'date' => $date,
                'hour' => $hour,
                'bucket' => $bucket,
                'orders' => $hourRows->pluck('document_number')->filter()->unique()->count(),
                'lines' => $hourRows->count(),
                'items' => (float) $hourRows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0),
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['bucket'], $b['bucket']));

        return $result;
    }

    /**
     * @param  Collection<int, MesonTransaction>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByDay(Collection $rows): array
    {
        $result = [];

        foreach ($rows->groupBy(fn (MesonTransaction $row) => $row->transaction_time?->toDateString()) as $date => $dayRows) {
            if ($date === null) {
                continue;
            }

            $result[] = [
                'date' => $date,
                'orders' => $dayRows->pluck('document_number')->filter()->unique()->count(),
                'lines' => $dayRows->count(),
                'items' => (float) $dayRows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0),
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $result;
    }

    private function rate(float $count, float $hours): float
    {
        if ($hours <= 0) {
            return 0.0;
        }

        return round($count / $hours, 2);
    }

    private function dataStart(): ?Carbon
    {
        $value = MesonTransaction::query()->min('transaction_time');

        return $value ? Carbon::parse($value) : null;
    }

    private function dataEnd(): ?Carbon
    {
        $value = MesonTransaction::query()->max('transaction_time');

        return $value ? Carbon::parse($value) : null;
    }
}
