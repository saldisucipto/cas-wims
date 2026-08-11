<?php

namespace App\Http\Controllers;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\Consumable;
use App\Models\DailyWorker;
use App\Models\RfDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdministrationDashboardController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return view('administration.dashboard', [
            'consumableCount' => Consumable::count(),
            'rfDeviceCount' => RfDevice::count(),
            'dailyWorkerCount' => DailyWorker::count(),
            'systemUserCount' => User::count(),
            'atkItemCount' => AtkItem::count(),
            'atkStockTotal' => AtkItem::sum('current_stock'),
            'pendingAtkRequestCount' => AtkRequest::query()->where('status', 'Pending')->count(),
            'approvedAtkTodayCount' => AtkRequest::query()->where('status', 'Approved')->whereDate('approved_at', today())->count(),
            'pendingAtkRequests' => AtkRequest::query()
                ->with(['requester', 'items.atkItem'])
                ->where('status', 'Pending')
                ->latest('requested_at')
                ->take(10)
                ->get(),
        ]);
    }
}
