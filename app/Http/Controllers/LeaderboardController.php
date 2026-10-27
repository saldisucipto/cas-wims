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

    /**
     * @return array<string, mixed>
     */
    private function board(Carbon $date, string $role, string $title): array
    {
        $board = $this->productivity->leaderboard($date, null, $role);

        // A scoreboard only ranks people who actually produced output.
        $rows = array_values(array_filter(
            $board['rows'],
            fn (array $row) => ($row['items'] ?? 0) > 0,
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
                'items' => array_sum(array_column($rows, 'items')),
                'orders' => array_sum(array_column($rows, 'orders')),
                'lines' => array_sum(array_column($rows, 'lines')),
                'workers' => count($rows),
            ],
        ];
    }
}
