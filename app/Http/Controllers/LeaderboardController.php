<?php

namespace App\Http\Controllers;

use App\Services\PackingProductivity\OperationalClock;
use App\Services\PackingProductivity\PackingProductivityService;
use App\Services\PackingProductivity\WorkerProductivityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private WorkerProductivityService $productivity,
        private PackingProductivityService $packingProductivity,
    ) {}

    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : OperationalClock::today();

        $boards = [
            'picker' => $this->board($date, 'picker', 'Picking Productivity'),
            'packer' => $this->board($date, 'packer', 'Packing Productivity'),
        ];

        $dataUntil = collect($boards)->pluck('data_until')->filter()->max();

        return view('leaderboard.productivity', [
            'boards' => $boards,
            'date' => $date,
            'generatedAt' => OperationalClock::now(),
            'dataUntil' => $dataUntil ? Carbon::parse($dataUntil) : null,
            'isStale' => $dataUntil === null || Carbon::parse($dataUntil)->toDateString() < $date->toDateString(),
        ]);
    }

    public function weekly(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : OperationalClock::today();
        $boards = $this->weeklyBoards($date);

        return view('leaderboard.weekly', [
            'boards' => $boards,
            'date' => $date,
            'weekStart' => $date->copy()->startOfWeek(Carbon::MONDAY),
            'weekEnd' => $date->copy()->endOfWeek(Carbon::SUNDAY),
            'generatedAt' => OperationalClock::now(),
        ]);
    }

    public function weeklyPrint(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : OperationalClock::today();
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);
        $workerKey = $request->string('worker')->toString();
        $role = $request->string('role')->toString();
        $roles = in_array($role, ['picker', 'packer'], true) ? [$role] : ['picker', 'packer'];
        $sections = [];

        foreach ($roles as $sectionRole) {
            $board = $this->weeklyBoard($date, $sectionRole, $sectionRole === 'picker' ? 'Picking Productivity' : 'Packing Productivity');
            $dailyRows = $this->productivity->dailyPerformance($weekStart, $weekEnd, $sectionRole)['rows'];

            if ($workerKey !== '') {
                $board['rows'] = array_values(array_filter(
                    $board['rows'],
                    fn (array $row): bool => ($row['key'] ?? '') === $workerKey,
                ));
                $dailyRows = array_values(array_filter(
                    $dailyRows,
                    fn (array $row): bool => ($row['key'] ?? '') === $workerKey,
                ));
            }

            $sections[] = [
                ...$board,
                'daily_rows' => $dailyRows,
            ];
        }

        if ($workerKey !== '') {
            $sections = array_values(array_filter($sections, fn (array $section): bool => $section['rows'] !== []));
        }

        return view('leaderboard.weekly-print', [
            'sections' => $sections,
            'date' => $date,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'generatedAt' => OperationalClock::now(),
            'workerKey' => $workerKey,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function weeklyBoards(Carbon $date): array
    {
        return [
            'picker' => $this->weeklyBoard($date, 'picker', 'Picking Productivity'),
            'packer' => $this->weeklyBoard($date, 'packer', 'Packing Productivity'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function weeklyBoard(Carbon $date, string $role, string $title): array
    {
        $board = $this->productivity->weeklyLeaderboard($date, $role);
        $rows = $board['rows'];

        return [
            'key' => $role,
            'title' => $title,
            'role' => $role,
            'types' => $this->packingProductivity->typesForRole($role) ?? [],
            'rows' => $rows,
            'top_three' => array_slice($rows, 0, 3),
            'data_until' => $board['data_until'],
            'is_stale' => $board['is_stale'],
            'totals' => [
                'orders' => array_sum(array_column($rows, 'orders')),
                'items' => array_sum(array_column($rows, 'items')),
                'active_hours' => array_sum(array_column($rows, 'active_hours')),
                'workers' => count($rows),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function board(Carbon $date, string $role, string $title): array
    {
        $board = $this->productivity->leaderboard($date, null, $role);

        // A scoreboard only ranks people who actually produced output.
        $rows = array_values(array_filter(
            $board['rows'],
            fn (array $row) => ($row['orders'] ?? 0) > 0,
        ));

        $types = $this->packingProductivity->typesForRole($role) ?? [];

        return [
            'key' => $role,
            'title' => $title,
            'role' => $role,
            'types' => $types,
            'rows' => $rows,
            'active' => collect($board['rows'])->contains(fn (array $row) => (bool) ($row['is_working'] ?? false)),
            'data_until' => $board['data_until'],
            'totals' => [
                'orders' => array_sum(array_column($rows, 'orders')),
                'items' => array_sum(array_column($rows, 'items')),
                'lines' => array_sum(array_column($rows, 'lines')),
                'workers' => count($rows),
            ],
        ];
    }
}
