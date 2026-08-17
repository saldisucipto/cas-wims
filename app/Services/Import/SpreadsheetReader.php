<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class SpreadsheetReader
{
    /**
     * Parse an uploaded XLSX/CSV file into raw rows (including the header row).
     *
     * @return array<int, array<int, string|null>>
     */
    public function parse(UploadedFile $file): array
    {
        return $this->parsePath($file->getRealPath(), strtolower($file->extension()));
    }

    /**
     * Parse an XLSX/CSV file from a path into raw rows (including the header row).
     *
     * @return array<int, array<int, string|null>>
     */
    public function parsePath(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if ($extension === 'csv') {
            return $this->parseCsv($path);
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsx($path);
        }

        throw ValidationException::withMessages([
            'file' => ['Unsupported import file type. Use an XLSX or CSV file.'],
        ]);
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseCsv(string $path): array
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
    private function parseXlsx(string $path): array
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
}
