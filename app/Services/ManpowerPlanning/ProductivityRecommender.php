<?php

namespace App\Services\ManpowerPlanning;

use App\Models\ManpowerActivity;
use App\Models\MesonTransaction;
use App\Models\SystemSetting;
use App\Services\PackingProductivity\WorkerProductivityService;
use Carbon\Carbon;

/**
 * Turns measured worker productivity into a concrete staffing recommendation.
 *
 * The recommendation is built from what each person actually produced in Meson
 * (orders per working hour), never from a planning assumption, so the suggested
 * headcount reflects the real team.
 */
class ProductivityRecommender
{
    public const ROLE_PICKER = 'picker';

    public const ROLE_PACKER = 'packer';

    /**
     * A worker needs at least this many orders and minutes before their rate is
     * trusted; below it they are listed as "belum cukup data".
     */
    private const MIN_ORDERS_FOR_TRUST = 10;

    private const MIN_ACTIVE_MINUTES_FOR_TRUST = 20;

    public function __construct(private WorkerProductivityService $productivity) {}

    /**
     * Measure every worker's output for the previous day of the planning date.
     *
     * A single previous day is used on purpose: the recommendation must reflect
     * the most recent shift, not a monthly average. When that exact day has no
     * Meson data, the closest earlier day that does is used instead and reported
     * back so the planner sees which date was taken as the basis.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     from: Carbon,
     *     to: Carbon,
     *     basis_date: Carbon,
     *     is_exact_previous_day: bool,
     *     data_until: Carbon|null,
     *     has_data: bool
     * }
     */
    public function performance(Carbon $planningDate, string $role): array
    {
        $planningStart = $planningDate->copy()->startOfDay();
        $previousDay = $planningStart->copy()->subDay();

        $latestBefore = MesonTransaction::query()
            ->where('transaction_time', '<', $planningStart)
            ->max('transaction_time');

        $latestDate = $latestBefore ? Carbon::parse($latestBefore)->startOfDay() : null;

        $basisDate = $latestDate !== null && $latestDate->lt($previousDay)
            ? $latestDate
            : $previousDay;

        $result = $this->productivity->performance($basisDate, $basisDate, $role);

        $rows = array_map(fn (array $row) => $this->decorate($row), $result['rows']);

        return [
            'rows' => $rows,
            'from' => $basisDate->copy()->startOfDay(),
            'to' => $basisDate->copy()->endOfDay(),
            'basis_date' => $basisDate->copy()->startOfDay(),
            'is_exact_previous_day' => $basisDate->equalTo($previousDay),
            'data_until' => $latestDate,
            'has_data' => collect($rows)->contains(fn (array $row) => (bool) $row['trusted']),
        ];
    }

    /**
     * Suggest which workers to call in and how many, for a given order demand.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    public function recommend(string $role, int $demand, float $effectiveHours, array $candidates = []): array
    {
        $roleLabel = $role === self::ROLE_PICKER ? 'Picker' : 'Packer';
        $effectiveHours = max(0.5, $effectiveHours);

        $trusted = array_values(array_filter(
            $candidates,
            fn (array $row) => $row['trusted'] && ($row['orders_per_hour'] ?? 0) > 0,
        ));

        usort($trusted, fn (array $a, array $b) => $b['orders_per_hour'] <=> $a['orders_per_hour']);

        $untracked = array_values(array_filter($candidates, fn (array $row) => ! $row['trusted']));

        $result = [
            'role' => $role,
            'label' => $roleLabel,
            'demand' => max(0, $demand),
            'effective_hours' => round($effectiveHours, 2),
            'source' => 'measured',
            'source_label' => 'Produktivitas aktual Meson',
            'headcount' => null,
            'selected' => [],
            'capacity' => 0.0,
            'coverage' => 0.0,
            'shortfall' => 0.0,
            'projected_hours' => null,
            'team_rate' => null,
            'trusted_pool' => count($trusted),
            'untracked_pool' => count($untracked),
            'note' => null,
        ];

        if ($demand <= 0) {
            $result['headcount'] = 0;
            $result['note'] = 'Tidak ada order pada input, tidak perlu tenaga tambahan.';

            return $result;
        }

        if ($trusted === []) {
            return $this->fallback($result, $effectiveHours);
        }

        $remaining = (float) $demand;
        $selected = [];

        foreach ($trusted as $worker) {
            if ($remaining <= 0) {
                break;
            }

            $rate = (float) $worker['orders_per_hour'];
            $capacity = $rate * $effectiveHours;
            $allocated = min($capacity, $remaining);

            $selected[] = [
                'daily_worker_id' => $worker['daily_worker_id'],
                'employee_code' => $worker['employee_code'],
                'name' => $worker['name'],
                'function' => $worker['function'],
                'orders_per_hour' => $rate,
                'capacity' => round($capacity, 2),
                'allocated_orders' => (int) ceil($allocated),
                'utilization' => $capacity > 0 ? round($allocated / $capacity * 100, 1) : 0.0,
                'days_worked' => $worker['days_worked'],
                'orders' => $worker['orders'],
            ];

            $remaining -= $allocated;
        }

        $headcount = count($selected);
        $totalRate = array_sum(array_column($selected, 'orders_per_hour'));
        $capacity = $totalRate * $effectiveHours;
        $poolRate = $trusted !== []
            ? array_sum(array_column($trusted, 'orders_per_hour')) / count($trusted)
            : 0.0;

        $additional = $remaining > 0 && $poolRate > 0
            ? (int) ceil($remaining / ($poolRate * $effectiveHours))
            : null;

        return [
            ...$result,
            'headcount' => $headcount,
            'selected' => $selected,
            'capacity' => round($capacity, 2),
            'coverage' => round(min(100, $capacity / $demand * 100), 1),
            'shortfall' => (int) ceil(max(0, $remaining)),
            'projected_hours' => $totalRate > 0 ? round($demand / $totalRate, 2) : null,
            'team_rate' => $headcount > 0 ? round($totalRate / $headcount, 2) : null,
            'pool_rate' => round($poolRate, 2),
            'additional_needed' => $additional,
            'note' => $remaining > 0
                ? sprintf(
                    'Masih kurang %s order. Butuh sekitar %s %s tambahan (asumsi rata-rata %s order/jam).',
                    number_format((int) ceil($remaining)),
                    $additional !== null ? number_format($additional) : '—',
                    strtolower($roleLabel),
                    number_format($poolRate, 1),
                )
                : null,
        ];
    }

    /**
     * When nobody has measured output, fall back to the master activity standard
     * so the planner still receives an indicative headcount.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function fallback(array $result, float $effectiveHours): array
    {
        $plannedRate = $this->plannedOrdersPerHour($result['role']);

        if ($plannedRate === null) {
            return [
                ...$result,
                'source' => 'none',
                'source_label' => 'Belum ada data',
                'note' => sprintf(
                    'Belum ada transaksi %s di Meson dan standar master activity belum diisi, headcount belum bisa dihitung.',
                    $result['role'] === self::ROLE_PICKER ? 'Picking&Packing' : 'Packing',
                ),
            ];
        }

        $demand = $result['demand'];
        $headcount = (int) ceil($demand / max(0.1, $plannedRate * $effectiveHours));

        return [
            ...$result,
            'source' => 'standard',
            'source_label' => 'Standar master activity',
            'headcount' => $headcount,
            'selected' => [],
            'capacity' => round($headcount * $plannedRate * $effectiveHours, 2),
            'coverage' => 100.0,
            'shortfall' => 0,
            'projected_hours' => round($demand / max(0.1, $headcount * $plannedRate), 2),
            'team_rate' => round($plannedRate, 2),
            'note' => 'Angka ini memakai standar produktivitas master activity, bukan hasil aktual pekerja.',
        ];
    }

    private function plannedOrdersPerHour(string $role): ?float
    {
        $settingKey = $role === self::ROLE_PICKER
            ? 'pp_default_picker_orders_per_hour'
            : 'pp_default_packer_orders_per_hour';

        $configured = SystemSetting::query()->where('setting_key', $settingKey)->value('setting_value');

        if ($configured !== null && is_numeric($configured) && (float) $configured > 0) {
            return (float) $configured;
        }

        $code = $role === self::ROLE_PICKER ? 'OB-PICKER' : 'OB-CHECKPACK';
        $activity = ManpowerActivity::query()->where('code', $code)->first();

        if (! $activity || $activity->productivity_per_hour <= 0) {
            return null;
        }

        $conversion = $activity->conversion_ratio > 0 ? (float) $activity->conversion_ratio : 1.0;
        $unit = strtolower((string) $activity->productivity_unit);

        // pcs/hour activities are converted to orders/hour through the ratio.
        if (str_contains($unit, 'pcs')) {
            return (float) $activity->productivity_per_hour / $conversion;
        }

        return (float) $activity->productivity_per_hour;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function decorate(array $row): array
    {
        $activeMinutes = (int) round(((float) ($row['active_hours'] ?? 0)) * 60);

        $row['trusted'] = $row['orders'] >= self::MIN_ORDERS_FOR_TRUST
            && $activeMinutes >= self::MIN_ACTIVE_MINUTES_FOR_TRUST
            && ($row['orders_per_hour'] ?? 0) > 0;

        return $row;
    }
}
