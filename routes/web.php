<?php

use App\Http\Controllers\AdministrationAuthController;
use App\Http\Controllers\AdministrationDashboardController;
use App\Http\Controllers\AtkController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LeaderAuthController;
use App\Http\Controllers\LeaderPanelController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfController;
use App\Http\Controllers\SystemConfigController;
use App\Http\Controllers\SystemUserController;
use App\Http\Controllers\WelcomeDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeDashboardController::class);

Route::get('/packing-station-registration', [PackingController::class, 'registration'])->name('packing.registration');
Route::get('/packing-dashboard', [PackingController::class, 'dashboard'])->name('packing.dashboard');
Route::get('/request-consumable', [PackingController::class, 'requestConsumable'])->name('packing.request-consumable');
Route::post('/request-consumable', [PackingController::class, 'submitConsumableRequest'])->name('packing.request-consumable.submit');
Route::get('/waiting-leader-validation', [PackingController::class, 'waitingLeaderValidation'])->name('packing.waiting-leader-validation');

Route::get('/rf-handheld-registration', [RfController::class, 'registration'])->name('rf.registration');
Route::get('/rf-handheld-dashboard', [RfController::class, 'dashboard'])->name('rf.dashboard');

Route::get('/administration-login', [AdministrationAuthController::class, 'create'])->name('administration.login');
Route::post('/administration-login', [AdministrationAuthController::class, 'store'])->name('administration.login.submit');
Route::post('/administration-logout', [AdministrationAuthController::class, 'destroy'])->name('administration.logout');
Route::get('/administration-dashboard', [AdministrationDashboardController::class, 'index'])->name('administration.dashboard');

Route::get('/leader-login', [LeaderAuthController::class, 'create'])->name('leader.login');
Route::post('/leader-login', [LeaderAuthController::class, 'store'])->name('leader.login.submit');
Route::post('/leader-logout', [LeaderAuthController::class, 'destroy'])->name('leader.logout');
Route::get('/leader-panel', [LeaderPanelController::class, 'index'])->name('leader.panel');
Route::post('/leader-consumable-requests/{consumableRequest}/validate', [LeaderPanelController::class, 'validateRequest'])->name('leader.requests.validate');
Route::post('/leader-consumable-requests/{consumableRequest}/reject', [LeaderPanelController::class, 'rejectRequest'])->name('leader.requests.reject');
Route::get('/atk/requests', [AtkController::class, 'requests'])->name('atk.requests');
Route::post('/atk/requests', [AtkController::class, 'storeRequest'])->name('atk.requests.store');
Route::get('/atk/take', [AtkController::class, 'take'])->name('atk.take');
Route::post('/atk/take', [AtkController::class, 'storeTake'])->name('atk.take.store');

Route::get('/administration/master-data/consumables', [MasterDataController::class, 'consumables'])->name('administration.master.consumables');
Route::get('/administration/master-data/consumables/template', [MasterDataController::class, 'downloadConsumableTemplate'])->name('administration.master.consumables.template');
Route::post('/administration/master-data/consumables/import', [MasterDataController::class, 'importConsumables'])->name('administration.master.consumables.import');
Route::post('/administration/master-data/consumables', [MasterDataController::class, 'storeConsumable'])->name('administration.master.consumables.store');
Route::post('/administration/master-data/consumables/{consumable}', [MasterDataController::class, 'updateConsumable'])->name('administration.master.consumables.update');
Route::post('/administration/master-data/consumables/{consumable}/delete', [MasterDataController::class, 'destroyConsumable'])->name('administration.master.consumables.delete');
Route::get('/administration/master-data/atk', [AtkController::class, 'master'])->name('administration.master.atk');
Route::get('/administration/master-data/atk/template', [AtkController::class, 'downloadAtkTemplate'])->name('administration.master.atk.template');
Route::post('/administration/master-data/atk/import', [AtkController::class, 'importAtk'])->name('administration.master.atk.import');
Route::post('/administration/master-data/atk', [AtkController::class, 'storeMaster'])->name('administration.master.atk.store');
Route::post('/administration/master-data/atk/{atkItem}', [AtkController::class, 'updateMaster'])->name('administration.master.atk.update');
Route::post('/administration/master-data/atk/{atkItem}/delete', [AtkController::class, 'destroyMaster'])->name('administration.master.atk.delete');

Route::get('/administration/master-data/rf-devices', [MasterDataController::class, 'rfDevices'])->name('administration.master.rf-devices');
Route::post('/administration/master-data/rf-devices', [MasterDataController::class, 'storeRfDevice'])->name('administration.master.rf-devices.store');
Route::post('/administration/master-data/rf-devices/{rfDevice}', [MasterDataController::class, 'updateRfDevice'])->name('administration.master.rf-devices.update');
Route::post('/administration/master-data/rf-devices/{rfDevice}/delete', [MasterDataController::class, 'destroyRfDevice'])->name('administration.master.rf-devices.delete');

Route::get('/administration/master-data/packing-stations', [MasterDataController::class, 'packingStations'])->name('administration.master.packing-stations');
Route::post('/administration/master-data/packing-stations', [MasterDataController::class, 'storePackingStation'])->name('administration.master.packing-stations.store');
Route::post('/administration/master-data/packing-stations/{packingStation}', [MasterDataController::class, 'updatePackingStation'])->name('administration.master.packing-stations.update');
Route::post('/administration/master-data/packing-stations/{packingStation}/delete', [MasterDataController::class, 'destroyPackingStation'])->name('administration.master.packing-stations.delete');

Route::get('/administration/master-data/daily-workers', [MasterDataController::class, 'dailyWorkers'])->name('administration.master.daily-workers');
Route::get('/administration/master-data/daily-workers/template', [MasterDataController::class, 'downloadDailyWorkerTemplate'])->name('administration.master.daily-workers.template');
Route::post('/administration/master-data/daily-workers/import', [MasterDataController::class, 'importDailyWorkers'])->name('administration.master.daily-workers.import');
Route::post('/administration/master-data/daily-workers', [MasterDataController::class, 'storeDailyWorker'])->name('administration.master.daily-workers.store');
Route::post('/administration/master-data/daily-workers/{dailyWorker}', [MasterDataController::class, 'updateDailyWorker'])->name('administration.master.daily-workers.update');
Route::post('/administration/master-data/daily-workers/{dailyWorker}/delete', [MasterDataController::class, 'destroyDailyWorker'])->name('administration.master.daily-workers.delete');

Route::get('/administration/master-data/wms-accounts', [MasterDataController::class, 'wmsAccounts'])->name('administration.master.wms-accounts');
Route::get('/administration/master-data/wms-accounts/template', [MasterDataController::class, 'downloadWmsAccountTemplate'])->name('administration.master.wms-accounts.template');
Route::post('/administration/master-data/wms-accounts/import', [MasterDataController::class, 'importWmsAccounts'])->name('administration.master.wms-accounts.import');
Route::post('/administration/master-data/wms-accounts', [MasterDataController::class, 'storeWmsAccount'])->name('administration.master.wms-accounts.store');
Route::post('/administration/master-data/wms-accounts/{wmsAccount}', [MasterDataController::class, 'updateWmsAccount'])->name('administration.master.wms-accounts.update');
Route::post('/administration/master-data/wms-accounts/{wmsAccount}/delete', [MasterDataController::class, 'destroyWmsAccount'])->name('administration.master.wms-accounts.delete');

Route::get('/administration/master-data/system-users', [SystemUserController::class, 'index'])->name('administration.master.system-users');
Route::post('/administration/master-data/system-users', [SystemUserController::class, 'store'])->name('administration.master.system-users.store');
Route::post('/administration/master-data/system-users/{user}', [SystemUserController::class, 'update'])->name('administration.master.system-users.update');
Route::post('/administration/master-data/system-users/{user}/delete', [SystemUserController::class, 'destroy'])->name('administration.master.system-users.delete');

Route::get('/administration/inventory/receiving', [InventoryController::class, 'receiving'])->name('administration.inventory.receiving');
Route::post('/administration/inventory/receiving', [InventoryController::class, 'storeReceiving'])->name('administration.inventory.receiving.store');
Route::get('/administration/inventory/atk-receiving', [AtkController::class, 'receiving'])->name('administration.inventory.atk-receiving');
Route::post('/administration/inventory/atk-receiving', [AtkController::class, 'storeReceiving'])->name('administration.inventory.atk-receiving.store');
Route::get('/administration/inventory/adjustment', [InventoryController::class, 'adjustment'])->name('administration.inventory.adjustment');
Route::post('/administration/inventory/adjustment', [InventoryController::class, 'storeAdjustment'])->name('administration.inventory.adjustment.store');
Route::get('/administration/inventory/opname', [InventoryController::class, 'opname'])->name('administration.inventory.opname');
Route::post('/administration/inventory/opname', [InventoryController::class, 'storeOpname'])->name('administration.inventory.opname.store');
Route::get('/administration/inventory/transactions', [InventoryController::class, 'history'])->name('administration.inventory.transactions');

Route::get('/administration/reports/working-sessions', [ReportController::class, 'workingSessions'])->name('administration.reports.working-sessions');
Route::post('/administration/reports/working-sessions/{workingSession}/force-close', [ReportController::class, 'forceCloseWorkingSession'])->name('administration.reports.working-sessions.force-close');
Route::get('/administration/reports/consumable-usage', [ReportController::class, 'consumableUsage'])->name('administration.reports.consumable-usage');
Route::get('/administration/reports/inventory', [ReportController::class, 'inventory'])->name('administration.reports.inventory');
Route::get('/administration/reports/atk-stock-card', [AtkController::class, 'stockCard'])->name('administration.reports.atk-stock-card');
Route::get('/administration/reports/atk-stock-card/print', [AtkController::class, 'printStockCard'])->name('administration.reports.atk-stock-card.print');
Route::get('/administration/reports/rf-device-usage', [ReportController::class, 'rfDeviceUsage'])->name('administration.reports.rf-device-usage');
Route::get('/administration/reports/daily-worker-activity', [ReportController::class, 'dailyWorkerActivity'])->name('administration.reports.daily-worker-activity');
Route::post('/administration/atk-requests/{atkRequest}/approve', [AtkController::class, 'approveRequest'])->name('administration.atk-requests.approve');
Route::post('/administration/atk-requests/{atkRequest}/reject', [AtkController::class, 'rejectRequest'])->name('administration.atk-requests.reject');

Route::get('/administration/system/warehouse-settings', [SystemConfigController::class, 'warehouseSettings'])->name('administration.system.warehouse-settings');
Route::post('/administration/system/warehouse-settings', [SystemConfigController::class, 'saveWarehouseSettings'])->name('administration.system.warehouse-settings.save');
Route::get('/administration/system/shift-settings', [SystemConfigController::class, 'shiftSettings'])->name('administration.system.shift-settings');
Route::post('/administration/system/shift-settings', [SystemConfigController::class, 'saveShiftSettings'])->name('administration.system.shift-settings.save');
Route::get('/administration/system/activity-logs', [SystemConfigController::class, 'activityLogs'])->name('administration.system.activity-logs');
