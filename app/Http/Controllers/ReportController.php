<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\ConsumableRequestItem;
use App\Models\DailyWorker;
use App\Models\RfDevice;
use App\Models\StockTransaction;
use App\Models\WorkingSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReportController extends Controller
{
    public function workingSessions(Request $request)
    {
        if ($redirect = $this->ensureAdminOrLeader()) {
            return $redirect;
        }

        $filter = $this->resolveDateFilter($request);

        $query = WorkingSession::query()
            ->with(['dailyWorker', 'packingStation', 'rfDevice', 'wmsAccount'])
            ->whereBetween('started_at', [$filter['start'], $filter['end']])
            ->latest('started_at');

        $rows = $query->paginate(15)->withQueryString();

        $allFilteredRows = WorkingSession::query()
            ->with('dailyWorker')
            ->whereBetween('started_at', [$filter['start'], $filter['end']])
            ->get();

        $totalMinutes = $allFilteredRows->sum(function (WorkingSession $session) {
            if (! $session->ended_at || ! $session->started_at) {
                return 0;
            }

            return max(0, $session->started_at->diffInMinutes($session->ended_at));
        });

        return view('administration.reports.working-sessions', [
            'rows' => $rows,
            'filter' => $filter,
            'canForceClose' => in_array(Auth::user()?->role, ['Administrator', 'Leader'], true),
            'backRoute' => Auth::user()?->role === 'Leader' ? route('leader.panel') : route('administration.dashboard'),
            'summary' => [
                'total_sessions' => $allFilteredRows->count(),
                'total_workers' => $allFilteredRows->pluck('daily_worker_id')->unique()->count(),
                'total_hours' => round($totalMinutes / 60, 2),
            ],
        ]);
    }

    public function forceCloseWorkingSession(Request $request, WorkingSession $workingSession)
    {
        if (! Auth::check() || ! in_array(Auth::user()->role, ['Administrator', 'Leader'], true)) {
            return redirect()->route('administration.login')->with('error', 'Anda tidak memiliki akses untuk melakukan force close session.');
        }

        try {
            DB::transaction(function () use ($workingSession) {
                $session = WorkingSession::query()
                    ->with(['dailyWorker', 'packingStation', 'rfDevice', 'wmsAccount'])
                    ->lockForUpdate()
                    ->find($workingSession->id);

                if (! $session) {
                    throw new RuntimeException('Sesi kerja tidak ditemukan.');
                }

                if ($session->status !== 'Working') {
                    throw new RuntimeException('Sesi kerja sudah ditutup sebelumnya.');
                }

                $now = now();

                if ($session->packing_station_id) {
                    $station = $session->packingStation()->lockForUpdate()->first();

                    if ($station) {
                        $station->update(['status' => 'Available']);
                    }
                }

                if ($session->rf_device_id) {
                    $device = $session->rfDevice()->lockForUpdate()->first();

                    if ($device) {
                        $device->update(['status' => 'Available']);
                    }
                }

                if ($session->wms_account_id) {
                    $wmsAccount = $session->wmsAccount()->lockForUpdate()->first();

                    if ($wmsAccount) {
                        $wmsAccount->update(['status' => 'Available']);
                    }
                }

                $session->update([
                    'status' => 'Closed',
                    'ended_at' => $now,
                    'close_type' => 'Force Close',
                    'force_closed_by' => Auth::id(),
                    'force_closed_at' => $now,
                    'force_close_reason' => 'Force Closed by Administrator',
                ]);

                Log::warning('Force Closed Working Session', [
                    'actor' => Auth::user()?->name,
                    'actor_role' => Auth::user()?->role,
                    'session_id' => $session->id,
                    'employee' => $session->dailyWorker?->name,
                    'rf_device' => $session->rfDevice?->code,
                    'packing_station' => $session->packingStation?->name,
                    'closed_at' => $now->format('d F Y H:i'),
                ]);
            });
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success_force_close', true);
    }

    public function consumableUsage(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveDateFilter($request);

        $query = ConsumableRequestItem::query()
            ->with(['consumable', 'consumableRequest.dailyWorker'])
            ->whereHas('consumableRequest', function ($requestQuery) {
                $requestQuery->where('status', 'Validated');
            })
            ->whereBetween('created_at', [$filter['start'], $filter['end']])
            ->latest('created_at');

        $rows = $query->paginate(15)->withQueryString();

        $allFilteredRows = ConsumableRequestItem::query()
            ->with('consumable')
            ->whereHas('consumableRequest', function ($requestQuery) {
                $requestQuery->where('status', 'Validated');
            })
            ->whereBetween('created_at', [$filter['start'], $filter['end']])
            ->get();

        return view('administration.reports.consumable-usage', [
            'rows' => $rows,
            'filter' => $filter,
            'summary' => [
                'total_transactions' => $allFilteredRows->count(),
                'total_item_rows' => $allFilteredRows->count(),
                'total_quantity' => $allFilteredRows->sum('quantity'),
                'total_consumable_types' => $allFilteredRows->pluck('consumable_id')->filter()->unique()->count(),
            ],
        ]);
    }

    public function consumableStockCard(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveConsumableStockCardFilter($request);

        $consumables = Consumable::query()->orderBy('name')->get();
        $selectedConsumableId = $request->integer('consumable_id');

        if (! $selectedConsumableId) {
            $summaryRows = $this->consumableSummaryRows($filter);

            return view('administration.reports.consumable-stock-card', [
                'mode' => 'summary',
                'rows' => $summaryRows,
                'filter' => array_merge($filter, $request->only('consumable_id')),
                'consumables' => $consumables,
                'summary' => [
                    'total_in' => $summaryRows->sum('total_in'),
                    'total_out' => $summaryRows->sum('total_out'),
                    'total_items' => $summaryRows->count(),
                ],
            ]);
        }

        $consumable = Consumable::query()->find($selectedConsumableId);

        if (! $consumable) {
            return back()->withErrors(['consumable_filter' => 'Consumable tidak ditemukan.']);
        }

        $movementData = $this->buildConsumableMovementData($consumable, $filter);
        $rows = $this->paginateCollection($movementData['rows_desc'], 15, $request);

        return view('administration.reports.consumable-stock-card', [
            'mode' => 'detail',
            'rows' => $rows,
            'filter' => array_merge($filter, $request->only('consumable_id')),
            'consumables' => $consumables,
            'summary' => [
                'total_in' => $movementData['total_in'],
                'total_out' => $movementData['total_out'],
                'total_items' => $movementData['rows_desc']->isEmpty() ? 0 : 1,
            ],
        ]);
    }

    public function printConsumableStockCard(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveConsumableStockCardFilter($request);

        if (! $request->filled('consumable_id')) {
            $summaryRows = $this->consumableSummaryRows($filter);

            return view('administration.reports.consumable-stock-card-summary-print', [
                'rows' => $summaryRows,
                'filter' => $filter,
                'printedBy' => Auth::user()->name,
                'printedAt' => now(),
            ]);
        }

        $consumable = Consumable::query()->find($request->integer('consumable_id'));

        if (! $consumable) {
            return back()->withErrors(['consumable_print' => 'Pilih consumable terlebih dahulu.']);
        }

        $movementData = $this->buildConsumableMovementData($consumable, $filter);

        return view('administration.reports.consumable-stock-card-print', [
            'consumable' => $consumable,
            'transactions' => $movementData['rows_asc'],
            'filter' => $filter,
            'openingBalance' => $movementData['opening_balance'],
            'totalIncoming' => $movementData['total_in'],
            'totalOutgoing' => $movementData['total_out'],
            'endingBalance' => $movementData['ending_balance'],
            'printedBy' => Auth::user()->name,
            'printedAt' => now(),
        ]);
    }

    public function inventory(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveDateFilter($request);

        $query = StockTransaction::query()
            ->with(['consumable', 'performer'])
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->latest('transaction_at');

        $rows = $query->paginate(15)->withQueryString();

        $allFilteredRows = StockTransaction::query()
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->get();

        return view('administration.reports.inventory', [
            'rows' => $rows,
            'filter' => $filter,
            'summary' => [
                'total_receiving' => $allFilteredRows->where('transaction_type', 'Receiving')->count(),
                'total_usage' => $allFilteredRows->where('transaction_type', 'Usage')->count(),
                'total_adjustment' => $allFilteredRows->where('transaction_type', 'Adjustment')->count(),
                'total_opname' => $allFilteredRows->where('transaction_type', 'Opname')->count(),
            ],
        ]);
    }

    public function rfDeviceUsage(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveDateFilter($request);

        $sessionConstraint = function ($query) use ($filter) {
            $query->whereBetween('started_at', [$filter['start'], $filter['end']]);
        };

        $query = RfDevice::query()
            ->with('wmsAccount')
            ->withCount(['workingSessions' => $sessionConstraint])
            ->whereHas('workingSessions', $sessionConstraint)
            ->orderBy('code');

        $rows = $query->paginate(15)->withQueryString();

        $allRows = RfDevice::query()
            ->withCount(['workingSessions' => $sessionConstraint])
            ->whereHas('workingSessions', $sessionConstraint)
            ->get();

        return view('administration.reports.rf-device-usage', [
            'rows' => $rows,
            'filter' => $filter,
            'summary' => [
                'total_devices' => $allRows->count(),
                'in_use_devices' => $allRows->where('status', 'In Use')->count(),
                'total_sessions' => $allRows->sum('working_sessions_count'),
            ],
        ]);
    }

    public function dailyWorkerActivity(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveDateFilter($request);

        $sessionConstraint = function ($query) use ($filter) {
            $query->whereBetween('started_at', [$filter['start'], $filter['end']]);
        };

        $query = DailyWorker::query()
            ->withCount(['workingSessions' => $sessionConstraint])
            ->with(['workingSessions' => function ($query) use ($filter) {
                $query->whereBetween('started_at', [$filter['start'], $filter['end']])
                    ->latest('started_at')
                    ->limit(1);
            }])
            ->whereHas('workingSessions', $sessionConstraint)
            ->orderBy('name');

        $rows = $query->paginate(15)->withQueryString();

        $allRows = DailyWorker::query()
            ->withCount(['workingSessions' => $sessionConstraint])
            ->whereHas('workingSessions', $sessionConstraint)
            ->get();

        return view('administration.reports.daily-worker-activity', [
            'rows' => $rows,
            'filter' => $filter,
            'summary' => [
                'total_workers' => $allRows->count(),
                'active_workers' => $allRows->where('status', 'Active')->count(),
                'total_sessions' => $allRows->sum('working_sessions_count'),
            ],
        ]);
    }

    private function consumableSummaryRows(array $filter): Collection
    {
        $incomingTotals = StockTransaction::query()
            ->select('consumable_id')
            ->selectRaw('COALESCE(SUM(quantity_change), 0) as total_in')
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->where('transaction_type', 'Receiving')
            ->groupBy('consumable_id')
            ->get()
            ->keyBy('consumable_id');

        $outgoingTotals = ConsumableRequestItem::query()
            ->select('consumable_id')
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_out')
            ->whereHas('consumableRequest', function ($query) {
                $query->where('status', 'Validated');
            })
            ->whereBetween('created_at', [$filter['start'], $filter['end']])
            ->groupBy('consumable_id')
            ->get()
            ->keyBy('consumable_id');

        return Consumable::query()
            ->orderBy('name')
            ->get()
            ->map(function (Consumable $consumable) use ($incomingTotals, $outgoingTotals) {
                $incoming = $incomingTotals->get($consumable->id);
                $outgoing = $outgoingTotals->get($consumable->id);

                return [
                    'sku' => $consumable->sku,
                    'name' => $consumable->name,
                    'unit' => $consumable->unit,
                    'total_in' => (int) ($incoming->total_in ?? 0),
                    'total_out' => (int) ($outgoing->total_out ?? 0),
                    'balance' => (int) $consumable->stock,
                ];
            })
            ->values();
    }

    /**
     * @return array{rows_asc:Collection<int,object>,rows_desc:Collection<int,object>,opening_balance:int,ending_balance:int,total_in:int,total_out:int}
     */
    private function buildConsumableMovementData(Consumable $consumable, array $filter): array
    {
        $rowsAsc = StockTransaction::query()
            ->with('performer')
            ->where('consumable_id', $consumable->id)
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get()
            ->map(function (StockTransaction $row) use ($consumable) {
                $quantityChange = (int) $row->quantity_change;

                return (object) [
                    'row_key' => 'stock-'.$row->id,
                    'transaction_at' => $row->transaction_at,
                    'transaction_type' => $row->transaction_type,
                    'reference' => $row->purchase_request_number ?: ($row->transaction_group ?: '-'),
                    'consumable_name' => $consumable->name,
                    'quantity_in' => max($quantityChange, 0),
                    'quantity_out' => abs(min($quantityChange, 0)),
                    'balance' => (int) $row->quantity_after,
                    'quantity_before' => (int) $row->quantity_before,
                    'user_name' => $row->received_by_name ?: ($row->performer?->name ?? '-'),
                    'notes' => $row->notes ?: '-',
                ];
            })
            ->values();

        $totalIncoming = (int) $rowsAsc->sum('quantity_in');
        $totalOutgoing = (int) $rowsAsc->sum('quantity_out');

        if ($rowsAsc->isNotEmpty()) {
            $openingBalance = (int) $rowsAsc->first()->quantity_before;
            $endingBalance = (int) $rowsAsc->last()->balance;
        } else {
            $previousTransaction = StockTransaction::query()
                ->where('consumable_id', $consumable->id)
                ->where('transaction_at', '<', $filter['start'])
                ->latest('transaction_at')
                ->first();

            $openingBalance = $previousTransaction
                ? (int) $previousTransaction->quantity_after
                : (int) $consumable->stock;
            $endingBalance = $openingBalance;
        }

        return [
            'rows_asc' => $rowsAsc,
            'rows_desc' => $rowsAsc->sortByDesc([
                ['transaction_at', 'desc'],
                ['row_key', 'desc'],
            ])->values(),
            'opening_balance' => $openingBalance,
            'ending_balance' => $endingBalance,
            'total_in' => $totalIncoming,
            'total_out' => $totalOutgoing,
        ];
    }

    private function paginateCollection(Collection $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function resolveConsumableStockCardFilter(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'this_month';
        $startDate = $request->string('start_date')->toString();
        $endDate = $request->string('end_date')->toString();

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
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), 'Hari Ini'],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'Bulan Ini'],
        };

        return [
            'period' => $period,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    private function resolveDateFilter(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'this_month';
        $startDate = $request->string('start_date')->toString();
        $endDate = $request->string('end_date')->toString();

        $today = Carbon::today();

        if ($period === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $label = $start->translatedFormat('d F Y').' - '.$end->translatedFormat('d F Y');

            return [
                'period' => 'custom',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start' => $start,
                'end' => $end,
                'label' => $label,
            ];
        }

        [$start, $end, $label] = match ($period) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Hari Ini'],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay(), 'Kemarin'],
            'last_7_days' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay(), '7 Hari Terakhir'],
            'last_30_days' => [$today->copy()->subDays(29)->startOfDay(), $today->copy()->endOfDay(), '30 Hari Terakhir'],
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
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }

    private function ensureAdminOrLeader()
    {
        if (! Auth::check() || ! in_array(Auth::user()->role, ['Administrator', 'Leader'], true)) {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
