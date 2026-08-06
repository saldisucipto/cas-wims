<?php

use App\Models\Consumable;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('administrator can store consumable with sku and sku barcode', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $response = $this
        ->from(route('administration.master.consumables'))
        ->post(route('administration.master.consumables.store'), [
            'sku' => 'SKU-GLV-001',
            'sku_barcode' => '8990011223344',
            'name' => 'Sarung Tangan',
            'unit' => 'Pcs',
            'stock' => 25,
            'status' => 'Active',
        ]);

    $response
        ->assertRedirect(route('administration.master.consumables'))
        ->assertSessionHas('success', 'Consumable created.');

    $this->assertDatabaseHas('consumables', [
        'sku' => 'SKU-GLV-001',
        'sku_barcode' => '8990011223344',
        'name' => 'Sarung Tangan',
        'unit' => 'Pcs',
        'stock' => 25,
        'is_active' => 1,
    ]);
});

test('receiving page can post multi item stock transaction', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $firstConsumable = Consumable::query()->create([
        'sku' => 'SKU-TAPE-01',
        'sku_barcode' => '1234567890123',
        'name' => 'Lakban',
        'unit' => 'Roll',
        'stock' => 10,
        'is_active' => true,
    ]);

    $secondConsumable = Consumable::query()->create([
        'sku' => 'SKU-LABEL-01',
        'sku_barcode' => '9988776655443',
        'name' => 'Sticker Label',
        'unit' => 'Pcs',
        'stock' => 20,
        'is_active' => true,
    ]);

    $page = $this->get(route('administration.inventory.receiving'));

    $page
        ->assertSuccessful()
        ->assertSee('Scan SKU Barcode')
        ->assertSee('Nomor Purchase Request')
        ->assertSee('Diterima Oleh');

    $response = $this
        ->from(route('administration.inventory.receiving'))
        ->post(route('administration.inventory.receiving.store'), [
            'transaction_date' => '2026-08-06',
            'purchase_request_number' => 'PR-20260806-001',
            'received_by_name' => 'Budi',
            'notes' => 'Scanner receiving',
            'items' => [
                [
                    'sku_barcode' => '1234567890123',
                    'quantity' => 5,
                ],
                [
                    'consumable_id' => $secondConsumable->id,
                    'quantity' => 3,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('administration.inventory.receiving'))
        ->assertSessionHas('success', 'Receiving transaction posted.');

    $this->assertDatabaseHas('consumables', [
        'id' => $firstConsumable->id,
        'stock' => 15,
    ]);

    $this->assertDatabaseHas('consumables', [
        'id' => $secondConsumable->id,
        'stock' => 23,
    ]);

    $this->assertDatabaseHas('stock_transactions', [
        'consumable_id' => $firstConsumable->id,
        'transaction_type' => 'Receiving',
        'purchase_request_number' => 'PR-20260806-001',
        'received_by_name' => 'Budi',
        'quantity_before' => 10,
        'quantity_change' => 5,
        'quantity_after' => 15,
        'notes' => 'Scanner receiving',
    ]);

    $this->assertDatabaseHas('stock_transactions', [
        'consumable_id' => $secondConsumable->id,
        'transaction_type' => 'Receiving',
        'purchase_request_number' => 'PR-20260806-001',
        'received_by_name' => 'Budi',
        'quantity_before' => 20,
        'quantity_change' => 3,
        'quantity_after' => 23,
        'notes' => 'Scanner receiving',
    ]);

    expect(StockTransaction::query()->where('purchase_request_number', 'PR-20260806-001')->count())->toBe(2);
    expect(StockTransaction::query()->where('purchase_request_number', 'PR-20260806-001')->distinct('transaction_group')->count('transaction_group'))->toBe(1);
});

test('administrator can download the consumable import template', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $response = $this->get(route('administration.master.consumables.template'));

    $response->assertSuccessful();
    $response->assertDownload('consumables-template.xlsx');

    $templateContents = $response->streamedContent();
    $temporaryFile = tempnam(sys_get_temp_dir(), 'consumable-template-test-');

    file_put_contents($temporaryFile, $templateContents);

    $zip = new ZipArchive;
    expect($zip->open($temporaryFile))->toBeTrue();

    $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
    $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $workbook = $zip->getFromName('xl/workbook.xml');
    $zip->close();

    @unlink($temporaryFile);

    expect($sharedStrings)
        ->toContain('sku')
        ->toContain('sku_barcode')
        ->toContain('name')
        ->toContain('unit')
        ->toContain('stock')
        ->toContain('status');

    expect($worksheet)
        ->toContain('<dimension ref="A1:F1"/>')
        ->toContain('<sheetData>');

    expect(strpos($worksheet, '<dimension ref="A1:F1"/>'))->toBeLessThan(strpos($worksheet, '<sheetData>'));
    expect($workbook)->toContain('sheet name="Consumables"');
});

test('administrator can import consumables from xlsx', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    Consumable::query()->create([
        'sku' => 'SKU-TAPE-01',
        'sku_barcode' => '1234567890123',
        'name' => 'Lakban Lama',
        'unit' => 'Roll',
        'stock' => 10,
        'is_active' => true,
    ]);

    $uploadedFile = UploadedFile::fake()->createWithContent(
        'consumables.xlsx',
        buildConsumableImportWorkbook([
            ['SKU-TAPE-01', '1234567890123', 'Lakban Baru', 'Box', '20', 'Inactive'],
            ['SKU-LABEL-01', '9988776655443', 'Sticker Label', 'Pcs', '45', 'Active'],
        ])
    );

    $response = $this->post(route('administration.master.consumables.import'), [
        'file' => $uploadedFile,
    ]);

    $response
        ->assertRedirect(route('administration.master.consumables'))
        ->assertSessionHas('success', 'Consumable import completed. 1 created, 1 updated.');

    $this->assertDatabaseHas('consumables', [
        'sku' => 'SKU-TAPE-01',
        'sku_barcode' => '1234567890123',
        'name' => 'Lakban Baru',
        'unit' => 'Box',
        'stock' => 20,
        'is_active' => 0,
    ]);

    $this->assertDatabaseHas('consumables', [
        'sku' => 'SKU-LABEL-01',
        'sku_barcode' => '9988776655443',
        'name' => 'Sticker Label',
        'unit' => 'Pcs',
        'stock' => 45,
        'is_active' => 1,
    ]);
});

function buildConsumableImportWorkbook(array $rows): string
{
    $headers = ['sku', 'sku_barcode', 'name', 'unit', 'stock', 'status'];

    return buildSpreadsheetWorkbook('Consumables', $headers, $rows);
}

function buildSpreadsheetWorkbook(string $sheetName, array $headers, array $rows): string
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

            $column = indexToSpreadsheetColumnReference($columnIndex);
            $cells[] = '<c r="'.$column.($rowIndex + 1).'" t="s"><v>'.$sharedStringLookup[$value].'</v></c>';
        }

        $worksheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
    }

    $sharedStringItems = collect($sharedStrings)
        ->map(fn (string $value): string => '<si><t>'.e($value).'</t></si>')
        ->implode('');

    $lastColumn = indexToSpreadsheetColumnReference(count($headers) - 1);
    $lastRow = count($allRows);
    $sheetData = implode('', $worksheetRows);
    $count = count($sharedStrings);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'spreadsheet-import-test-');
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
        <sheet name="{$sheetName}" sheetId="1" r:id="rId1"/>
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

function indexToSpreadsheetColumnReference(int $index): string
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
