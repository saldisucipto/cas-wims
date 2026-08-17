<?php

namespace App\Services\PackingProductivity;

use App\Models\MesonImportBatch;
use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Models\WmsAccount;
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
     * @return array<int, string>
     */
    public function transactionTypes(): array
    {
        $value = SystemSetting::query()->where('setting_key', 'pp_transaction_types')->value('setting_value');

        return collect(explode(',', (string) ($value ?? 'Picking&Packing')))
            ->map(fn ($type) => trim($type))
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
            'per_operator' => $this->aggregateByOperator($rows, $scheduledHours),
            'hourly' => $this->aggregateByHour($rows),
            'daily' => $this->aggregateByDay($rows),
        ];
    }

    public function scheduledHours(Carbon $start, Carbon $end): float
    {
        $workingDays = 0;

        for ($date = $start->copy()->startOfDay(); $date->lte($end->copy()->startOfDay()); $date->addDay()) {
            if ($date->dayOfWeekIso !== 7) {
                $workingDays++;
            }
        }

        return $workingDays * $this->scheduledHoursPerDay();
    }

    /**
     * Estimated active minutes from a sorted list of transaction timestamps,
     * subtracting gaps that exceed the configured inactivity threshold.
     *
     * @param  Collection<int, Carbon>  $timestamps
     */
    public function estimatedActiveMinutes(Collection $timestamps, int $thresholdMinutes): int
    {
        $timestamps = $timestamps->filter()->sort()->values();

        if ($timestamps->count() < 2) {
            return 0;
        }

        $total = max(0, $timestamps->first()->diffInMinutes($timestamps->last()));
        $previous = $timestamps->first();

        foreach ($timestamps->slice(1) as $timestamp) {
            $gap = $previous->diffInMinutes($timestamp);

            if ($gap > $thresholdMinutes) {
                $total -= $gap;
            }

            $previous = $timestamp;
        }

        return max(0, $total);
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

        $operatorMap = WmsAccount::query()
            ->whereIn('username', array_values(array_unique(array_filter($operators))))
            ->pluck('id', 'username');

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

            $insertRows[] = $this->toAttributes($entry['row'], $entry['time'], $operatorId, $operator);
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
    private function toAttributes(array $row, Carbon $time, ?int $operatorId, string $operator): array
    {
        $now = now();

        $attributes = [
            'transaction_time' => $time,
            'system_time' => $this->parseTransactionTime($row['system_time'] ?? null),
            'operator_id' => $operatorId,
            'operator_username' => $operator !== '' ? $operator : null,
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
        $query = MesonTransaction::query()->with('operator');

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

        if (! empty($filters['function'])) {
            $query->whereHas('operator', fn ($q) => $q->where('function', $filters['function']));
        }

        if (! empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
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
        $orders = $rows->pluck('document_number')->filter()->unique()->count();
        $lines = $rows->count();
        $items = $rows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0);
        $operators = $rows->pluck('operator_id')->filter()->unique()->count();

        return [
            'total_orders' => $orders,
            'total_lines' => $lines,
            'total_items' => (float) $items,
            'total_operators' => $operators,
            'scheduled_hours' => $scheduledHours,
            'orders_per_hour' => $this->rate($orders, $scheduledHours),
            'lines_per_hour' => $this->rate($lines, $scheduledHours),
            'items_per_hour' => $this->rate((float) $items, $scheduledHours),
        ];
    }

    /**
     * @param  Collection<int, MesonTransaction>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByOperator(Collection $rows, float $scheduledHours): array
    {
        $threshold = $this->inactivityThresholdMinutes();
        $result = [];

        foreach ($rows->groupBy('operator_id') as $operatorId => $operatorRows) {
            if ($operatorId === null || $operatorId === '') {
                continue;
            }

            $orders = $operatorRows->pluck('document_number')->filter()->unique()->count();
            $lines = $operatorRows->count();
            $items = $operatorRows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0);
            $activeMinutes = $this->estimatedActiveMinutes($operatorRows->pluck('transaction_time'), $threshold);
            $operator = $operatorRows->first()->operator;

            $result[] = [
                'operator_id' => $operatorId,
                'username' => $operator?->username ?? '?',
                'function' => $operator?->function ?? '-',
                'orders' => $orders,
                'lines' => $lines,
                'items' => (float) $items,
                'orders_per_hour' => $this->rate($orders, $scheduledHours),
                'lines_per_hour' => $this->rate($lines, $scheduledHours),
                'items_per_hour' => $this->rate((float) $items, $scheduledHours),
                'estimated_active_hours' => round($activeMinutes / 60, 2),
            ];
        }

        usort($result, fn ($a, $b) => $b['items'] <=> $a['items']);

        return $result;
    }

    /**
     * @param  Collection<int, MesonTransaction>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByHour(Collection $rows): array
    {
        $result = [];

        foreach ($rows->groupBy(fn (MesonTransaction $row) => $row->transaction_time?->format('H:00')) as $hour => $hourRows) {
            if ($hour === null) {
                continue;
            }

            $result[] = [
                'hour' => $hour,
                'orders' => $hourRows->pluck('document_number')->filter()->unique()->count(),
                'lines' => $hourRows->count(),
                'items' => (float) $hourRows->sum(fn (MesonTransaction $row) => $row->qty_each_fm ?? 0),
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['hour'], $b['hour']));

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
