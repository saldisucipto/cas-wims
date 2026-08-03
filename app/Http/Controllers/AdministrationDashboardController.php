<?php

namespace App\Http\Controllers;

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
        ]);
    }
}
