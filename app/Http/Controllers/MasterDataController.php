<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\DailyWorker;
use App\Models\PackingStation;
use App\Models\RfDevice;
use App\Models\WmsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MasterDataController extends Controller
{
    public function consumables(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = Consumable::query()->orderBy('name');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->string('q') . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status') === 'Active');
        }

        return view('administration.master.consumables', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
        ]);
    }

    public function storeConsumable(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:consumables,name'],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        Consumable::create([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'stock' => $data['stock'],
            'is_active' => $data['status'] === 'Active',
        ]);

        return back()->with('success', 'Consumable created.');
    }

    public function updateConsumable(Request $request, Consumable $consumable)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:consumables,name,' . $consumable->id],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $consumable->update([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'stock' => $data['stock'],
            'is_active' => $data['status'] === 'Active',
        ]);

        return back()->with('success', 'Consumable updated.');
    }

    public function destroyConsumable(Consumable $consumable)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $consumable->delete();

        return back()->with('success', 'Consumable deleted.');
    }

    public function rfDevices(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = RfDevice::query()->with('wmsAccount')->orderBy('code');

        if ($request->filled('q')) {
            $query->where('code', 'like', '%' . $request->string('q') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.rf-devices', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
            'wmsAccounts' => WmsAccount::query()->where('status', '!=', 'Disabled')->orderBy('username')->get(),
        ]);
    }

    public function storeRfDevice(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:rf_devices,code'],
            'status' => ['required', 'in:Available,In Use,Maintenance'],
            'wms_account_id' => ['nullable', 'exists:wms_accounts,id'],
        ]);

        $rfDevice = RfDevice::create($data);

        if ($rfDevice->wms_account_id) {
            WmsAccount::query()->whereKey($rfDevice->wms_account_id)->update(['status' => 'Assigned']);
        }

        return back()->with('success', 'RF device created.');
    }

    public function updateRfDevice(Request $request, RfDevice $rfDevice)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:rf_devices,code,' . $rfDevice->id],
            'status' => ['required', 'in:Available,In Use,Maintenance'],
            'wms_account_id' => ['nullable', 'exists:wms_accounts,id'],
        ]);

        $oldWmsAccountId = $rfDevice->wms_account_id;

        $rfDevice->update($data);

        if ($oldWmsAccountId && $oldWmsAccountId !== $rfDevice->wms_account_id) {
            WmsAccount::query()->whereKey($oldWmsAccountId)->update(['status' => 'Available']);
        }

        if ($rfDevice->wms_account_id) {
            WmsAccount::query()->whereKey($rfDevice->wms_account_id)->update(['status' => 'Assigned']);
        }

        return back()->with('success', 'RF device updated.');
    }

    public function destroyRfDevice(RfDevice $rfDevice)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if ($rfDevice->wms_account_id) {
            WmsAccount::query()->whereKey($rfDevice->wms_account_id)->update(['status' => 'Available']);
        }

        $rfDevice->delete();

        return back()->with('success', 'RF device deleted.');
    }

    public function packingStations(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = PackingStation::query()->with('wmsAccount')->orderBy('code');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('code', 'like', '%' . $request->string('q') . '%')
                    ->orWhere('name', 'like', '%' . $request->string('q') . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.packing-stations', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
            'wmsAccounts' => WmsAccount::query()->where('status', '!=', 'Disabled')->orderBy('username')->get(),
        ]);
    }

    public function storePackingStation(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:packing_stations,code'],
            'station_number' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'qr_code' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive,Maintenance'],
            'wms_account_id' => ['nullable', 'exists:wms_accounts,id'],
        ]);

        $packingStation = PackingStation::create($data);

        if ($packingStation->wms_account_id) {
            WmsAccount::query()->whereKey($packingStation->wms_account_id)->update(['status' => 'Assigned']);
        }

        return back()->with('success', 'Packing station created.');
    }

    public function updatePackingStation(Request $request, PackingStation $packingStation)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:packing_stations,code,' . $packingStation->id],
            'station_number' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'qr_code' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive,Maintenance'],
            'wms_account_id' => ['nullable', 'exists:wms_accounts,id'],
        ]);

        $oldWmsAccountId = $packingStation->wms_account_id;

        $packingStation->update($data);

        if ($oldWmsAccountId && $oldWmsAccountId !== $packingStation->wms_account_id) {
            WmsAccount::query()->whereKey($oldWmsAccountId)->update(['status' => 'Available']);
        }

        if ($packingStation->wms_account_id) {
            WmsAccount::query()->whereKey($packingStation->wms_account_id)->update(['status' => 'Assigned']);
        }

        return back()->with('success', 'Packing station updated.');
    }

    public function destroyPackingStation(PackingStation $packingStation)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        if ($packingStation->wms_account_id) {
            WmsAccount::query()->whereKey($packingStation->wms_account_id)->update(['status' => 'Available']);
        }

        $packingStation->delete();

        return back()->with('success', 'Packing station deleted.');
    }

    public function dailyWorkers(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = DailyWorker::query()->orderBy('employee_code');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('employee_code', 'like', '%' . $request->string('q') . '%')
                    ->orWhere('name', 'like', '%' . $request->string('q') . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.daily-workers', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
        ]);
    }

    public function storeDailyWorker(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:255', 'unique:daily_workers,employee_code'],
            'name' => ['required', 'string', 'max:255', 'unique:daily_workers,name'],
            'function' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        DailyWorker::create([
            ...$data,
            'division' => 'Packer',
            'is_active' => $data['status'] === 'Active',
        ]);

        return back()->with('success', 'Daily worker created.');
    }

    public function updateDailyWorker(Request $request, DailyWorker $dailyWorker)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:255', 'unique:daily_workers,employee_code,' . $dailyWorker->id],
            'name' => ['required', 'string', 'max:255', 'unique:daily_workers,name,' . $dailyWorker->id],
            'function' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $dailyWorker->update([
            ...$data,
            'is_active' => $data['status'] === 'Active',
        ]);

        return back()->with('success', 'Daily worker updated.');
    }

    public function destroyDailyWorker(DailyWorker $dailyWorker)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $dailyWorker->delete();

        return back()->with('success', 'Daily worker deleted.');
    }

    public function wmsAccounts(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = WmsAccount::query()->orderBy('username');

        if ($request->filled('q')) {
            $query->where('username', 'like', '%' . $request->string('q') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.wms-accounts', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
        ]);
    }

    public function storeWmsAccount(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:wms_accounts,username'],
            'password' => ['required', 'string', 'max:255'],
            'function' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Available,Assigned,Disabled'],
        ]);

        WmsAccount::create($data);

        return back()->with('success', 'WMS account created.');
    }

    public function updateWmsAccount(Request $request, WmsAccount $wmsAccount)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:wms_accounts,username,' . $wmsAccount->id],
            'password' => ['required', 'string', 'max:255'],
            'function' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:Available,Assigned,Disabled'],
        ]);

        $wmsAccount->update($data);

        return back()->with('success', 'WMS account updated.');
    }

    public function destroyWmsAccount(WmsAccount $wmsAccount)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $wmsAccount->delete();

        return back()->with('success', 'WMS account deleted.');
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
