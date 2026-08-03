<?php

namespace App\Http\Controllers;

use App\Models\ConsumableRequest;
use App\Models\WorkingSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaderPanelController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'Leader') {
            return redirect()->route('leader.login');
        }

        $activeSessions = WorkingSession::query()
            ->with(['dailyWorker', 'packingStation', 'rfDevice'])
            ->where('status', 'Working')
            ->orderBy('started_at')
            ->get();

        $activeWorkers = $activeSessions->map(function (WorkingSession $session) {
            return [
                'name' => $session->dailyWorker?->name ?? '-',
                'function' => $session->dailyWorker?->function ?? 'Outbound',
                'division' => $session->dailyWorker?->division ?? 'Packer',
                'workstation' => $session->packingStation?->name ?? '-',
                'rf_device' => $session->rfDevice?->code ?? '-',
                'since' => $session->started_at?->format('H:i') ?? '-',
                'shift' => 'Morning',
                'status' => '🟢 Working',
            ];
        })->all();

        $pendingRequests = ConsumableRequest::query()
            ->with(['dailyWorker', 'rfDevice', 'items.consumable'])
            ->where('status', 'Pending')
            ->orderBy('requested_at')
            ->get()
            ->map(function (ConsumableRequest $requestItem) {
                return [
                    'id' => $requestItem->id,
                    'request_number' => $requestItem->request_number,
                    'name' => $requestItem->dailyWorker?->name ?? '-',
                    'function' => $requestItem->dailyWorker?->function ?? 'Outbound',
                    'division' => $requestItem->dailyWorker?->division ?? 'Packer',
                    'rf_device' => $requestItem->rfDevice?->code ?? '-',
                    'request_time' => $requestItem->requested_at?->format('H:i') ?? '-',
                    'items' => $requestItem->items->map(function ($item) {
                        return [
                            'name' => $item->consumable?->name ?? '-',
                            'qty' => $item->quantity,
                        ];
                    })->values()->all(),
                ];
            })->all();

        $validatedRequests = ConsumableRequest::query()
            ->with(['dailyWorker', 'rfDevice'])
            ->where('status', 'Validated')
            ->latest('validated_at')
            ->take(10)
            ->get();

        $rejectedRequests = ConsumableRequest::query()
            ->with(['dailyWorker', 'rfDevice'])
            ->where('status', 'Rejected')
            ->latest('rejected_at')
            ->take(10)
            ->get();

        $today = Carbon::today();

        $summary = [
            [
                'label' => 'Active Workers',
                'value' => (string) WorkingSession::query()
                    ->where('status', 'Working')
                    ->distinct('daily_worker_id')
                    ->count('daily_worker_id'),
                'subvalue' => 'Employees',
            ],
            [
                'label' => 'Pending Requests',
                'value' => (string) ConsumableRequest::query()->where('status', 'Pending')->count(),
                'subvalue' => 'Need validation',
            ],
            [
                'label' => 'Validated Today',
                'value' => (string) ConsumableRequest::query()
                    ->where('status', 'Validated')
                    ->whereDate('validated_at', $today)
                    ->count(),
                'subvalue' => 'Approved',
            ],
            [
                'label' => 'Rejected Today',
                'value' => (string) ConsumableRequest::query()
                    ->where('status', 'Rejected')
                    ->whereDate('rejected_at', $today)
                    ->count(),
                'subvalue' => 'Declined',
            ],
        ];

        return view('leader.panel', [
            'activeWorkers' => $activeWorkers,
            'pendingRequests' => $pendingRequests,
            'validatedRequests' => $validatedRequests,
            'rejectedRequests' => $rejectedRequests,
            'summary' => $summary,
        ]);
    }

    public function validateRequest(Request $request, ConsumableRequest $consumableRequest)
    {
        if (! Auth::check() || Auth::user()->role !== 'Leader') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($consumableRequest->status !== 'Pending') {
            return response()->json(['message' => 'Request already processed.'], 422);
        }

        $consumableRequest->update([
            'status' => 'Validated',
            'validated_at' => now(),
            'validated_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Consumable Request Validated Successfully.']);
    }

    public function rejectRequest(Request $request, ConsumableRequest $consumableRequest)
    {
        if (! Auth::check() || Auth::user()->role !== 'Leader') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($consumableRequest->status !== 'Pending') {
            return response()->json(['message' => 'Request already processed.'], 422);
        }

        $consumableRequest->update([
            'status' => 'Rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Consumable Request Rejected.']);
    }
}
