<?php

namespace App\Services\ManpowerPlanning;

use App\Services\ManpowerPlanning\Concerns\ResolvesMesonBasisDays;
use App\Services\ManpowerPlanning\Concerns\UsesProductivityStandard;
use App\Services\ManpowerPlanning\Concerns\UsesStationCapacity;
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
    use UsesProductivityStandard;
    use UsesStationCapacity;

    public const ROLE = 'packer';

    public const UNIT = 'order';

    public const UNIT_LABEL = 'order/jam';

    /**
     * Warehouse productivity standard: 24 packed orders per worker per hour.
     */
    public const ORDERS_PER_HOUR_STANDARD = 24.0;

    private const LABEL = 'Packer';

    private const MASTER_ACTIVITY_CODE = 'OB-CHECKPACK';

    private const STATION_DEVICE_TYPE = 'PC';

    private const ORDERS_SETTING = 'pp_default_packer_orders_per_hour';

    private const BASIS_DAYS = 3;

    private const MIN_ORDERS_FOR_TRUST = 10;

    private const MIN_ACTIVE_HOURS_FOR_TRUST = 0.5;

    private const MIN_ACTIVE_HOURS_FOR_RANKING = 1.0;

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
        $demand = max(0, $outboundOrders);
        $standard = $this->productivityStandard(self::ORDERS_SETTING, self::ORDERS_PER_HOUR_STANDARD);

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
            'standard_rate' => round($standard, 2),
            'demand' => $demand,
            'effective_hours' => round($this->effectiveHoursForShift(1), 2),
            'allowance' => self::ALLOWANCE,
            'source' => 'none',
            'source_label' => 'Belum ada data',
            'headcount' => null,
            'standard_headcount' => null,
            'recommended_shift' => null,
            'shift_plan' => [],
            'selected' => [],
            'near_standard' => [],
            'pool_rate' => null,
            'pool_size' => 0,
            'ranked_pool' => 0,
            'at_standard' => 0,
            'untracked_pool' => 0,
            'capacity_per_person' => null,
            'capacity_total' => 0.0,
            'coverage' => 0.0,
            'shortfall' => 0,
            'standard_shortfall' => 0,
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
            'shifts' => $this->shiftDefinitions(),
        ];

        $schedule = $this->schedule($from, $to, $demand, $standard);
        $measured = $this->measuredRows($from, $to);
        $optionalPool = $this->optionalPool($from, $to);

        $result = [
            ...$result,
            'effective_hours' => $schedule['hours'],
            'recommended_shift' => $schedule['shifts'],
            'shift_plan' => $schedule['plan'],
            'standard_headcount' => $schedule['standard_headcount'],
        ];

        if ($demand === 0) {
            return [
                ...$result,
                'headcount' => 0,
                'standard_headcount' => 0,
                'note' => 'Tidak ada order pada input, packing tidak perlu tenaga tambahan.',
            ];
        }

        if ($measured['trusted'] === []) {
            $plan = $this->withStandardRate($result, $measured['untracked'], $schedule['hours']);

            return [
                ...$plan,
                'near_standard' => $this->rankAgainstStandard($optionalPool, $standard),
                'ranked_pool' => count($optionalPool),
            ];
        }

        return $this->withMeasuredRate($result, $measured, $optionalPool, $standard, $schedule['hours']);
    }

    /**
     * How many shifts the demand needs, and how many people each one carries.
     *
     * The standard is per worker per hour, so a single shift can only clear
     * `standard x effective hours x (1 - allowance)`. Anything above that has
     * to be spread over a second shift instead of piling everyone into one.
     *
     * @return array{shifts: int, hours: float, capacity_per_person: float, payload: int, headcount: int, standard_headcount: int, plan: array<int, array<string, mixed>>}
     */
    private function schedule(?Carbon $from, ?Carbon $to, int $demand, float $standard): array
    {
        $hours = $this->effectiveHoursForShift(1);
        $capacityPerPerson = $standard * $this->productiveHoursForShift(1) * (1 - self::ALLOWANCE);
        $stations = $this->packingStations();

        $oneShift = $this->singleShiftPlan($demand, $capacityPerPerson, $stations, $hours, 1);
        $twoShift = $this->singleShiftPlan($demand, $capacityPerPerson, $stations, $hours, 2);

        // One shift closes the whole order whenever the crew the standard needs
        // still fits the stations. Only the overflow escalates; when even the
        // split does not fit, the one shift scenario is reported so the note can
        // say the stations themselves are the shortfall.
        $plan = match (true) {
            $oneShift['feasible'] => $oneShift,
            $twoShift['feasible'] => $twoShift,
            default => $oneShift,
        };

        return [
            'shifts' => $plan['shifts'],
            'hours' => $hours,
            'capacity_per_person' => $capacityPerPerson,
            'payload' => $stations,
            'headcount' => $plan['headcount'],
            'standard_headcount' => $oneShift['headcount'],
            'plan' => [
                $oneShift,
                $twoShift,
            ],
        ];
    }

    /**
     * One shift closes the whole demand when the crew needed to hit the standard
     * still fits the packing stations that are physically ready. Only the
     * overflow is spread over a second shift.
     *
     * @return array{shifts: int, payload: int, orders_per_shift: float, capacity_per_person: float, headcount: int, capacity_total: float, coverage: float, projected_hours: float, feasible: bool, label: string}
     */
    private function singleShiftPlan(int $demand, float $capacityPerPerson, int $stations, float $hours, int $shifts = 1): array
    {
        $ordersPerShift = $shifts > 0 ? $demand / $shifts : (float) $demand;
        $headcount = $capacityPerPerson > 0 ? (int) ceil($ordersPerShift / $capacityPerPerson) : 0;
        $capacityTotal = $headcount * $capacityPerPerson;
        $fits = $stations <= 0 || $headcount <= $stations;

        return [
            'shifts' => $shifts,
            'payload' => $stations,
            'orders_per_shift' => round($ordersPerShift, 2),
            'capacity_per_person' => round($capacityPerPerson, 2),
            'headcount' => $headcount,
            'capacity_total' => round($capacityTotal, 2),
            'coverage' => $ordersPerShift > 0 ? round(min(100, $capacityTotal / $ordersPerShift * 100), 1) : 100.0,
            'projected_hours' => $capacityPerPerson > 0 && $headcount > 0
                ? round($ordersPerShift / ($capacityPerPerson * $headcount) * $hours, 2)
                : 0.0,
            'feasible' => $fits,
            'label' => $shifts === 1 ? '1 shift' : '2 shift',
        ];
    }

    /**
     * Packing stations physically ready to run, the hard ceiling on how many
     * packers can work the order at the same time.
     */
    private function packingStations(): int
    {
        return $this->stationCapacity(self::MASTER_ACTIVITY_CODE, self::STATION_DEVICE_TYPE);
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
     * Every worker holding at least one productive hour, used only to show how
     * close each person is to the standard. Volume is not required here: a
     * worker with a short but clean sample can still sit above the bar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function optionalPool(?Carbon $from, ?Carbon $to): array
    {
        if ($from === null || $to === null) {
            return [];
        }

        $rows = [];

        foreach ($this->productivity->performance($from, $to, self::ROLE)['rows'] as $row) {
            $activeHours = (float) ($row['active_hours'] ?? 0);

            if ($activeHours < self::MIN_ACTIVE_HOURS_FOR_RANKING) {
                continue;
            }

            $orders = (int) ($row['orders'] ?? 0);

            $rows[] = [...$row, 'unit_rate' => $orders / $activeHours];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array{trusted: array<int, array<string, mixed>>, untracked: int}  $measured
     * @param  array<int, array<string, mixed>>  $optionalPool
     * @return array<string, mixed>
     */
    private function withMeasuredRate(
        array $result,
        array $measured,
        array $optionalPool,
        float $standard,
        float $effectiveHours,
    ): array {
        $rows = $this->rankAgainstStandard($measured['trusted'], $standard);
        $poolRate = $this->median(array_column($measured['trusted'], 'unit_rate'));
        $capacityPerPerson = $poolRate * $this->productiveHoursForShift(1) * (1 - self::ALLOWANCE);
        $demand = (int) $result['demand'];
        $headcount = $capacityPerPerson > 0 ? (int) ceil($demand / $capacityPerPerson) : null;
        $standardHeadcount = (int) $result['standard_headcount'];

        // Nobody below the standard is picked while a standard-level crew still
        // covers the demand: the standard is the ceiling on how many people the
        // shift may use, not a floor.
        if ($headcount !== null && $standardHeadcount > 0) {
            $headcount = min($headcount, $standardHeadcount);
        }

        $selected = $this->allocate($rows, $demand, $headcount, $this->productiveHoursForShift(1));

        $capacityTotal = array_sum(array_column($selected, 'capacity'));
        $shortfall = (int) ceil(max(0.0, $demand - $capacityTotal));
        $atStandard = count(array_filter($rows, fn (array $row) => $row['meets_standard'] === true));
        $standardCapacity = $standard * $this->productiveHoursForShift(1) * (1 - self::ALLOWANCE);

        return [
            ...$result,
            'source' => 'measured',
            'source_label' => 'Produktivitas aktual Meson',
            'headcount' => $headcount,
            'selected' => $selected,
            'near_standard' => $rows,
            'pool_rate' => round($poolRate, 2),
            'pool_size' => count($rows),
            'ranked_pool' => count($rows),
            'at_standard' => $atStandard,
            'untracked_pool' => $measured['untracked'],
            'capacity_per_person' => round($capacityPerPerson, 2),
            'capacity_total' => round($capacityTotal, 2),
            'coverage' => $demand > 0 ? round(min(100, $capacityTotal / $demand * 100), 1) : 100.0,
            'shortfall' => $shortfall,
            'standard_shortfall' => (int) ceil(max(0.0, $demand - $standardHeadcount * $standardCapacity)),
            'projected_hours' => $demand > 0 && $capacityTotal > 0
                ? round($demand / $capacityTotal * $this->productiveHoursForShift(1), 2)
                : null,
            'note' => $this->measuredNote($result, $headcount, $selected, $shortfall, $atStandard, $standardHeadcount),
        ];
    }

    /**
     * Take the highest ranked workers until the demand is covered.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function allocate(array $rows, int $demand, ?int $headcount, float $effectiveHours): array
    {
        $selected = [];
        $allocatedTotal = 0.0;

        foreach (array_slice($rows, 0, min((int) $headcount, count($rows))) as $index => $row) {
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
                'orders_per_hour' => round($row['unit_rate'], 2),
                'active_hours' => round((float) ($row['active_hours'] ?? 0), 2),
                'days_worked' => (int) ($row['days_worked'] ?? 0),
                'standard_rate' => $row['standard_rate'],
                'meets_standard' => $row['meets_standard'],
                'gap_percent' => $row['gap_percent'],
                'rank' => $index + 1,
                'capacity' => round($capacity, 2),
                'allocated_units' => (int) ceil($allocated),
                'utilization' => $capacity > 0 ? round($allocated / $capacity * 100, 1) : 0.0,
            ];
        }

        return $selected;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $selected
     */
    private function measuredNote(
        array $result,
        ?int $headcount,
        array $selected,
        int $shortfall,
        int $atStandard = 0,
        int $standardHeadcount = 0,
    ): ?string {
        $notes = [];

        $stations = (int) ($result['shift_plan'][0]['payload'] ?? 0);

        if ($atStandard === 0) {
            $notes[] = sprintf(
                'Belum ada packer yang menyentuh standar %s order/jam. Semua di bawah standar, ambil yang terdekat lebih dulu.',
                number_format((float) $result['standard_rate'], 2),
            );
        }

        if ($headcount !== null && $headcount > count($selected)) {
            $notes[] = sprintf(
                'Butuh %s packer, hanya %s kandidat terukur yang tersedia. Sisanya harus ditunjuk manual atau diukur ulang.',
                number_format($headcount),
                number_format(count($selected)),
            );
        }

        if ($shortfall > 0) {
            $notes[] = sprintf(
                'Kandidat terukur paling atas baru menutup %s dari %s order pada standar %s packer x %s jam efektif.',
                number_format(max(0, (int) $result['demand'] - $shortfall)),
                number_format((int) $result['demand']),
                number_format($standardHeadcount),
                number_format((float) $result['effective_hours'], 2),
            );
        }

        if ($standardHeadcount > 0 && $stations > 0 && $standardHeadcount > $stations) {
            $notes[] = sprintf(
                'Jika semua orang hanya pas standar %s order/jam, kebutuhan %s packer melebihi %s meja packing yang ready, jadi order dibagi dua shift.',
                number_format((float) $result['standard_rate'], 2),
                number_format($standardHeadcount),
                number_format($stations),
            );
        }

        return $notes === [] ? null : implode(' ', $notes);
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
            'standard_headcount' => $headcount,
            'untracked_pool' => $untracked,
            'pool_rate' => round($rate, 2),
            'capacity_per_person' => round($capacityPerPerson, 2),
            'capacity_total' => round($headcount * $capacityPerPerson, 2),
            'coverage' => 100.0,
            'shortfall' => 0,
            'standard_shortfall' => 0,
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

        return $this->masterActivityRate(self::MASTER_ACTIVITY_CODE);
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
