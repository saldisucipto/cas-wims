<?php

namespace App\Services\ManpowerPlanning;

use App\Models\ManpowerActivity;
use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Services\ManpowerPlanning\Concerns\ResolvesMesonBasisDays;
use App\Services\PackingProductivity\PackingProductivityService;
use App\Services\PackingProductivity\WorkerProductivityService;
use Carbon\Carbon;

/**
 * Manpower requirement for the picking station only.
 *
 * Picking is measured in pieces because the warehouse master activity and the
 * Meson picking export both express picking work as pcs, so demand, worker rate
 * and shift capacity share one unit. This service never reads packing output,
 * and the packer service never reads this one.
 */
class PickerManpowerService
{
    use ResolvesMesonBasisDays;

    public const ROLE = 'picker';

    public const UNIT = 'pcs';

    public const UNIT_LABEL = 'pcs/jam';

    private const LABEL = 'Picker';

    private const MASTER_ACTIVITY_CODE = 'OB-PICKER';

    private const PIECES_SETTING = 'pp_default_picker_pieces_per_hour';

    private const LEGACY_ORDERS_SETTING = 'pp_default_picker_orders_per_hour';

    /**
     * How many of the most recent picking days are averaged into one rate.
     * A single day is never trusted: a partial import would turn a two hour
     * peak into a full shift capacity.
     */
    private const BASIS_DAYS = 3;

    private const MIN_PIECES_FOR_TRUST = 10;

    private const MIN_ACTIVE_HOURS_FOR_TRUST = 0.5;

    /** Share of the shift lost to breaks, waiting and handover. */
    private const ALLOWANCE = 0.10;

    public function __construct(
        private WorkerProductivityService $productivity,
        private PackingProductivityService $packing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(Carbon $planningDate, int $outboundOrders, float $effectiveHours): array
    {
        $effectiveHours = max(0.5, $effectiveHours);
        $demandOrders = max(0, $outboundOrders);

        $days = $this->recentActiveDays(
            $this->packing->pickerTransactionTypes(),
            $planningDate->copy()->startOfDay(),
            self::BASIS_DAYS,
        );

        [$from, $to] = $this->basisRange($days);
        $piecesPerOrder = $this->piecesPerOrder($from, $to);
        $demand = (int) ceil($demandOrders * $piecesPerOrder);

        $result = [
            'role' => self::ROLE,
            'label' => self::LABEL,
            'unit' => self::UNIT,
            'unit_label' => self::UNIT_LABEL,
            'demand' => $demand,
            'demand_orders' => $demandOrders,
            'pieces_per_order' => round($piecesPerOrder, 2),
            'effective_hours' => round($effectiveHours, 2),
            'allowance' => self::ALLOWANCE,
            'source' => 'none',
            'source_label' => 'Belum ada data',
            'headcount' => null,
            'selected' => [],
            'pool_rate' => null,
            'pool_size' => 0,
            'untracked_pool' => 0,
            'capacity_per_person' => null,
            'capacity_total' => 0.0,
            'coverage' => 0.0,
            'shortfall' => 0,
            'projected_hours' => null,
            'note' => null,
            'basis' => [
                'from' => $from,
                'to' => $to,
                'days' => $days,
                'data_until' => $this->productivity->latestSyncedAt(),
                'is_exact_previous_day' => $days !== []
                    && $days[0] === $planningDate->copy()->subDay()->toDateString(),
            ],
        ];

        if ($demand === 0) {
            return [
                ...$result,
                'headcount' => 0,
                'note' => 'Tidak ada order pada input, picking tidak perlu tenaga tambahan.',
            ];
        }

        $measured = $this->measuredRows($from, $to);

        if ($measured['trusted'] === []) {
            return $this->withStandardRate($result, $measured['untracked'], $effectiveHours);
        }

        return $this->withMeasuredRate($result, $measured, $effectiveHours);
    }

    /**
     * @param  array<int, string>  $days
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function basisRange(array $days): array
    {
        if ($days === []) {
            return [null, null];
        }

        return [
            Carbon::parse($days[count($days) - 1])->startOfDay(),
            Carbon::parse($days[0])->endOfDay(),
        ];
    }

    /**
     * Average pieces per order inside the basis window, so the order demand can
     * be expressed in the same unit as the picking rate.
     */
    private function piecesPerOrder(?Carbon $from, ?Carbon $to): float
    {
        if ($from !== null && $to !== null) {
            $aggregate = MesonTransaction::query()
                ->whereIn('transaction_type', $this->packing->pickerTransactionTypes())
                ->whereBetween('transaction_time', [$from, $to])
                ->selectRaw('COALESCE(SUM(qty_each_fm), 0) AS pieces, COUNT(DISTINCT document_number) AS documents')
                ->first();

            $documents = (int) ($aggregate->documents ?? 0);
            $pieces = (float) ($aggregate->pieces ?? 0);

            if ($documents > 0 && $pieces > 0) {
                return $pieces / $documents;
            }
        }

        $activity = $this->masterActivity();

        if ($activity !== null && (float) $activity->conversion_ratio > 0) {
            return (float) $activity->conversion_ratio;
        }

        return 1.0;
    }

    /**
     * @return array{trusted: array<int, array<string, mixed>>, untracked: int}
     */
    private function measuredRows(?Carbon $from, ?Carbon $to): array
    {
        if ($from === null || $to === null) {
            return ['trusted' => [], 'untracked' => 0];
        }

        $rows = $this->productivity->performance($from, $to, self::ROLE)['rows'];
        $trusted = [];

        foreach ($rows as $row) {
            $pieces = (float) ($row['items'] ?? 0);
            $activeHours = (float) ($row['active_hours'] ?? 0);
            $rate = $activeHours > 0 ? $pieces / $activeHours : 0.0;

            if ($pieces < self::MIN_PIECES_FOR_TRUST || $activeHours < self::MIN_ACTIVE_HOURS_FOR_TRUST || $rate <= 0) {
                continue;
            }

            $trusted[] = [...$row, 'unit_rate' => $rate];
        }

        return ['trusted' => $trusted, 'untracked' => count($rows) - count($trusted)];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array{trusted: array<int, array<string, mixed>>, untracked: int}  $measured
     * @return array<string, mixed>
     */
    private function withMeasuredRate(array $result, array $measured, float $effectiveHours): array
    {
        $rows = $measured['trusted'];
        $poolRate = $this->median(array_column($rows, 'unit_rate'));
        $capacityPerPerson = $poolRate * $effectiveHours * (1 - self::ALLOWANCE);
        $demand = (int) $result['demand'];
        $headcount = $capacityPerPerson > 0 ? (int) ceil($demand / $capacityPerPerson) : null;

        usort($rows, fn (array $a, array $b) => $b['unit_rate'] <=> $a['unit_rate']);

        $selected = [];
        $allocatedTotal = 0.0;

        foreach (array_slice($rows, 0, min((int) $headcount, count($rows))) as $row) {
            $capacity = $row['unit_rate'] * $effectiveHours * (1 - self::ALLOWANCE);
            $allocated = min($capacity, max(0.0, $demand - $allocatedTotal));
            $allocatedTotal += $allocated;

            $selected[] = [
                'daily_worker_id' => $row['daily_worker_id'] ?? null,
                'employee_code' => $row['employee_code'] ?? null,
                'name' => $row['name'] ?? 'Unknown',
                'function' => $row['function'] ?? '-',
                'units' => (int) round((float) ($row['items'] ?? 0)),
                'units_per_hour' => round($row['unit_rate'], 2),
                'active_hours' => round((float) ($row['active_hours'] ?? 0), 2),
                'days_worked' => (int) ($row['days_worked'] ?? 0),
                'capacity' => round($capacity, 2),
                'allocated_units' => (int) ceil($allocated),
                'utilization' => $capacity > 0 ? round($allocated / $capacity * 100, 1) : 0.0,
            ];
        }

        $capacityTotal = array_sum(array_column($selected, 'capacity'));
        $shortfall = (int) ceil(max(0.0, $demand - $capacityTotal));

        return [
            ...$result,
            'source' => 'measured',
            'source_label' => 'Produktivitas aktual Meson',
            'headcount' => $headcount,
            'selected' => $selected,
            'pool_rate' => round($poolRate, 2),
            'pool_size' => count($rows),
            'untracked_pool' => $measured['untracked'],
            'capacity_per_person' => round($capacityPerPerson, 2),
            'capacity_total' => round($capacityTotal, 2),
            'coverage' => $demand > 0 ? round(min(100, $capacityTotal / $demand * 100), 1) : 100.0,
            'shortfall' => $shortfall,
            'projected_hours' => $poolRate > 0 && (int) $headcount > 0
                ? round($demand / ($poolRate * (int) $headcount), 2)
                : null,
            'note' => $this->measuredNote($result, $headcount, $selected, $shortfall),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $selected
     */
    private function measuredNote(array $result, ?int $headcount, array $selected, int $shortfall): ?string
    {
        if ($headcount !== null && $headcount > count($selected)) {
            return sprintf(
                'Butuh %s picker, hanya %s kandidat terukur yang tersedia. Sisanya harus ditunjuk manual atau diukur ulang.',
                number_format($headcount),
                number_format(count($selected)),
            );
        }

        if ($shortfall > 0) {
            return sprintf(
                'Kapasitas kandidat terukur masih kurang %s %s dari demand %s %s.',
                number_format($shortfall),
                self::UNIT,
                number_format((int) $result['demand']),
                self::UNIT,
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withStandardRate(array $result, int $untracked, float $effectiveHours): array
    {
        $rate = $this->configuredPiecesPerHour((float) $result['pieces_per_order']);

        if ($rate === null || $rate <= 0) {
            return [
                ...$result,
                'untracked_pool' => $untracked,
                'source' => 'none',
                'note' => sprintf(
                    'Belum ada transaksi picking di Meson dan standar %s belum diisi, headcount picker belum bisa dihitung.',
                    self::MASTER_ACTIVITY_CODE,
                ),
            ];
        }

        $capacityPerPerson = $rate * $effectiveHours * (1 - self::ALLOWANCE);
        $demand = (int) $result['demand'];
        $headcount = $capacityPerPerson > 0 ? (int) ceil($demand / $capacityPerPerson) : null;

        return [
            ...$result,
            'source' => 'standard',
            'source_label' => 'Standar master activity',
            'headcount' => $headcount,
            'untracked_pool' => $untracked,
            'pool_rate' => round($rate, 2),
            'capacity_per_person' => round($capacityPerPerson, 2),
            'capacity_total' => round($headcount * $capacityPerPerson, 2),
            'coverage' => 100.0,
            'shortfall' => 0,
            'projected_hours' => $headcount !== null && $headcount > 0
                ? round($demand / ($rate * $headcount), 2)
                : null,
            'note' => 'Angka ini memakai standar produktivitas master activity, bukan hasil aktual pekerja.',
        ];
    }

    private function configuredPiecesPerHour(float $piecesPerOrder): ?float
    {
        $configured = $this->settingValue(self::PIECES_SETTING);

        if ($configured !== null && $configured > 0) {
            return $configured;
        }

        $activity = $this->masterActivity();

        if ($activity !== null && (float) $activity->productivity_per_hour > 0) {
            return (float) $activity->productivity_per_hour;
        }

        $legacy = $this->settingValue(self::LEGACY_ORDERS_SETTING);

        if ($legacy !== null && $legacy > 0) {
            return $legacy * max(1.0, $piecesPerOrder);
        }

        return null;
    }

    private function masterActivity(): ?ManpowerActivity
    {
        return ManpowerActivity::query()->where('code', self::MASTER_ACTIVITY_CODE)->first();
    }

    private function settingValue(string $key): ?float
    {
        $value = SystemSetting::query()->where('setting_key', $key)->value('setting_value');

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        $values = array_values(array_filter($values, fn ($value) => $value > 0));

        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }
}
