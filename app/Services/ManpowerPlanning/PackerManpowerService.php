<?php

namespace App\Services\ManpowerPlanning;

use App\Models\ManpowerActivity;
use App\Models\SystemSetting;
use App\Services\ManpowerPlanning\Concerns\ResolvesMesonBasisDays;
use App\Services\PackingProductivity\PackingProductivityService;
use App\Services\PackingProductivity\WorkerProductivityService;
use Carbon\Carbon;

/**
 * Manpower requirement for the packing station only.
 *
 * Packing is measured in orders: one packed document is the unit the warehouse
 * master activity and the Meson packing export both use. This service never
 * reads picking output, and the picker service never reads this one.
 */
class PackerManpowerService
{
    use ResolvesMesonBasisDays;

    public const ROLE = 'packer';

    public const UNIT = 'order';

    public const UNIT_LABEL = 'order/jam';

    private const LABEL = 'Packer';

    private const MASTER_ACTIVITY_CODE = 'OB-CHECKPACK';

    private const ORDERS_SETTING = 'pp_default_packer_orders_per_hour';

    private const BASIS_DAYS = 3;

    private const MIN_ORDERS_FOR_TRUST = 10;

    private const MIN_ACTIVE_HOURS_FOR_TRUST = 0.5;

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
        $demand = max(0, $outboundOrders);

        $days = $this->recentActiveDays(
            $this->packing->packerTransactionTypes(),
            $planningDate->copy()->startOfDay(),
            self::BASIS_DAYS,
        );

        [$from, $to] = $this->basisRange($days);

        $result = [
            'role' => self::ROLE,
            'label' => self::LABEL,
            'unit' => self::UNIT,
            'unit_label' => self::UNIT_LABEL,
            'demand' => $demand,
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
                'note' => 'Tidak ada order pada input, packing tidak perlu tenaga tambahan.',
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
            $orders = (int) ($row['orders'] ?? 0);
            $activeHours = (float) ($row['active_hours'] ?? 0);
            $rate = $activeHours > 0 ? $orders / $activeHours : 0.0;

            if ($orders < self::MIN_ORDERS_FOR_TRUST || $activeHours < self::MIN_ACTIVE_HOURS_FOR_TRUST || $rate <= 0) {
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
                'units' => (int) ($row['orders'] ?? 0),
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
                'Butuh %s packer, hanya %s kandidat terukur yang tersedia. Sisanya harus ditunjuk manual atau diukur ulang.',
                number_format($headcount),
                number_format(count($selected)),
            );
        }

        if ($shortfall > 0) {
            return sprintf(
                'Kapasitas kandidat terukur masih kurang %s order dari demand %s order.',
                number_format($shortfall),
                number_format((int) $result['demand']),
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
        $rate = $this->configuredOrdersPerHour();

        if ($rate === null || $rate <= 0) {
            return [
                ...$result,
                'untracked_pool' => $untracked,
                'source' => 'none',
                'note' => sprintf(
                    'Belum ada transaksi packing di Meson dan standar %s belum diisi, headcount packer belum bisa dihitung.',
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

    private function configuredOrdersPerHour(): ?float
    {
        $configured = $this->settingValue(self::ORDERS_SETTING);

        if ($configured !== null && $configured > 0) {
            return $configured;
        }

        $activity = ManpowerActivity::query()->where('code', self::MASTER_ACTIVITY_CODE)->first();

        if ($activity === null || (float) $activity->productivity_per_hour <= 0) {
            return null;
        }

        $rate = (float) $activity->productivity_per_hour;
        $conversion = (float) $activity->conversion_ratio > 0 ? (float) $activity->conversion_ratio : 1.0;

        // Master activities expressed in pcs/hour are converted to orders/hour.
        if (str_contains(strtolower((string) $activity->productivity_unit), 'pcs')) {
            return $rate / $conversion;
        }

        return $rate;
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
