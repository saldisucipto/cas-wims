<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\DailyWorker;
use App\Models\PackingStation;
use App\Models\RfDevice;
use App\Models\WmsAccount;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class MasterDataController extends Controller
{
    private const DAILY_WORKER_IMPORT_HEADERS = [
        'employee_code',
        'name',
        'function',
        'division',
        'position',
        'status',
    ];

    private const WMS_ACCOUNT_IMPORT_HEADERS = [
        'username',
        'password',
        'function',
        'status',
    ];

    private const CONSUMABLE_IMPORT_HEADERS = [
        'sku',
        'sku_barcode',
        'name',
        'unit',
        'stock',
        'status',
    ];

    public function consumables(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = Consumable::query()->orderBy('name');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('name', 'like', '%'.$request->string('q').'%')
                    ->orWhere('sku', 'like', '%'.$request->string('q').'%')
                    ->orWhere('sku_barcode', 'like', '%'.$request->string('q').'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status') === 'Active');
        }

        return view('administration.master.consumables', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
            'importHeaders' => self::CONSUMABLE_IMPORT_HEADERS,
        ]);
    }

    public function downloadConsumableTemplate()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $temporaryFile = $this->createImportTemplateFile(
            'consumables-template-',
            self::CONSUMABLE_IMPORT_HEADERS,
            'Consumables'
        );

        return response()->download(
            $temporaryFile,
            'consumables-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importConsumables(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'file' => ['required', File::types(['xlsx', 'csv'])->max('5mb')],
        ]);

        $rows = $this->normalizeImportRows(
            $this->parseImportedSpreadsheetFile($data['file']),
            self::CONSUMABLE_IMPORT_HEADERS
        );

        $validatedRows = validator(
            ['rows' => $rows],
            [
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.sku' => ['nullable', 'string', 'max:255', 'distinct'],
                'rows.*.sku_barcode' => ['nullable', 'string', 'max:255', 'distinct'],
                'rows.*.name' => ['required', 'string', 'max:255', 'distinct'],
                'rows.*.unit' => ['required', 'string', 'max:30'],
                'rows.*.stock' => ['required', 'integer', 'min:0'],
                'rows.*.status' => ['required', 'in:Active,Inactive'],
            ],
            [
                'rows.min' => 'Import file does not contain any consumable rows.',
                'rows.*.sku.distinct' => 'SKU in the import file must be unique.',
                'rows.*.sku_barcode.distinct' => 'SKU barcode in the import file must be unique.',
                'rows.*.name.distinct' => 'Consumable name in the import file must be unique.',
            ]
        )->validate()['rows'];

        [$createdCount, $updatedCount] = $this->persistImportedConsumables($validatedRows);

        return redirect()
            ->route('administration.master.consumables')
            ->with('success', "Consumable import completed. {$createdCount} created, {$updatedCount} updated.");
    }

    public function storeConsumable(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:255', 'unique:consumables,sku'],
            'sku_barcode' => ['nullable', 'string', 'max:255', 'unique:consumables,sku_barcode'],
            'name' => ['required', 'string', 'max:255', 'unique:consumables,name'],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        Consumable::create([
            'sku' => $data['sku'] ?: null,
            'sku_barcode' => $data['sku_barcode'] ?: null,
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
            'sku' => ['nullable', 'string', 'max:255', 'unique:consumables,sku,'.$consumable->id],
            'sku_barcode' => ['nullable', 'string', 'max:255', 'unique:consumables,sku_barcode,'.$consumable->id],
            'name' => ['required', 'string', 'max:255', 'unique:consumables,name,'.$consumable->id],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $consumable->update([
            'sku' => $data['sku'] ?: null,
            'sku_barcode' => $data['sku_barcode'] ?: null,
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
            $query->where('code', 'like', '%'.$request->string('q').'%');
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
            'code' => ['required', 'string', 'max:255', 'unique:rf_devices,code,'.$rfDevice->id],
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
                $builder->where('code', 'like', '%'.$request->string('q').'%')
                    ->orWhere('name', 'like', '%'.$request->string('q').'%');
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
            'code' => ['required', 'string', 'max:255', 'unique:packing_stations,code,'.$packingStation->id],
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
                $builder->where('employee_code', 'like', '%'.$request->string('q').'%')
                    ->orWhere('name', 'like', '%'.$request->string('q').'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.daily-workers', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
            'importHeaders' => self::DAILY_WORKER_IMPORT_HEADERS,
        ]);
    }

    public function downloadDailyWorkerTemplate()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $temporaryFile = $this->createImportTemplateFile(
            'daily-workers-template-',
            self::DAILY_WORKER_IMPORT_HEADERS,
            'Daily Workers'
        );

        return response()->download(
            $temporaryFile,
            'daily-workers-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importDailyWorkers(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'file' => ['required', File::types(['xlsx', 'csv'])->max('5mb')],
        ]);

        $rows = $this->normalizeImportRows(
            $this->parseImportedSpreadsheetFile($data['file']),
            self::DAILY_WORKER_IMPORT_HEADERS
        );

        $validatedRows = validator(
            ['rows' => $rows],
            [
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.employee_code' => ['required', 'string', 'max:255', 'distinct'],
                'rows.*.name' => ['required', 'string', 'max:255', 'distinct'],
                'rows.*.function' => ['required', 'string', 'max:255'],
                'rows.*.division' => ['required', 'string', 'max:255'],
                'rows.*.position' => ['required', 'string', 'max:255'],
                'rows.*.status' => ['required', 'in:Active,Inactive'],
            ],
            [
                'rows.min' => 'Import file does not contain any employee rows.',
                'rows.*.employee_code.distinct' => 'Employee code in the import file must be unique.',
                'rows.*.name.distinct' => 'Employee name in the import file must be unique.',
            ]
        )->validate()['rows'];

        [$createdCount, $updatedCount] = $this->persistImportedDailyWorkers($validatedRows);

        return redirect()
            ->route('administration.master.daily-workers')
            ->with('success', "Daily worker import completed. {$createdCount} created, {$updatedCount} updated.");
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
            'employee_code' => ['required', 'string', 'max:255', 'unique:daily_workers,employee_code,'.$dailyWorker->id],
            'name' => ['required', 'string', 'max:255', 'unique:daily_workers,name,'.$dailyWorker->id],
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
            $query->where('username', 'like', '%'.$request->string('q').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.wms-accounts', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'status'),
            'importHeaders' => self::WMS_ACCOUNT_IMPORT_HEADERS,
        ]);
    }

    public function downloadWmsAccountTemplate()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $temporaryFile = $this->createImportTemplateFile(
            'wms-accounts-template-',
            self::WMS_ACCOUNT_IMPORT_HEADERS,
            'WMS Accounts'
        );

        return response()->download(
            $temporaryFile,
            'wms-accounts-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importWmsAccounts(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'file' => ['required', File::types(['xlsx', 'csv'])->max('5mb')],
        ]);

        $rows = $this->normalizeImportRows(
            $this->parseImportedSpreadsheetFile($data['file']),
            self::WMS_ACCOUNT_IMPORT_HEADERS
        );

        $validatedRows = validator(
            ['rows' => $rows],
            [
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.username' => ['required', 'string', 'max:255', 'distinct'],
                'rows.*.password' => ['required', 'string', 'max:255'],
                'rows.*.function' => ['required', 'string', 'max:255'],
                'rows.*.status' => ['required', 'in:Available,Assigned,Disabled'],
            ],
            [
                'rows.min' => 'Import file does not contain any WMS account rows.',
                'rows.*.username.distinct' => 'Username in the import file must be unique.',
            ]
        )->validate()['rows'];

        [$createdCount, $updatedCount] = $this->persistImportedWmsAccounts($validatedRows);

        return redirect()
            ->route('administration.master.wms-accounts')
            ->with('success', "WMS account import completed. {$createdCount} created, {$updatedCount} updated.");
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
            'username' => ['required', 'string', 'max:255', 'unique:wms_accounts,username,'.$wmsAccount->id],
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

    private function createImportTemplateFile(string $prefix, array $headers, string $sheetName): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), $prefix);

        if ($temporaryFile === false) {
            abort(500, 'Unable to create template file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to build template file.');
        }

        $sharedStrings = $headers;

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelationshipsXml());
        $zip->addFromString('docProps/app.xml', $this->xlsxAppPropertiesXml());
        $zip->addFromString('docProps/core.xml', $this->xlsxCorePropertiesXml());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->xlsxSharedStringsXml($sharedStrings));
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheetXml(count($sharedStrings)));
        $zip->close();

        return $temporaryFile;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseImportedSpreadsheetFile(UploadedFile $file): array
    {
        $extension = strtolower($file->extension());

        if ($extension === 'csv') {
            return $this->parseImportCsv($file->getRealPath());
        }

        if ($extension === 'xlsx') {
            return $this->parseImportXlsx($file->getRealPath());
        }

        throw ValidationException::withMessages([
            'file' => ['Unsupported import file type. Use the provided XLSX template or a CSV file.'],
        ]);
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeImportRows(array $rows, array $expectedHeaders): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['Import file is empty.'],
            ]);
        }

        $headerRow = array_shift($rows) ?? [];
        $headers = array_map(function (?string $value): string {
            return trim((string) $value);
        }, $headerRow);

        if ($headers !== $expectedHeaders) {
            throw ValidationException::withMessages([
                'file' => ['Invalid template header. Download the latest template and try again.'],
            ]);
        }

        $normalizedRows = [];

        foreach ($rows as $rowIndex => $row) {
            $mappedRow = [];

            foreach ($expectedHeaders as $columnIndex => $header) {
                $mappedRow[$header] = trim((string) ($row[$columnIndex] ?? ''));
            }

            if (count(array_filter($mappedRow, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $normalizedRows[] = $mappedRow;
        }

        return $normalizedRows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0:int,1:int}
     */
    private function persistImportedWmsAccounts(array $rows): array
    {
        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($rows, &$createdCount, &$updatedCount): void {
            foreach ($rows as $row) {
                $wmsAccount = WmsAccount::query()->firstOrNew([
                    'username' => $row['username'],
                ]);

                $exists = $wmsAccount->exists;

                $wmsAccount->fill([
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'function' => $row['function'],
                    'status' => $row['status'],
                ]);

                $wmsAccount->save();

                if ($exists) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
            }
        });

        return [$createdCount, $updatedCount];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0:int,1:int}
     */
    private function persistImportedConsumables(array $rows): array
    {
        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($rows, &$createdCount, &$updatedCount): void {
            foreach ($rows as $index => $row) {
                $consumableBySku = $row['sku'] !== ''
                    ? Consumable::query()->where('sku', $row['sku'])->first()
                    : null;
                $consumableByBarcode = $row['sku_barcode'] !== ''
                    ? Consumable::query()->where('sku_barcode', $row['sku_barcode'])->first()
                    : null;
                $consumableByName = Consumable::query()->where('name', $row['name'])->first();

                $matchedConsumable = $this->resolveSingleConsumableImportMatch(
                    [$consumableBySku, $consumableByBarcode, $consumableByName],
                    $index
                );

                $consumable = $matchedConsumable ?? new Consumable;
                $exists = $consumable->exists;

                $consumable->fill([
                    'sku' => $row['sku'] !== '' ? $row['sku'] : null,
                    'sku_barcode' => $row['sku_barcode'] !== '' ? $row['sku_barcode'] : null,
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'stock' => (int) $row['stock'],
                    'is_active' => $row['status'] === 'Active',
                ]);

                $consumable->save();

                if ($exists) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
            }
        });

        return [$createdCount, $updatedCount];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0:int,1:int}
     */
    private function persistImportedDailyWorkers(array $rows): array
    {
        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($rows, &$createdCount, &$updatedCount): void {
            foreach ($rows as $index => $row) {
                $workerByCode = DailyWorker::query()->where('employee_code', $row['employee_code'])->first();
                $workerByName = DailyWorker::query()->where('name', $row['name'])->first();

                if ($workerByCode && $workerByName && $workerByCode->id !== $workerByName->id) {
                    throw ValidationException::withMessages([
                        'file' => ['Row '.($index + 2).' conflicts with existing employee code and name records.'],
                    ]);
                }

                $dailyWorker = $workerByCode ?? $workerByName ?? new DailyWorker;

                $exists = $dailyWorker->exists;

                $dailyWorker->fill([
                    'employee_code' => $row['employee_code'],
                    'name' => $row['name'],
                    'function' => $row['function'],
                    'division' => $row['division'],
                    'position' => $row['position'],
                    'status' => $row['status'],
                    'is_active' => $row['status'] === 'Active',
                ]);

                $dailyWorker->save();

                if ($exists) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
            }
        });

        return [$createdCount, $updatedCount];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseImportCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to read the uploaded CSV file.'],
            ]);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }

            $rows[] = array_map(
                static fn ($value): ?string => $value === null ? null : (string) $value,
                $row
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseImportXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'file' => ['Unable to open the uploaded XLSX file.'],
            ]);
        }

        $worksheetPath = $this->firstWorksheetPath($zip);
        $sheetXml = $worksheetPath ? $zip->getFromName($worksheetPath) : false;
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if (! $worksheetPath || $sheetXml === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to read worksheet data from the uploaded XLSX file.'],
            ]);
        }

        $sharedStrings = $this->parseSharedStrings($sharedStringsXml ?: null);
        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement || ! isset($sheet->sheetData)) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded XLSX file has an invalid worksheet format.'],
            ]);
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $row = [];

            foreach ($rowNode->c as $cellNode) {
                $reference = (string) $cellNode['r'];
                $columnIndex = $this->columnReferenceToIndex($reference);
                $row[$columnIndex] = $this->cellNodeValue($cellNode, $sharedStrings);
            }

            if ($row !== []) {
                ksort($row);
                $rows[] = array_values($row);
            }
        }

        return $rows;
    }

    private function firstWorksheetPath(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function parseSharedStrings(?string $xml): array
    {
        if ($xml === null || $xml === '') {
            return [];
        }

        $sharedStringsXml = simplexml_load_string($xml);

        if (! $sharedStringsXml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($sharedStringsXml->si as $stringNode) {
            if (isset($stringNode->t)) {
                $strings[] = (string) $stringNode->t;

                continue;
            }

            $text = '';

            foreach ($stringNode->r as $runNode) {
                $text .= (string) $runNode->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellNodeValue(SimpleXMLElement $cellNode, array $sharedStrings): ?string
    {
        $type = (string) $cellNode['t'];

        if ($type === 's') {
            $sharedStringIndex = (int) ($cellNode->v ?? 0);

            return $sharedStrings[$sharedStringIndex] ?? null;
        }

        if ($type === 'inlineStr') {
            return isset($cellNode->is->t) ? (string) $cellNode->is->t : null;
        }

        return isset($cellNode->v) ? (string) $cellNode->v : null;
    }

    /**
     * @param  array<int, Consumable|null>  $matches
     */
    private function resolveSingleConsumableImportMatch(array $matches, int $rowIndex): ?Consumable
    {
        $matchedConsumables = array_values(array_filter($matches));

        if ($matchedConsumables === []) {
            return null;
        }

        $firstConsumable = $matchedConsumables[0];

        foreach ($matchedConsumables as $consumable) {
            if ($consumable->id !== $firstConsumable->id) {
                throw ValidationException::withMessages([
                    'file' => ['Row '.($rowIndex + 2).' conflicts with existing SKU, SKU barcode, or consumable name records.'],
                ]);
            }
        }

        return $firstConsumable;
    }

    private function columnReferenceToIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $matches);

        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private function xlsxContentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function xlsxRootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function xlsxAppPropertiesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>Microsoft Excel</Application>
</Properties>
XML;
    }

    private function xlsxCorePropertiesXml(): string
    {
        $timestamp = now()->toAtomString();

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>WIMS</dc:creator>
    <cp:lastModifiedBy>WIMS</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private function xlsxWorkbookXml(string $sheetName): string
    {
        return str_replace('__SHEET_NAME__', e($sheetName), <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="__SHEET_NAME__" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
    }

    private function xlsxWorkbookRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML;
    }

    private function xlsxStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1">
        <font>
            <sz val="11"/>
            <name val="Calibri"/>
        </font>
    </fonts>
    <fills count="1">
        <fill>
            <patternFill patternType="none"/>
        </fill>
    </fills>
    <borders count="1">
        <border>
            <left/>
            <right/>
            <top/>
            <bottom/>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
</styleSheet>
XML;
    }

    /**
     * @param  array<int, string>  $strings
     */
    private function xlsxSharedStringsXml(array $strings): string
    {
        $count = count($strings);
        $items = collect($strings)
            ->map(fn (string $value): string => '<si><t>'.e($value).'</t></si>')
            ->implode('');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{$count}" uniqueCount="{$count}">{$items}</sst>
XML;
    }

    private function xlsxWorksheetXml(int $headerCount): string
    {
        $cells = [];

        for ($index = 0; $index < $headerCount; $index++) {
            $column = $this->indexToColumnReference($index);
            $cells[] = '<c r="'.$column.'1" t="s"><v>'.$index.'</v></c>';
        }

        $lastColumn = $this->indexToColumnReference(max($headerCount - 1, 0));
        $cellMarkup = implode('', $cells);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <dimension ref="A1:{$lastColumn}1"/>
    <sheetData>
        <row r="1">{$cellMarkup}</row>
    </sheetData>
</worksheet>
XML;
    }

    private function indexToColumnReference(int $index): string
    {
        $reference = '';
        $number = $index + 1;

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $reference = chr(65 + $remainder).$reference;
            $number = intdiv($number - 1, 26);
        }

        return $reference;
    }
}
