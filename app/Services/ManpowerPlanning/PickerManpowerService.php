<?php

namespace App\Services\ManpowerPlanning;

use App\Models\ManpowerActivity;
use App\Models\MesonTransaction;
use App\Services\ManpowerPlanning\Concerns\ResolvesMesonBasisDays;
use App\Services\ManpowerPlanning\Concerns\UsesProductivityStandard;
use App\Services\ManpowerPlanning\Concerns\UsesStationCapacity;
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
 *
 * The productivity standard is stated in ORDERS per hour, so every worker's
 * order rate is the number ranked against the bar, while the headcount still
 * comes from the piece capacity.
 */
class PickerManpowerService
{
    use ResolvesMesonBasisDays;
    use UsesProductivityStandard;
    use UsesStationCapacity;

    public const ROLE = 'picker';

    public const UNIT = 'pcs';

    public const UNIT_LABEL = 'pcs/jam';

    /**
     * Warehouse productivity standard: 30 picked orders per worker per hour.
     */
    public const ORDERS_PER_HOUR_STANDARD = 30.0;

    private const LABEL = 'Picker';

    private const MASTER_ACTIVITY_CODE = 'OB-PICKER';

    private const PICKER_DEVICE_TYPE = 'RF';

    private const PIECES_SETTING = 'pp_default_picker_pieces_per_hour';

    private const ORDERS_SETTING = 'pp_default_picker_orders_per_hour';

    /**
     * How many of the most recent picking days are averaged into one rate.
     * A single day is never trusted: a partial import would turn a two hour
     * peak into a full shift capacity.
     */
    private const BASIS_DAYS = 3;

    private const MIN_PIECES_FOR_TRUST = 10;

    private const MIN_ACTIVE_HOURS_FOR_TRUST = 0.5;

    private const MIN_ACTIVE_HOURS_FOR_RANKING = 1.0;

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
        $demandOrders = max(0, $outboundOrders);
        $standard = $this->productivityStandard(self::ORDERS_SETTING, self::ORDERS_PER_HOUR_STANDARD);

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
            'standard_rate' => round($standard, 2),
            'standard_unit' => 'order/jam',
            'demand' => $demand,
            'demand_orders' => $demandOrders,
            'pieces_per_order' => round($piecesPerOrder, 2),
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
            'pool_order_rate' => null,
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

        $schedule = $this->schedule($from, $to, $demand, $standard, $piecesPerOrder);
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
                'note' => 'Tidak ada order pada input, picking tidak perlu tenaga tambahan.',
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

        return $this->withMeasuredRate($result, $measured, $optionalPool, $standard, $schedule['hours'], $piecesPerOrder);
    }

    /**
     * How many shifts the demand needs, and how many people each one carries.
     *
     * Picking capacity is piece based, so the standard order rate is converted
     * through pieces-per-order before it can be compared against the demand.
     *
     * @return array{shifts: int, hours: float, capacity_per_person: float, payload: int, headcount: int, standard_headcount: int, plan: array<int, array<string, mixed>>}
     */
    private function schedule(?Carbon $from, ?Carbon $to, int $demandPieces, float $standard, float $piecesPerOrder): array
    {
        $hours = $this->effectiveHoursForShift(1);
        $capacityPerPerson = $standard * max(0.01, $piecesPerOrder) * $this->productiveHoursForShift(1) * (1 - self::ALLOWANCE);
        $payload = $this->rfDevices();

        $oneShift = $this->singleShiftPlan($demandPieces, $capacityPerPerson, $payload, $hours, 1);
        $twoShift = $this->singleShiftPlan($demandPieces, $capacityPerPerson, $payload, $hours, 2);

        $plan = match (true) {
            $oneShift['feasible'] => $oneShift,
            $twoShift['feasible'] => $twoShift,
            default => $oneShift,
        };

        return [
            'shifts' => $plan['shifts'],
            'hours' => $hours,
            'capacity_per_person' => $capacityPerPerson,
            'payload' => $payload,
            'headcount' => $plan['headcount'],
            'standard_headcount' => $plan['headcount'],
            'plan' => [
                $oneShift,
                $twoShift,
            ],
        ];
    }

    /**
     * One shift closes the whole demand when the crew needed to hit the standard
     * still fits the RF devices ready on the floor. Only the overflow goes to a
     * second shift.
     *
     * @return array{shifts: int, payload: int, orders_per_shift: float, capacity_per_person: float, headcount: int, capacity_total: float, coverage: float, projected_hours: float|null, feasible: bool, label: string}
     */
    private function singleShiftPlan(int $demandPieces, float $capacityPerPerson, int $payload, float $hours, int $shifts): array
    {
        $piecesPerShift = $shifts > 0 ? $demandPieces / $shifts : (float) $demandPieces;
        $headcount = $capacityPerPerson > 0 ? (int) ceil($piecesPerShift / $capacityPerPerson) : 0;
        $capacityTotal = $headcount * $capacityPerPerson;

        return [
            'shifts' => $shifts,
            'payload' => $payload,
            'orders_per_shift' => round($piecesPerShift, 2),
            'capacity_per_person' => round($capacityPerPerson, 2),
            'headcount' => $headcount,
            'capacity_total' => round($capacityTotal, 2),
            'coverage' => $piecesPerShift > 0 ? round(min(100, $capacityTotal / $piecesPerShift * 100), 1) : 100.0,
            'projected_hours' => $capacityPerPerson > 0 && $headcount > 0
                ? round($piecesPerShift / ($capacityPerPerson * $headcount) * $hours, 2)
                : 0.0,
            'feasible' => $payload <= 0 || $headcount <= $payload,
            'label' => $shifts === 1 ? '1 shift' : '2 shift',
        ];
    }

    /**
     * RF devices physically ready to run, the hard ceiling on how many pickers
     * can work the order at the same time.
     */
    private function rfDevices(): int
    {
        return $this->stationCapacity(self::MASTER_ACTIVITY_CODE, self::PICKER_DEVICE_TYPE);
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

        $conversion = ManpowerActivity::query()
            ->where('code', self::MASTER_ACTIVITY_CODE)
            ->value('conversion_ratio');

        if ($conversion !== null && (float) $conversion > 0) {
            return (float) $conversion;
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

            $trusted[] = [...$row, 'unit_rate' => $rate, 'order_rate' => $this->orderRate($row)];
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

            $rows[] = [...$row, 'unit_rate' => (float) ($row['items'] ?? 0) / $activeHours, 'order_rate' => $this->orderRate($row)];
        }

        return $rows;
    }

    /**
     * Orders per hour for a picker is the number the standard speaks in, so it
     * is the ranking value even though capacity stays in pieces.
     *
     * @param  array<string, mixed>  $row
     */
    private function orderRate(array $row): float
    {
        $activeHours = (float) ($row['active_hours'] ?? 0);

        if ($activeHours > 0) {
            return (float) ($row['orders'] ?? 0) / $activeHours;
        }

        return 0.0;
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
        float $piecesPerOrder,
    ): array {
        $rows = $this->rankAgainstStandard($measured['trusted'], $standard);
        $productiveHours = $this->productiveHoursForShift(1);
        $poolRate = $this->median(array_column($measured['trusted'], 'unit_rate'));
        $poolOrderRate = $this->median(array_column($measured['trusted'], 'order_rate'));
        $capacityPerPerson = $poolRate * $productiveHours * (1 - self::ALLOWANCE);
        $demand = (int) $result['demand'];
        $headcount = $capacityPerPerson > 0 ? (int) ceil($demand / $capacityPerPerson) : null;
        $standardHeadcount = (int) $result['standard_headcount'];

        // Nobody below the standard is picked while a standard-level crew still
        // covers the demand: the standard caps the shift, it does not pad it.
        if ($headcount !== null && $standardHeadcount > 0) {
            $headcount = min($headcount, $standardHeadcount);
        }

        $selected = $this->allocate($rows, $demand, $headcount, $productiveHours);

        $capacityTotal = array_sum(array_column($selected, 'capacity'));
        $shortfall = (int) ceil(max(0.0, $demand - $capacityTotal));

        $standardCapacity = $standard * max(0.01, $piecesPerOrder) * $productiveHours * (1 - self::ALLOWANCE);
        $atStandard = count(array_filter($rows, fn (array $row) => $row['meets_standard'] === true));

        return [
            ...$result,
            'source' => 'measured',
            'source_label' => 'Produktivitas aktual Meson',
            'headcount' => $headcount,
            'selected' => $selected,
            'near_standard' => $rows,
            'pool_rate' => round($poolRate, 2),
            'pool_order_rate' => round($poolOrderRate, 2),
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
                ? round($demand / $capacityTotal * $productiveHours, 2)
                : null,
            'note' => $this->measuredNote($result, $headcount, $selected, $shortfall, $atStandard, $standardHeadcount),
        ];
    }

    /**
     * Take the highest ranked workers until the piece demand is covered.
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
                'units' => (int) round((float) ($row['items'] ?? 0)),
                'units_per_hour' => round($row['unit_rate'], 2),
                'orders_per_hour' => round((float) ($row['order_rate'] ?? 0), 2),
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

        $devices = (int) ($result['shift_plan'][0]['payload'] ?? 0);

        if ($atStandard === 0) {
            $notes[] = sprintf(
                'Belum ada picker yang menyentuh standar %s order/jam. Semua di bawah standar, ambil yang terdekat lebih dulu.',
                number_format((float) $result['standard_rate'], 2),
            );
        }

        if ($headcount !== null && $headcount > count($selected)) {
            $notes[] = sprintf(
                'Butuh %s picker, hanya %s kandidat terukur yang tersedia. Sisanya harus ditunjuk manual atau diukur ulang.',
                number_format($headcount),
                number_format(count($selected)),
            );
        }

        if ($shortfall > 0) {
            $notes[] = sprintf(
                'Kandidat terukur paling atas baru menutup %s dari %s %s pada standar %s picker x %s jam efektif.',
                number_format(max(0, (int) $result['demand'] - $shortfall)),
                number_format((int) $result['demand']),
                self::UNIT,
                number_format($standardHeadcount),
                number_format((float) $result['effective_hours'], 2),
            );
        }

        if ($standardHeadcount > 0 && $devices > 0 && $standardHeadcount > $devices) {
            $notes[] = sprintf(
                'Jika semua orang hanya pas standar %s order/jam, kebutuhan %s picker melebihi %s RF yang ready, jadi order dibagi dua shift.',
                number_format((float) $result['standard_rate'], 2),
                number_format($standardHeadcount),
                number_format($devices),
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
            'standard_headcount' => $headcount,
            'untracked_pool' => $untracked,
            'pool_rate' => round($rate, 2),
            'pool_order_rate' => round($rate / max(0.01, (float) $result['pieces_per_order']), 2),
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

    /**
     * Picking capacity in pcs/hour. The warehouse standard is stated in
     * orders/hour, so the standard is converted through pieces-per-order when
     * no explicit pieces setting exists.
     */
    private function configuredPiecesPerHour(float $piecesPerOrder): ?float
    {
        $configured = $this->settingValue(self::PIECES_SETTING);

        if ($configured !== null && $configured > 0) {
            return $configured;
        }

        $masterRate = $this->masterActivityRate(self::MASTER_ACTIVITY_CODE);

        if ($masterRate !== null && $masterRate > 0) {
            return $masterRate;
        }

        $ordersRate = $this->settingValue(self::ORDERS_SETTING);

        if ($ordersRate !== null && $ordersRate > 0) {
            return $ordersRate * max(0.01, $piecesPerOrder);
        }

        return null;
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
