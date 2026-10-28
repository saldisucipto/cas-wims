<?php

namespace App\Http\Controllers;

use App\Services\PackingProductivity\WorkerProductivityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ManpowerReportController extends Controller
{
    private const ROLES = [
        'all' => null,
        'picker' => 'picker',
        'packer' => 'packer',
    ];

    public function __construct(private WorkerProductivityService $productivity) {}

    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $dataset = $this->buildDataset($request);

        return view('administration.reports.manpower', [
            ...$dataset,
            'perPage' => 25,
        ]);
    }

    public function print(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $dataset = $this->buildDataset($request);

        return view('administration.reports.manpower-print', [
            ...$dataset,
            'printedBy' => auth()->user()?->name ?? '-',
            'printedAt' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDataset(Request $request): array
    {
        $filter = $this->resolvePeriod($request);

        $roleKey = $request->string('role')->toString();
        $roleKey = array_key_exists($roleKey, self::ROLES) ? $roleKey : 'all';

        $search = trim($request->string('q')->toString());
        $search = preg_replace('/\s+/', ' ', str_replace('_', ' ', $search)) ?? '';

        $performance = $this->productivity->dailyPerformance(
            $filter['start'],
            $filter['end'],
            self::ROLES[$roleKey],
        );

        $rows = $performance['rows'];

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $rows = array_values(array_filter($rows, function (array $row) use ($needle) {
                return str_contains(mb_strtolower($row['name']), $needle)
                    || str_contains(mb_strtolower((string) $row['employee_code']), $needle)
                    || str_contains(mb_strtolower((string) $row['username']), $needle);
            }));
        }

        $summary = $this->summarize($rows);
        $perWorker = $this->groupByWorker($rows);
        $dates = collect($rows)->pluck('date')->unique()->sortDesc()->values();

        return [
            'rows' => $rows,
            'perWorker' => $perWorker,
            'dates' => $dates,
            'summary' => $summary,
            'filter' => $filter,
            'search' => $search,
            'role' => $roleKey,
            'roleOptions' => [
                'all' => 'Semua role',
                'picker' => 'Picker (Picking&Packing)',
                'packer' => 'Packer (Packing)',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarize(array $rows): array
    {
        $durationHours = array_sum(array_column($rows, 'duration_hours'));
        $activeHours = array_sum(array_column($rows, 'active_hours'));
        $orders = array_sum(array_column($rows, 'orders'));
        $items = array_sum(array_column($rows, 'items'));

        return [
            'total_workers' => collect($rows)->pluck('daily_worker_id')->filter()->unique()->count(),
            'total_days' => collect($rows)->pluck('date')->unique()->count(),
            'total_sessions' => array_sum(array_column($rows, 'sessions')),
            'duration_hours' => round($durationHours, 2),
            'active_hours' => round($activeHours, 2),
            'total_orders' => $orders,
            'total_items' => round($items, 2),
            'orders_per_hour' => $activeHours > 0 ? round($orders / $activeHours, 2) : null,
            'avg_duration_hours' => count($rows) > 0 ? round($durationHours / count($rows), 2) : 0.0,
        ];
    }

    /**
     * Aggregate the daily rows into one row per worker for the whole period.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupByWorker(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = $row['daily_worker_id'] !== null
                ? 'worker:'.$row['daily_worker_id']
                : 'operator:'.$row['username'];

            $grouped[$key] ??= [
                'name' => $row['name'],
                'employee_code' => $row['employee_code'],
                'function' => $row['function'],
                'days' => 0,
                'sessions' => 0,
                'duration_hours' => 0.0,
                'active_hours' => 0.0,
                'orders' => 0,
                'items' => 0.0,
            ];

            $grouped[$key]['days']++;
            $grouped[$key]['sessions'] += $row['sessions'];
            $grouped[$key]['duration_hours'] += $row['duration_hours'];
            $grouped[$key]['active_hours'] += $row['active_hours'];
            $grouped[$key]['orders'] += $row['orders'];
            $grouped[$key]['items'] += $row['items'];
        }

        $result = array_map(function (array $row): array {
            $row['duration_hours'] = round($row['duration_hours'], 2);
            $row['active_hours'] = round($row['active_hours'], 2);
            $row['items'] = round($row['items'], 2);
            $row['orders_per_hour'] = $row['active_hours'] > 0 ? round($row['orders'] / $row['active_hours'], 2) : null;
            $row['orders_per_day'] = $row['days'] > 0 ? round($row['orders'] / $row['days'], 2) : null;

            return $row;
        }, array_values($grouped));

        usort($result, fn (array $a, array $b) => [$b['orders'], $b['active_hours']] <=> [$a['orders'], $a['active_hours']]);

        return $result;
    }

    /**
     * @return array{period: string, start_date: string, end_date: string, start: Carbon, end: Carbon, label: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'this_month';
        $startDate = $request->string('start_date')->toString();
        $endDate = $request->string('end_date')->toString();
        $today = Carbon::today();

        if ($period === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            return [
                'period' => 'custom',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start' => $start,
                'end' => $end,
                'label' => $start->translatedFormat('d F Y').' - '.$end->translatedFormat('d F Y'),
            ];
        }

        [$start, $end, $label] = match ($period) {
            'today' => [$today->copy(), $today->copy()->endOfDay(), 'Hari Ini'],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay(), 'Kemarin'],
            'last_7_days' => [$today->copy()->subDays(6), $today->copy()->endOfDay(), '7 Hari Terakhir'],
            'last_30_days' => [$today->copy()->subDays(29), $today->copy()->endOfDay(), '30 Hari Terakhir'],
            'last_month' => [Carbon::now()->subMonthNoOverflow()->startOfMonth(), Carbon::now()->subMonthNoOverflow()->endOfMonth(), 'Bulan Lalu'],
            'this_year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear(), 'Tahun Ini'],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'Bulan Ini'],
        };

        return [
            'period' => $period,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start' => $start,
            'end' => $end,
            'label' => $label.' ('.$start->translatedFormat('d F Y').' - '.$end->translatedFormat('d F Y').')',
        ];
    }

    private function ensureAdmin()
    {
        if (! auth()->check() || auth()->user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
