<?php

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('stock card without a specific atk shows summary of all items', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    AtkItem::query()->create([
        'code' => 'ATK-300',
        'name' => 'Pulpen Summary',
        'category' => 'Writing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 30,
        'status' => 'Active',
        'notes' => null,
    ]);

    $this->actingAs($administrator)
        ->get(route('administration.reports.atk-stock-card'))
        ->assertSuccessful()
        ->assertSee('Kartu Stok ATK')
        ->assertSee('Kode ATK')
        ->assertSee('Nama ATK')
        ->assertSee('Total Masuk')
        ->assertSee('Total Keluar')
        ->assertSee('Saldo Akhir')
        ->assertSee('Pulpen Summary')
        ->assertSee('30');
});

test('stock card with a specific atk keeps the detailed transaction format', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-301',
        'name' => 'Spidol Detail',
        'category' => 'Writing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 10,
        'status' => 'Active',
        'notes' => null,
    ]);

    $this->actingAs($administrator)
        ->get(route('administration.reports.atk-stock-card', ['atk_item_id' => $atkItem->id]))
        ->assertSuccessful()
        ->assertSee('Tanggal')
        ->assertSee('Nomor Transaksi')
        ->assertSee('Jenis Transaksi')
        ->assertSee('Saldo')
        ->assertSee('User')
        ->assertSee('Spidol Detail');
});

test('stock card print works without a specific atk and shows the summary', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    AtkItem::query()->create([
        'code' => 'ATK-400',
        'name' => 'Pulpen Print Summary',
        'category' => 'Writing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 30,
        'status' => 'Active',
        'notes' => null,
    ]);

    $this->actingAs($administrator)
        ->get(route('administration.reports.atk-stock-card.print'))
        ->assertSuccessful()
        ->assertSee('Rekap Kartu Stok ATK')
        ->assertSee('Total Masuk')
        ->assertSee('Pulpen Print Summary');
});

test('administrator can view atk administration pages', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);

    $this->actingAs($administrator)
        ->get(route('administration.dashboard'))
        ->assertSuccessful()
        ->assertSee('Pending ATK Approvals');

    $this->actingAs($administrator)
        ->get(route('administration.master.atk'))
        ->assertSuccessful()
        ->assertSee('Master ATK');

    $this->actingAs($administrator)
        ->get(route('administration.inventory.atk-receiving'))
        ->assertSuccessful()
        ->assertSee('Penerimaan ATK');

    $this->actingAs($administrator)
        ->get(route('administration.reports.atk-stock-card'))
        ->assertSuccessful()
        ->assertSee('Kartu Stok ATK');

    $this->actingAs($administrator)
        ->get(route('atk.take'))
        ->assertSuccessful()
        ->assertSee('Pengambilan ATK');
});

test('leader can view atk request page', function () {
    $leader = User::factory()->create(['role' => 'Leader']);

    $this->actingAs($leader)
        ->get(route('atk.requests'))
        ->assertSuccessful()
        ->assertSee('Permintaan ATK')
        ->assertSee('Request History');
});

test('leader can submit atk request', function () {
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-100',
        'name' => 'Pulpen Test',
        'category' => 'Writing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 30,
        'status' => 'Active',
        'notes' => 'Test item',
    ]);

    $response = $this
        ->actingAs($leader)
        ->from(route('atk.requests'))
        ->post(route('atk.requests.store'), [
            'notes' => 'Need supplies for coordination meeting.',
            'items' => [
                [
                    'atk_item_id' => $atkItem->id,
                    'quantity' => 4,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('atk.requests'))
        ->assertSessionHas('success', 'ATK request submitted.');

    $request = AtkRequest::query()->first();

    expect($request)->not->toBeNull();

    $this->assertDatabaseHas('atk_requests', [
        'id' => $request->id,
        'requested_by' => $leader->id,
        'status' => 'Pending',
        'notes' => 'Need supplies for coordination meeting.',
    ]);

    $this->assertDatabaseHas('atk_request_items', [
        'atk_request_id' => $request->id,
        'atk_item_id' => $atkItem->id,
        'quantity' => 4,
    ]);
});

test('guest cannot access atk request page', function () {
    $this->get(route('atk.requests'))
        ->assertRedirect(route('administration.login'));
});

test('welcome page shows the permintaan atk menu card', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Permintaan ATK')
        ->assertSee(route('atk.take'));
});

test('guest can access atk take page without an account', function () {
    $this->get(route('atk.take'))
        ->assertSuccessful()
        ->assertSee('Permintaan ATK')
        ->assertSee('Nama Pengambil');
});

test('guest can take atk directly and transaction records the entered name', function () {
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-500',
        'name' => 'Guest Pulpen Test',
        'category' => 'Writing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 25,
        'status' => 'Active',
        'notes' => null,
    ]);

    $response = $this
        ->from(route('atk.take'))
        ->post(route('atk.take.store'), [
            'taken_by' => 'Tamu Anonim',
            'transaction_date' => '2026-08-12',
            'notes' => 'Ambil tanpa akun.',
            'items' => [
                [
                    'atk_item_id' => $atkItem->id,
                    'quantity' => 3,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('atk.take'))
        ->assertSessionHas('success', 'ATK direct take posted.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 22,
    ]);

    $this->assertDatabaseHas('atk_stock_transactions', [
        'atk_item_id' => $atkItem->id,
        'transaction_type' => 'Direct Take',
        'quantity_in' => 0,
        'quantity_out' => 3,
        'balance' => 22,
        'performed_by' => null,
        'taken_by_name' => 'Tamu Anonim',
    ]);
});

test('administrator approval reduces atk stock and records stock card history', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-101',
        'name' => 'Kertas A4 Test',
        'category' => 'Paper',
        'unit' => 'Rim',
        'minimum_stock' => 5,
        'current_stock' => 20,
        'status' => 'Active',
        'notes' => null,
    ]);

    $atkRequest = AtkRequest::query()->create([
        'request_number' => 'ATK-REQ-777',
        'requested_by' => $leader->id,
        'notes' => 'Need paper for documents.',
        'status' => 'Pending',
        'requested_at' => now(),
    ]);

    $atkRequest->items()->create([
        'atk_item_id' => $atkItem->id,
        'quantity' => 4,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->from(route('administration.dashboard'))
        ->post(route('administration.atk-requests.approve', $atkRequest));

    $response
        ->assertRedirect(route('administration.dashboard'))
        ->assertSessionHas('success', 'ATK request approved.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 16,
    ]);

    $this->assertDatabaseHas('atk_requests', [
        'id' => $atkRequest->id,
        'status' => 'Approved',
        'approved_by' => $administrator->id,
    ]);

    $this->assertDatabaseHas('atk_stock_transactions', [
        'atk_item_id' => $atkItem->id,
        'transaction_type' => 'Approval',
        'reference' => 'ATK-REQ-777',
        'quantity_in' => 0,
        'quantity_out' => 4,
        'balance' => 16,
        'performed_by' => $administrator->id,
    ]);
});

test('administrator rejection keeps stock unchanged and saves rejection notes', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-102',
        'name' => 'Sticky Notes Test',
        'category' => 'Paper',
        'unit' => 'Pack',
        'minimum_stock' => 5,
        'current_stock' => 12,
        'status' => 'Active',
        'notes' => null,
    ]);

    $atkRequest = AtkRequest::query()->create([
        'request_number' => 'ATK-REQ-778',
        'requested_by' => $leader->id,
        'notes' => 'Need extra sticky notes.',
        'status' => 'Pending',
        'requested_at' => now(),
    ]);

    $atkRequest->items()->create([
        'atk_item_id' => $atkItem->id,
        'quantity' => 2,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->from(route('administration.dashboard'))
        ->post(route('administration.atk-requests.reject', $atkRequest), [
            'rejection_notes' => 'Use remaining stock first.',
        ]);

    $response
        ->assertRedirect(route('administration.dashboard'))
        ->assertSessionHas('success', 'ATK request rejected.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 12,
    ]);

    $this->assertDatabaseHas('atk_requests', [
        'id' => $atkRequest->id,
        'status' => 'Rejected',
        'rejected_by' => $administrator->id,
        'rejection_notes' => 'Use remaining stock first.',
    ]);

    $this->assertDatabaseMissing('atk_stock_transactions', [
        'reference' => 'ATK-REQ-778',
    ]);
});

test('leader can take atk directly and transaction records performer', function () {
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-200',
        'name' => 'Map Folder Test',
        'category' => 'Filing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 25,
        'status' => 'Active',
        'notes' => null,
    ]);

    $response = $this
        ->actingAs($leader)
        ->from(route('atk.take'))
        ->post(route('atk.take.store'), [
            'taken_by' => $leader->name,
            'transaction_date' => '2026-08-11',
            'notes' => 'Dipakai untuk dokumen outgoing.',
            'items' => [
                [
                    'atk_item_id' => $atkItem->id,
                    'quantity' => 4,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('atk.take'))
        ->assertSessionHas('success', 'ATK direct take posted.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 21,
    ]);

    $this->assertDatabaseHas('atk_stock_transactions', [
        'atk_item_id' => $atkItem->id,
        'transaction_type' => 'Direct Take',
        'reference' => 'Direct ATK Take',
        'quantity_in' => 0,
        'quantity_out' => 4,
        'balance' => 21,
        'performed_by' => $leader->id,
        'taken_by_name' => $leader->name,
        'notes' => 'Dipakai untuk dokumen outgoing.',
    ]);
});

test('atk master page shows the import template download and upload form', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);

    $this->actingAs($administrator)
        ->get(route('administration.master.atk'))
        ->assertSuccessful()
        ->assertSee('Import Data ATK')
        ->assertSee(route('administration.master.atk.template'))
        ->assertSee(route('administration.master.atk.import'));
});

test('administrator can download the atk import template with headers and example rows', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $response = $this->get(route('administration.master.atk.template'));

    $response->assertSuccessful();
    $response->assertDownload('atk-template.xlsx');

    $templateContents = $response->streamedContent();
    $temporaryFile = tempnam(sys_get_temp_dir(), 'atk-template-test-');

    file_put_contents($temporaryFile, $templateContents);

    $zip = new ZipArchive;
    expect($zip->open($temporaryFile))->toBeTrue();

    $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
    $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $workbook = $zip->getFromName('xl/workbook.xml');
    $zip->close();

    @unlink($temporaryFile);

    expect($sharedStrings)
        ->toContain('code')
        ->toContain('name')
        ->toContain('category')
        ->toContain('unit')
        ->toContain('minimum_stock')
        ->toContain('current_stock')
        ->toContain('status')
        ->toContain('notes')
        ->toContain('ATK-CONTOH-1')
        ->toContain('Pulpen Contoh');

    expect($worksheet)
        ->toContain('<dimension ref="A1:H3"/>')
        ->toContain('<sheetData>');

    expect($workbook)->toContain('sheet name="ATK"');
});

test('administrator can import atk items from xlsx', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    AtkItem::query()->create([
        'code' => 'ATK-IMP-0',
        'name' => 'Map Existing',
        'category' => 'Filing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 20,
        'status' => 'Active',
        'notes' => null,
    ]);

    $uploadedFile = UploadedFile::fake()->createWithContent(
        'atk-items.xlsx',
        buildAtkImportWorkbook([
            ['ATK-IMP-0', 'Map Existing', 'Filing', 'Pcs', '5', '25', 'Active', ''],
            ['ATK-IMP-1', 'Pulpen Import', 'Alat Tulis', 'Pcs', '5', '50', 'Active', 'Catatan'],
            ['ATK-IMP-2', 'Kertas Import', 'Kertas', 'Rim', '3', '30', 'Inactive', ''],
        ])
    );

    $response = $this->post(route('administration.master.atk.import'), [
        'file' => $uploadedFile,
    ]);

    $response
        ->assertRedirect(route('administration.master.atk'))
        ->assertSessionHas('success', 'ATK import completed. 2 created, 1 updated.');

    $this->assertDatabaseHas('atk_items', [
        'code' => 'ATK-IMP-0',
        'current_stock' => 25,
    ]);

    $this->assertDatabaseHas('atk_items', [
        'code' => 'ATK-IMP-1',
        'name' => 'Pulpen Import',
        'category' => 'Alat Tulis',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 50,
        'status' => 'Active',
        'notes' => 'Catatan',
    ]);

    $this->assertDatabaseHas('atk_items', [
        'code' => 'ATK-IMP-2',
        'name' => 'Kertas Import',
        'category' => 'Kertas',
        'unit' => 'Rim',
        'minimum_stock' => 3,
        'current_stock' => 30,
        'status' => 'Inactive',
    ]);
});

test('administrator cannot import atk items with duplicate names', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $uploadedFile = UploadedFile::fake()->createWithContent(
        'atk-items-duplicate.xlsx',
        buildAtkImportWorkbook([
            ['ATK-DUP-1', 'Nama Sama', 'Alat Tulis', 'Pcs', '5', '10', 'Active', ''],
            ['ATK-DUP-2', 'Nama Sama', 'Alat Tulis', 'Pcs', '5', '10', 'Active', ''],
        ])
    );

    $response = $this->from(route('administration.master.atk'))
        ->post(route('administration.master.atk.import'), [
            'file' => $uploadedFile,
        ]);

    $response->assertSessionHasErrors('rows');

    expect(session('errors')->first('rows'))->toContain('Nama ATK "Nama Sama" muncul di baris data ke-1 dan ke-2');

    $this->assertDatabaseMissing('atk_items', ['code' => 'ATK-DUP-1']);
    $this->assertDatabaseMissing('atk_items', ['code' => 'ATK-DUP-2']);
});

function buildAtkImportWorkbook(array $rows): string
{
    $headers = ['code', 'name', 'category', 'unit', 'minimum_stock', 'current_stock', 'status', 'notes'];

    return buildAtkSpreadsheetWorkbook($headers, $rows);
}

function buildAtkSpreadsheetWorkbook(array $headers, array $rows): string
{
    $allRows = [$headers, ...$rows];
    $sharedStrings = [];
    $sharedStringLookup = [];
    $worksheetRows = [];

    foreach ($allRows as $rowIndex => $row) {
        $cells = [];

        foreach ($row as $columnIndex => $value) {
            if (! array_key_exists($value, $sharedStringLookup)) {
                $sharedStringLookup[$value] = count($sharedStrings);
                $sharedStrings[] = $value;
            }

            $column = atkColumnReference($columnIndex);
            $cells[] = '<c r="'.$column.($rowIndex + 1).'" t="s"><v>'.$sharedStringLookup[$value].'</v></c>';
        }

        $worksheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
    }

    $sharedStringItems = collect($sharedStrings)
        ->map(fn (string $value): string => '<si><t>'.e($value).'</t></si>')
        ->implode('');

    $lastColumn = atkColumnReference(count($headers) - 1);
    $lastRow = count($allRows);
    $sheetData = implode('', $worksheetRows);
    $count = count($sharedStrings);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'atk-import-test-');
    $zip = new ZipArchive;
    $zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/workbook.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="ATK" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
    <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
    <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
    <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML);
    $zip->addFromString('xl/sharedStrings.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{$count}" uniqueCount="{$count}">{$sharedStringItems}</sst>
XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <dimension ref="A1:{$lastColumn}{$lastRow}"/>
    <sheetData>{$sheetData}</sheetData>
</worksheet>
XML);
    $zip->close();

    $contents = file_get_contents($temporaryFile);
    @unlink($temporaryFile);

    return $contents === false ? '' : $contents;
}

function atkColumnReference(int $index): string
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
