<?php

use App\Models\DailyWorker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('administrator can download the daily worker import template', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    $response = $this->get(route('administration.master.daily-workers.template'));

    $response->assertSuccessful();
    $response->assertDownload('daily-workers-template.xlsx');

    $templateContents = $response->streamedContent();
    $temporaryFile = tempnam(sys_get_temp_dir(), 'daily-workers-template-test-');

    file_put_contents($temporaryFile, $templateContents);

    $zip = new ZipArchive;
    expect($zip->open($temporaryFile))->toBeTrue();

    $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
    $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    @unlink($temporaryFile);

    expect($sharedStrings)
        ->toContain('employee_code')
        ->toContain('name')
        ->toContain('function')
        ->toContain('division')
        ->toContain('position')
        ->toContain('status');

    expect($worksheet)
        ->toContain('<dimension ref="A1:F1"/>')
        ->toContain('<sheetData>');

    expect(strpos($worksheet, '<dimension ref="A1:F1"/>'))->toBeLessThan(strpos($worksheet, '<sheetData>'));
});

test('administrator can import daily workers from xlsx', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));

    DailyWorker::query()->create([
        'employee_code' => 'EMP-001',
        'name' => 'Siti Lama',
        'function' => 'Outbound',
        'division' => 'Packer',
        'position' => 'Packer',
        'status' => 'Active',
        'is_active' => true,
    ]);

    $uploadedFile = UploadedFile::fake()->createWithContent(
        'daily-workers.xlsx',
        buildDailyWorkerImportWorkbook([
            ['EMP-001', 'Siti Baru', 'Inbound', 'Packing', 'Checker', 'Inactive'],
            ['EMP-002', 'Budi', 'Outbound', 'Packing', 'Packer', 'Active'],
        ])
    );

    $response = $this->post(route('administration.master.daily-workers.import'), [
        'file' => $uploadedFile,
    ]);

    $response
        ->assertRedirect(route('administration.master.daily-workers'))
        ->assertSessionHas('success', 'Daily worker import completed. 1 created, 1 updated.');

    $this->assertDatabaseHas('daily_workers', [
        'employee_code' => 'EMP-001',
        'name' => 'Siti Baru',
        'function' => 'Inbound',
        'division' => 'Packing',
        'position' => 'Checker',
        'status' => 'Inactive',
        'is_active' => 0,
    ]);

    $this->assertDatabaseHas('daily_workers', [
        'employee_code' => 'EMP-002',
        'name' => 'Budi',
        'function' => 'Outbound',
        'division' => 'Packing',
        'position' => 'Packer',
        'status' => 'Active',
        'is_active' => 1,
    ]);
});

function buildDailyWorkerImportWorkbook(array $rows): string
{
    $headers = ['employee_code', 'name', 'function', 'division', 'position', 'status'];
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

            $column = indexToColumnReference($columnIndex);
            $cells[] = '<c r="'.$column.($rowIndex + 1).'" t="s"><v>'.$sharedStringLookup[$value].'</v></c>';
        }

        $worksheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
    }

    $sharedStringItems = collect($sharedStrings)
        ->map(fn (string $value): string => '<si><t>'.e($value).'</t></si>')
        ->implode('');

    $lastColumn = indexToColumnReference(count($headers) - 1);
    $lastRow = count($allRows);
    $sheetData = implode('', $worksheetRows);
    $count = count($sharedStrings);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'daily-workers-import-test-');
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
    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Daily Workers" sheetId="1" r:id="rId1"/>
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

function indexToColumnReference(int $index): string
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
