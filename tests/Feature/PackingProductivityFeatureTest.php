<?php

use App\Models\DailyWorker;
use App\Models\MesonImportBatch;
use App\Models\MesonTransaction;
use App\Models\User;
use App\Models\WmsAccount;
use App\Models\WorkingCalendar;
use App\Models\WorkingSession;
use App\Services\PackingProductivity\PackingProductivityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function mesonProductivityHeaders(): array
{
    return [
        'Warehouse ID', 'Transaction ID', 'Transaction Type', 'Document number', 'Transaction Time',
        'SKU FM', 'QTY Each FM', 'Operator',
    ];
}

function seedPackingOperator(string $username, string $function = 'Outbound'): WmsAccount
{
    return WmsAccount::query()->create([
        'username' => $username,
        'password' => 'x',
        'function' => $function,
        'status' => 'Available',
    ]);
}

function buildMesonProductivityWorkbook(array $rows): string
{
    $headers = mesonProductivityHeaders();
    $allRows = [$headers, ...$rows];
    $sharedStrings = [];
    $lookup = [];
    $worksheetRows = [];

    foreach ($allRows as $rowIndex => $row) {
        $cells = [];

        foreach ($row as $columnIndex => $value) {
            if (! array_key_exists($value, $lookup)) {
                $lookup[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            $column = mesonColumnRef($columnIndex);
            $cells[] = '<c r="'.$column.($rowIndex + 1).'" t="s"><v>'.$lookup[$value].'</v></c>';
        }

        $worksheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
    }

    $sharedStringItems = collect($sharedStrings)->map(fn (string $v) => '<si><t>'.e($v).'</t></si>')->implode('');
    $lastColumn = mesonColumnRef(count($headers) - 1);
    $lastRow = count($allRows);
    $count = count($sharedStrings);
    $path = tempnam(sys_get_temp_dir(), 'meson-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">'.$sharedStringItems.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $worksheetRows).'</sheetData></worksheet>');
    $zip->close();

    $contents = file_get_contents($path);
    @unlink($path);

    return $contents === false ? '' : $contents;
}

function mesonColumnRef(int $index): string
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

function mesonRow(string $warehouse, string $transactionId, string $type, string $doc, string $time, string $sku, string $qtyEach, string $operator): array
{
    return [$warehouse, $transactionId, $type, $doc, $time, $sku, $qtyEach, $operator];
}

test('packing productivity page requires administrator', function () {
    $this->get(route('administration.packing-productivity'))
        ->assertRedirect(route('administration.login'));
});

test('import replaces only the selected period and calculates productivity', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    $op1 = seedPackingOperator('CA1OPS008');
    $op2 = seedPackingOperator('CA1OPS009');

    // Existing data: an old row in the target period (to be replaced) and a row in another period (to keep).
    MesonTransaction::query()->create([
        'transaction_id' => 'OLD-1', 'transaction_type' => 'Picking&Packing', 'document_number' => 'DOC-OLD',
        'transaction_time' => '2026-08-15 10:00:00', 'qty_each_fm' => 99, 'operator_id' => $op1->id, 'operator_username' => 'CA1OPS008',
    ]);
    MesonTransaction::query()->create([
        'transaction_id' => 'KEEP-1', 'transaction_type' => 'Picking&Packing', 'document_number' => 'DOC-KEEP',
        'transaction_time' => '2026-08-16 10:00:00', 'qty_each_fm' => 1, 'operator_id' => $op1->id, 'operator_username' => 'CA1OPS008',
    ]);

    $file = UploadedFile::fake()->createWithContent('meson.xlsx', buildMesonProductivityWorkbook([
        mesonRow('WH1', 'T1', 'Picking&Packing', 'DOC-A', '2026-08-15 08:30:00', 'SKU1', '1', 'CA1OPS008'),
        mesonRow('WH1', 'T2', 'Picking&Packing', 'DOC-A', '2026-08-15 09:30:00', 'SKU2', '2', 'CA1OPS008'),
        mesonRow('WH1', 'T3', 'Picking&Packing', 'DOC-B', '2026-08-15 10:30:00', 'SKU3', '5', 'CA1OPS009'),
        mesonRow('WH1', 'T4', 'Picking&Packing', 'DOC-A', '2026-08-16 09:00:00', 'SKU4', '1', 'CA1OPS008'),
    ]));

    $this->post(route('administration.packing-productivity.upload'), [
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
        'file' => $file,
    ])->assertRedirect(route('administration.packing-productivity.preview'));

    $this->get(route('administration.packing-productivity.preview'))->assertOk();

    $this->post(route('administration.packing-productivity.confirm'))
        ->assertRedirect(route('administration.packing-productivity'));

    $this->assertDatabaseHas('meson_transactions', ['transaction_id' => 'T1', 'operator_id' => $op1->id]);
    $this->assertDatabaseHas('meson_transactions', ['transaction_id' => 'T3', 'operator_id' => $op2->id]);
    $this->assertDatabaseMissing('meson_transactions', ['transaction_id' => 'OLD-1']);
    $this->assertDatabaseHas('meson_transactions', ['transaction_id' => 'KEEP-1']);
    $this->assertDatabaseMissing('meson_transactions', ['transaction_id' => 'T4']);

    $data = app(PackingProductivityService::class)->dashboard(['start_date' => '2026-08-15', 'end_date' => '2026-08-15']);

    expect($data['summary']['total_orders'])->toBe(2)
        ->and($data['summary']['total_lines'])->toBe(3)
        ->and($data['summary']['total_items'])->toBe(8.0)
        ->and($data['summary']['total_operators'])->toBe(2)
        ->and($data['per_worker'])->toHaveCount(2);
});

test('invalid operator is flagged and listed in preview', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    seedPackingOperator('CA1OPS008');

    $file = UploadedFile::fake()->createWithContent('meson.xlsx', buildMesonProductivityWorkbook([
        mesonRow('WH1', 'T1', 'Picking&Packing', 'DOC-A', '2026-08-15 08:30:00', 'SKU1', '1', 'CA1OPS008'),
        mesonRow('WH1', 'T2', 'Picking&Packing', 'DOC-B', '2026-08-15 09:30:00', 'SKU2', '2', 'CA1OPS099'),
    ]));

    $this->post(route('administration.packing-productivity.upload'), [
        'start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'file' => $file,
    ]);

    $this->get(route('administration.packing-productivity.preview'))
        ->assertOk()
        ->assertSee('CA1OPS099');

    $this->post(route('administration.packing-productivity.confirm'));

    $this->assertDatabaseHas('meson_transactions', ['transaction_id' => 'T2', 'operator_id' => null, 'operator_username' => 'CA1OPS099']);
});

test('duplicate transaction id is skipped and counted', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    seedPackingOperator('CA1OPS008');

    $file = UploadedFile::fake()->createWithContent('meson.xlsx', buildMesonProductivityWorkbook([
        mesonRow('WH1', 'T1', 'Picking&Packing', 'DOC-A', '2026-08-15 08:30:00', 'SKU1', '1', 'CA1OPS008'),
        mesonRow('WH1', 'T1', 'Picking&Packing', 'DOC-A', '2026-08-15 08:31:00', 'SKU2', '1', 'CA1OPS008'),
    ]));

    $this->post(route('administration.packing-productivity.upload'), [
        'start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'file' => $file,
    ])->assertRedirect();

    $this->post(route('administration.packing-productivity.confirm'));

    expect(MesonTransaction::query()->where('transaction_id', 'T1')->count())->toBe(1);

    $batch = MesonImportBatch::query()->first();
    expect($batch->duplicate_rows)->toBe(1)
        ->and($batch->imported_rows)->toBe(1);
});

test('missing required columns stops the import', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    // Headers without 'Document number'.
    $headers = ['Warehouse ID', 'Transaction ID', 'Transaction Time', 'Operator'];
    $rows = [['WH1', 'T1', '2026-08-15 08:30:00', 'CA1OPS008']];
    $content = buildMesonProductivityWorkbookWithHeaders($headers, $rows);
    $file = UploadedFile::fake()->createWithContent('meson.xlsx', $content);

    $this->post(route('administration.packing-productivity.upload'), [
        'start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'file' => $file,
    ])->assertRedirect(route('administration.packing-productivity.preview'));

    $this->get(route('administration.packing-productivity.preview'))
        ->assertRedirect(route('administration.packing-productivity.import'))
        ->assertSessionHas('error');
});

test('scheduled hours excludes sunday and uses configurable hours per day', function () {
    $service = app(PackingProductivityService::class);

    // Monday 2026-08-17 to Saturday 2026-08-22 => 6 working days * 7h = 42h.
    expect($service->scheduledHours(Carbon::parse('2026-08-17'), Carbon::parse('2026-08-22')))->toBe(42.0);
});

test('import resolves the daily worker from the working session', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $worker = DailyWorker::query()->create([
        'employee_code' => 'DW0001',
        'name' => 'Andi Pratama',
        'function' => 'Outbound',
        'division' => 'Packer',
        'position' => 'Packer',
        'status' => 'Active',
        'is_active' => true,
    ]);

    $op = seedPackingOperator('CA1OPS008');

    WorkingSession::query()->create([
        'daily_worker_id' => $worker->id,
        'wms_account_id' => $op->id,
        'session_type' => 'Packing',
        'status' => 'Completed',
        'started_at' => '2026-08-15 08:00:00',
        'ended_at' => '2026-08-15 16:00:00',
    ]);

    $file = UploadedFile::fake()->createWithContent('meson.xlsx', buildMesonProductivityWorkbook([
        mesonRow('WH1', 'T1', 'Picking&Packing', 'DOC-A', '2026-08-15 08:30:00', 'SKU1', '3', 'CA1OPS008'),
    ]));

    $this->post(route('administration.packing-productivity.upload'), [
        'start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'file' => $file,
    ])->assertRedirect();
    $this->post(route('administration.packing-productivity.confirm'));

    $this->assertDatabaseHas('meson_transactions', ['transaction_id' => 'T1', 'daily_worker_id' => $worker->id]);

    $data = app(PackingProductivityService::class)->dashboard(['start_date' => '2026-08-15', 'end_date' => '2026-08-15']);
    expect($data['per_worker'][0]['name'])->toBe('Andi Pratama');

    $this->get(route('administration.packing-productivity'))
        ->assertOk()
        ->assertSee('Andi Pratama');
});

test('import refuses to wipe a period when no row matches the transaction type filter', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    seedPackingOperator('CA1OPS008');

    MesonTransaction::query()->create([
        'transaction_id' => 'EXISTING-1',
        'transaction_type' => 'Picking&Packing',
        'document_number' => 'DOC-EXISTING',
        'transaction_time' => '2026-08-15 09:00:00',
        'qty_each_fm' => 42,
        'operator_id' => 1,
        'operator_username' => 'CA1OPS008',
    ]);

    $file = UploadedFile::fake()->createWithContent('meson.xlsx', buildMesonProductivityWorkbook([
        mesonRow('WH1', 'T1', 'Receiving', 'DOC-A', '2026-08-15 08:30:00', 'SKU1', '1', 'CA1OPS008'),
    ]));

    $this->post(route('administration.packing-productivity.upload'), [
        'start_date' => '2026-08-15',
        'end_date' => '2026-08-15',
        'file' => $file,
    ])->assertRedirect();

    $this->post(route('administration.packing-productivity.confirm'))
        ->assertRedirect(route('administration.packing-productivity.import'))
        ->assertSessionHas('error');

    // The existing period data must survive a failed import.
    $this->assertDatabaseHas('meson_transactions', ['transaction_id' => 'EXISTING-1', 'qty_each_fm' => 42]);
});

test('transaction types fall back to both packing variants', function () {
    expect(app(PackingProductivityService::class)->transactionTypes())
        ->toContain('Packing')
        ->toContain('Picking&Packing');
});

test('admin dashboard can be filtered by productivity role', function () {
    seedPackingOperator('CA1OPS008');

    foreach ([
        ['T1', 'Picking&Packing', 'DOC-A', 10],
        ['T2', 'Packing', 'DOC-B', 100],
    ] as [$id, $type, $doc, $qty]) {
        MesonTransaction::query()->create([
            'transaction_id' => $id,
            'transaction_type' => $type,
            'document_number' => $doc,
            'transaction_time' => '2026-08-15 09:00:00',
            'qty_each_fm' => $qty,
            'operator_id' => 1,
            'operator_username' => 'CA1OPS008',
        ]);
    }

    $service = app(PackingProductivityService::class);
    $filters = ['start_date' => '2026-08-15', 'end_date' => '2026-08-15'];

    expect($service->dashboard($filters)['summary']['total_items'])->toBe(110.0)
        ->and($service->dashboard($filters + ['role' => 'picker'])['summary']['total_items'])->toBe(10.0)
        ->and($service->dashboard($filters + ['role' => 'packer'])['summary']['total_items'])->toBe(100.0);
});

test('scheduled hours follow the working calendar instead of a flat seven hours', function () {
    foreach ([
        [1, 'Monday', true, 8], [2, 'Tuesday', true, 8], [3, 'Wednesday', true, 8],
        [4, 'Thursday', true, 8], [5, 'Friday', true, 8], [6, 'Saturday', true, 5],
        [7, 'Sunday', false, 0],
    ] as [$day, $name, $working, $hours]) {
        WorkingCalendar::query()->create([
            'day_of_week' => $day,
            'day_name' => $name,
            'is_working_day' => $working,
            'working_hours' => $hours,
        ]);
    }

    // August 2026: 21 weekdays * 8h + 5 Saturdays * 5h + 5 Sundays * 0h = 193h.
    expect(app(PackingProductivityService::class)
        ->scheduledHours(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')))
        ->toBe(193.0);
});

test('per worker rate is derived from the worker own active hours', function () {
    seedPackingOperator('CA1OPS008');

    foreach ([
        ['T1', 'DOC-A', '2026-08-15 08:00:00', 5],
        ['T2', 'DOC-B', '2026-08-15 08:06:00', 5],
    ] as [$id, $doc, $time, $qty]) {
        MesonTransaction::query()->create([
            'transaction_id' => $id,
            'transaction_type' => 'Picking&Packing',
            'document_number' => $doc,
            'transaction_time' => $time,
            'qty_each_fm' => $qty,
            'operator_id' => 1,
            'operator_username' => 'CA1OPS008',
        ]);
    }

    $data = app(PackingProductivityService::class)
        ->dashboard(['start_date' => '2026-08-15', 'end_date' => '2026-08-15']);

    $worker = $data['per_worker'][0];

    // 6 minutes of active time for 10 items => 100 items/hour, not 10 / 7h.
    expect($worker['estimated_active_hours'])->toBe(0.1)
        ->and($worker['items_per_hour'])->toBe(100.0)
        ->and($data['summary']['labor_hours'])->toBe(0.1);
});

function buildMesonProductivityWorkbookWithHeaders(array $headers, array $rows): string
{
    $allRows = [$headers, ...$rows];
    $sharedStrings = [];
    $lookup = [];
    $worksheetRows = [];

    foreach ($allRows as $rowIndex => $row) {
        $cells = [];

        foreach ($row as $columnIndex => $value) {
            if (! array_key_exists($value, $lookup)) {
                $lookup[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            $column = mesonColumnRef($columnIndex);
            $cells[] = '<c r="'.$column.($rowIndex + 1).'" t="s"><v>'.$lookup[$value].'</v></c>';
        }

        $worksheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
    }

    $sharedStringItems = collect($sharedStrings)->map(fn (string $v) => '<si><t>'.e($v).'</t></si>')->implode('');
    $count = count($sharedStrings);
    $path = tempnam(sys_get_temp_dir(), 'meson-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">'.$sharedStringItems.'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $worksheetRows).'</sheetData></worksheet>');
    $zip->close();

    $contents = file_get_contents($path);
    @unlink($path);

    return $contents === false ? '' : $contents;
}
