<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\ConsumableRequest;
use App\Models\ConsumableRequestItem;
use App\Models\PackingStation;
use App\Models\RfDevice;
use App\Models\SystemSetting;
use App\Models\WorkingSession;
use App\Models\WmsAccount;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class WelcomeDashboardController extends Controller
{
    public function __invoke()
    {
        $now = now();
        $menuCards = [
            [
                'icon' => '📦',
                'title' => 'Registrasi Meja Packing',
                'description' => ['Registrasi Pekerja Harian', 'sebelum mulai shift operasional.'],
                'href' => route('packing.registration'),
            ],
            [
                'icon' => '📱',
                'title' => 'RF Handheld',
                'description' => ['Daftarkan Perangkat RF', 'dan dapatkan Akun WMS.'],
                'href' => route('rf.registration'),
            ],
            [
                'icon' => '📝',
                'title' => 'Permintaan ATK',
                'description' => ['Ambil ATK', 'tanpa perlu akun.'],
                'href' => route('atk.take'),
            ],
            [
                'icon' => '👨‍💼',
                'title' => 'Panel Leader',
                'description' => ['Validasi Consumable', 'Monitoring', 'Kontrol Operator'],
                'href' => route('leader.login'),
            ],
            [
                'icon' => '⚙',
                'title' => 'Administrasi',
                'description' => ['Inventaris', 'Data Master', 'Laporan', 'Konfigurasi'],
                'href' => route('administration.login'),
            ],
        ];

        $shift = $this->resolveCurrentShift($now);

        if (! $this->hasOperationalTables()) {
            return view('welcome', [
                'now' => $now,
                'currentShift' => $shift,
                'quickStatus' => [
                    'emoji' => '🟡',
                    'label' => 'Waiting Validation',
                    'class' => 'border-amber-500/40 bg-amber-100 text-amber-700',
                ],
                'summaryCards' => $this->emptySummaryCards(),
                'overview' => $this->emptyOverview($now),
                'lowStockThreshold' => 10,
                'lowStockItems' => collect(),
                'recentActivities' => collect(),
                'menuCards' => $menuCards,
            ]);
        }

        try {
            return $this->buildOperationalDashboard($now, $shift, $menuCards);
        } catch (QueryException) {
            return view('welcome', [
                'now' => $now,
                'currentShift' => $shift,
                'quickStatus' => [
                    'emoji' => '🟡',
                    'label' => 'Waiting Validation',
                    'class' => 'border-amber-500/40 bg-amber-100 text-amber-700',
                ],
                'summaryCards' => $this->emptySummaryCards(),
                'overview' => $this->emptyOverview($now),
                'lowStockThreshold' => 10,
                'lowStockItems' => collect(),
                'recentActivities' => collect(),
                'menuCards' => $menuCards,
            ]);
        }
    }

    private function buildOperationalDashboard(Carbon $now, array $shift, array $menuCards)
    {
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $activeSessionsCount = WorkingSession::query()->where('status', 'Working')->count();
        $pendingRequestsCount = ConsumableRequest::query()->where('status', 'Pending')->count();
        $availableRfDevicesCount = RfDevice::query()->where('status', 'Available')->count();

        $activePackingStationsCount = WorkingSession::query()
            ->where('status', 'Working')
            ->whereNotNull('packing_station_id')
            ->distinct('packing_station_id')
            ->count('packing_station_id');

        $totalPackingStationsCount = PackingStation::query()->count();

        $activeRfDevicesCount = WorkingSession::query()
            ->where('status', 'Working')
            ->whereNotNull('rf_device_id')
            ->distinct('rf_device_id')
            ->count('rf_device_id');

        $totalRfDevicesCount = RfDevice::query()->count();

        $minimumStockThreshold = (int) (SystemSetting::query()
            ->where('setting_key', 'minimum_consumable_stock')
            ->value('setting_value') ?? 10);

        $quickStatus = $this->resolveQuickStatus(
            pendingRequestsCount: $pendingRequestsCount,
            activeSessionsCount: $activeSessionsCount,
            availableRfDevicesCount: $availableRfDevicesCount,
        );

        return view('welcome', [
            'now' => $now,
            'currentShift' => $shift,
            'quickStatus' => $quickStatus,
            'summaryCards' => [
                [
                    'label' => 'Total Consumable Items',
                    'value' => (string) Consumable::query()->where('is_active', true)->count(),
                    'subvalue' => 'Master data aktif',
                ],
                [
                    'label' => 'Consumable Stock',
                    'value' => (string) Consumable::query()->where('is_active', true)->sum('stock'),
                    'subvalue' => 'Total stok tersedia',
                ],
                [
                    'label' => 'Active Daily Workers',
                    'value' => (string) WorkingSession::query()
                        ->where('status', 'Working')
                        ->distinct('daily_worker_id')
                        ->count('daily_worker_id'),
                    'subvalue' => 'Sedang bekerja',
                ],
                [
                    'label' => 'Active Packing Stations',
                    'value' => $activePackingStationsCount . ' / ' . $totalPackingStationsCount,
                    'subvalue' => 'Station aktif dipakai',
                ],
                [
                    'label' => 'Active RF Devices',
                    'value' => $activeRfDevicesCount . ' / ' . $totalRfDevicesCount,
                    'subvalue' => $availableRfDevicesCount . ' tersedia',
                ],
                [
                    'label' => 'Available WMS Accounts',
                    'value' => (string) WmsAccount::query()->where('status', 'Available')->count(),
                    'subvalue' => 'Siap dipakai',
                ],
                [
                    'label' => 'Pending Consumable Requests',
                    'value' => (string) $pendingRequestsCount,
                    'subvalue' => 'Menunggu validasi',
                ],
                [
                    'label' => 'Validated Requests Today',
                    'value' => (string) ConsumableRequest::query()
                        ->where('status', 'Validated')
                        ->whereBetween('validated_at', [$todayStart, $todayEnd])
                        ->count(),
                    'subvalue' => 'Disetujui hari ini',
                ],
                [
                    'label' => 'Working Sessions Today',
                    'value' => (string) WorkingSession::query()
                        ->whereBetween('started_at', [$todayStart, $todayEnd])
                        ->count(),
                    'subvalue' => 'Sesi dimulai hari ini',
                ],
                [
                    'label' => 'Consumable Usage Today',
                    'value' => (string) ConsumableRequestItem::query()
                        ->whereBetween('created_at', [$todayStart, $todayEnd])
                        ->sum('quantity'),
                    'subvalue' => 'Item keluar hari ini',
                ],
            ],
            'overview' => [
                'today_date' => $now->translatedFormat('l, d F Y'),
                'current_time' => $now->format('H:i:s'),
                'total_sessions' => WorkingSession::query()->whereBetween('started_at', [$todayStart, $todayEnd])->count(),
                'completed_sessions' => WorkingSession::query()
                    ->whereBetween('started_at', [$todayStart, $todayEnd])
                    ->where('status', 'Finished')
                    ->count(),
                'active_sessions' => $activeSessionsCount,
                'pending_validations' => $pendingRequestsCount,
            ],
            'lowStockThreshold' => $minimumStockThreshold,
            'lowStockItems' => Consumable::query()
                ->where('is_active', true)
                ->where('stock', '<=', $minimumStockThreshold)
                ->orderBy('stock')
                ->orderBy('name')
                ->limit(10)
                ->get(['name', 'stock']),
            'recentActivities' => $this->resolveRecentActivities(),
            'menuCards' => $menuCards,
        ]);
    }

    private function hasOperationalTables(): bool
    {
        return Schema::hasTable('consumables')
            && Schema::hasTable('consumable_requests')
            && Schema::hasTable('consumable_request_items')
            && Schema::hasTable('working_sessions')
            && Schema::hasTable('packing_stations')
            && Schema::hasTable('rf_devices')
            && Schema::hasTable('wms_accounts')
            && Schema::hasTable('system_settings');
    }

    private function emptySummaryCards(): array
    {
        return [
            ['label' => 'Total Consumable Items', 'value' => '0', 'subvalue' => 'Master data aktif'],
            ['label' => 'Consumable Stock', 'value' => '0', 'subvalue' => 'Total stok tersedia'],
            ['label' => 'Active Daily Workers', 'value' => '0', 'subvalue' => 'Sedang bekerja'],
            ['label' => 'Active Packing Stations', 'value' => '0 / 0', 'subvalue' => 'Station aktif dipakai'],
            ['label' => 'Active RF Devices', 'value' => '0 / 0', 'subvalue' => '0 tersedia'],
            ['label' => 'Available WMS Accounts', 'value' => '0', 'subvalue' => 'Siap dipakai'],
            ['label' => 'Pending Consumable Requests', 'value' => '0', 'subvalue' => 'Menunggu validasi'],
            ['label' => 'Validated Requests Today', 'value' => '0', 'subvalue' => 'Disetujui hari ini'],
            ['label' => 'Working Sessions Today', 'value' => '0', 'subvalue' => 'Sesi dimulai hari ini'],
            ['label' => 'Consumable Usage Today', 'value' => '0', 'subvalue' => 'Item keluar hari ini'],
        ];
    }

    private function emptyOverview(Carbon $now): array
    {
        return [
            'today_date' => $now->translatedFormat('l, d F Y'),
            'current_time' => $now->format('H:i:s'),
            'total_sessions' => 0,
            'completed_sessions' => 0,
            'active_sessions' => 0,
            'pending_validations' => 0,
        ];
    }

    private function resolveCurrentShift(Carbon $now): array
    {
        $hourMinute = $now->format('H:i');

        if ($hourMinute >= '07:00' && $hourMinute < '15:00') {
            return [
                'label' => 'Morning Shift',
                'range' => '07:00 - 15:00',
            ];
        }

        if ($hourMinute >= '15:00' && $hourMinute < '23:00') {
            return [
                'label' => 'Afternoon Shift',
                'range' => '15:00 - 23:00',
            ];
        }

        return [
            'label' => 'Night Shift',
            'range' => '23:00 - 07:00',
        ];
    }

    private function resolveQuickStatus(int $pendingRequestsCount, int $activeSessionsCount, int $availableRfDevicesCount): array
    {
        if (($pendingRequestsCount > 0 && $activeSessionsCount === 0) || ($activeSessionsCount > 0 && $availableRfDevicesCount === 0)) {
            return [
                'emoji' => '🔴',
                'label' => 'System Requires Attention',
                'class' => 'border-red-500/40 bg-red-100 text-red-700',
            ];
        }

        if ($pendingRequestsCount > 0) {
            return [
                'emoji' => '🟡',
                'label' => 'Waiting Validation',
                'class' => 'border-amber-500/40 bg-amber-100 text-amber-700',
            ];
        }

        return [
            'emoji' => '🟢',
            'label' => 'Warehouse Operational',
            'class' => 'border-green-500/40 bg-green-100 text-green-700',
        ];
    }

    private function resolveRecentActivities(): Collection
    {
        $startedSessions = WorkingSession::query()
            ->with(['dailyWorker:id,name', 'packingStation:id,name', 'rfDevice:id,code'])
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(function (WorkingSession $session) {
                return [
                    'time' => $session->started_at,
                    'time_label' => $session->started_at?->format('H:i') ?? '-',
                    'subject' => $session->dailyWorker?->name ?? '-',
                    'action' => 'Started Working Session',
                    'detail' => $session->packingStation?->name ?? ($session->rfDevice?->code ?? '-'),
                ];
            });

        $finishedSessions = WorkingSession::query()
            ->with(['dailyWorker:id,name', 'packingStation:id,name', 'rfDevice:id,code'])
            ->whereNotNull('ended_at')
            ->latest('ended_at')
            ->limit(10)
            ->get()
            ->map(function (WorkingSession $session) {
                return [
                    'time' => $session->ended_at,
                    'time_label' => $session->ended_at?->format('H:i') ?? '-',
                    'subject' => $session->dailyWorker?->name ?? '-',
                    'action' => 'Finished Working Session',
                    'detail' => $session->packingStation?->name ?? ($session->rfDevice?->code ?? '-'),
                ];
            });

        $requestedConsumables = ConsumableRequest::query()
            ->with('dailyWorker:id,name')
            ->latest('requested_at')
            ->limit(10)
            ->get()
            ->map(function (ConsumableRequest $request) {
                return [
                    'time' => $request->requested_at,
                    'time_label' => $request->requested_at?->format('H:i') ?? '-',
                    'subject' => $request->dailyWorker?->name ?? $request->request_number,
                    'action' => 'Requested Consumable',
                    'detail' => $request->status === 'Pending' ? 'Waiting Validation' : $request->status,
                ];
            });

        $validatedRequests = ConsumableRequest::query()
            ->with('validator:id,name')
            ->whereNotNull('validated_at')
            ->latest('validated_at')
            ->limit(10)
            ->get()
            ->map(function (ConsumableRequest $request) {
                return [
                    'time' => $request->validated_at,
                    'time_label' => $request->validated_at?->format('H:i') ?? '-',
                    'subject' => $request->validator?->name ?? 'Leader',
                    'action' => 'Validated Consumable Request',
                    'detail' => $request->request_number,
                ];
            });

        return $startedSessions
            ->concat($finishedSessions)
            ->concat($requestedConsumables)
            ->concat($validatedRequests)
            ->filter(fn (array $activity) => $activity['time'] !== null)
            ->sortByDesc('time')
            ->values()
            ->take(10);
    }
}
