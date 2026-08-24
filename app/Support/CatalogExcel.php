<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Import / export Excel master brand & category.
 */
class CatalogExcel
{
    public const HEADERS = ['nama', 'aktif', 'urutan'];

    /**
     * @param  iterable<Model>  $records
     */
    public static function export(string $filename, iterable $records, string $sheetName = 'Data'): StreamedResponse
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                (string) ($record->name ?? ''),
                ! empty($record->is_active) ? 'ya' : 'tidak',
                (int) ($record->sort_order ?? 0),
            ];
        }

        return self::download($filename, $rows, $sheetName);
    }

    public static function template(string $filename, string $exampleName, string $sheetName = 'Data'): StreamedResponse
    {
        return self::download($filename, [
            [$exampleName, 'ya', 0],
        ], $sheetName);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{created: int, updated: int, skipped: int}
     */
    public static function import(UploadedFile $file, string $modelClass): array
    {
        $rows = self::parseFile($file);

        return DB::transaction(function () use ($rows, $modelClass) {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '' || self::isExampleName($name)) {
                    $skipped++;
                    continue;
                }

                if (mb_strlen($name) > 255) {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'name' => $name,
                    'is_active' => $row['is_active'],
                    'sort_order' => $row['sort_order'],
                ];

                $existing = $modelClass::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                    continue;
                }

                $modelClass::create($payload);
                $created++;
            }

            return compact('created', 'updated', 'skipped');
        });
    }

    public static function resultMessage(array $result, string $label): string
    {
        $created = (int) ($result['created'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $imported = $created + $updated;

        $msg = sprintf(
            'Import %s selesai: %d baris (%d baru, %d diperbarui).',
            $label,
            $imported,
            $created,
            $updated
        );

        if ($skipped > 0) {
            $msg .= sprintf(' %d baris dilewati.', $skipped);
        }

        return $msg;
    }

    /**
     * @param  list<list<string|int|float>>  $rows
     */
    protected static function download(string $filename, array $rows, string $sheetName): StreamedResponse
    {
        $binary = self::buildXlsx($rows, $sheetName);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<list<string|int|float>>  $rows
     */
    protected static function buildXlsx(array $rows, string $sheetName): string
    {
        $sheetRows = array_merge([self::HEADERS], $rows);
        $sheetXml = self::sheetXml($sheetRows);
        $safeName = htmlspecialchars($sheetName !== '' ? $sheetName : 'Data', ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $tmp = tempnam(sys_get_temp_dir(), 'catxlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Gagal membuat file sementara Excel.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Gagal menulis file Excel.');
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
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
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="{$safeName}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);

        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        if ($binary === false) {
            throw new \RuntimeException('Gagal membaca file Excel.');
        }

        return $binary;
    }

    /**
     * @param  list<list<string|int|float>>  $rows
     */
    protected static function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rIndex => $row) {
            $rowNum = $rIndex + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $cIndex => $value) {
                $col = self::columnLetter($cIndex).$rowNum;
                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && ! preg_match('/^0\d+/', $value))) {
                    $xml .= '<c r="'.$col.'"><v>'.self::xml($value).'</v></c>';
                } else {
                    $xml .= '<c r="'.$col.'" t="inlineStr"><is><t>'.self::xml((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * @return list<array{name: string, is_active: bool, sort_order: int}>
     */
    protected static function parseFile(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('File tidak dapat dibaca.');
        }

        $matrix = match ($ext) {
            'csv' => self::parseCsv($path),
            'xlsx' => self::parseXlsx($path),
            default => throw new \RuntimeException('Format file harus .xlsx atau .csv.'),
        };

        if ($matrix === []) {
            return [];
        }

        $headerRow = array_shift($matrix);
        $keys = [];
        foreach ($headerRow as $i => $header) {
            $normalized = self::normalizeHeader((string) $header);
            if ($normalized !== '') {
                $keys[$i] = $normalized;
            }
        }

        $out = [];
        foreach ($matrix as $row) {
            $map = [];
            foreach ($keys as $i => $key) {
                $map[$key] = $row[$i] ?? '';
            }
            $name = trim((string) ($map['nama'] ?? $map['name'] ?? ''));
            $out[] = [
                'name' => $name,
                'is_active' => self::parseActive($map['aktif'] ?? $map['is_active'] ?? $map['status'] ?? 'ya'),
                'sort_order' => self::parseSort($map['urutan'] ?? $map['sort_order'] ?? $map['order'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<list<string>>
     */
    protected static function parseCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $firstLine = strtok($raw, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $isFirst = true;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            if ($isFirst && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? (string) $data[0];
                $isFirst = false;
            }
            $rows[] = array_map(fn ($v) => trim((string) $v), $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    protected static function parseXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File Excel tidak valid.');
        }

        $sheetXml = self::firstWorksheetXml($zip);
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($sheetXml === '') {
            throw new \RuntimeException('Worksheet Excel kosong atau tidak ditemukan.');
        }

        $shared = self::parseSharedStrings(is_string($sharedXml) ? $sharedXml : '');

        $sheet = self::loadSpreadsheetXml($sheetXml);
        if ($sheet === null) {
            throw new \RuntimeException('Gagal membaca worksheet Excel.');
        }

        $grid = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $rowNum = max(1, (int) ($row['r'] ?? 0));
            foreach ($row->c ?? [] as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $col = self::columnIndexFromRef($ref);
                if ($col < 0) {
                    continue;
                }
                $grid[$rowNum][$col] = self::cellValue($cell, $shared);
            }
        }

        if ($grid === []) {
            return [];
        }

        ksort($grid);
        $maxCol = 0;
        foreach ($grid as $cols) {
            if ($cols === []) {
                continue;
            }
            $maxCol = max($maxCol, max(array_keys($cols)));
        }

        $matrix = [];
        foreach ($grid as $cols) {
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $line[] = (string) ($cols[$i] ?? '');
            }
            if (implode('', $line) === '') {
                continue;
            }
            $matrix[] = $line;
        }

        return $matrix;
    }

    protected static function firstWorksheetXml(ZipArchive $zip): string
    {
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (is_string($rels) && preg_match_all('/Target="([^"]*worksheets\/[^"]+)"/i', $rels, $matches)) {
            foreach ($matches[1] as $target) {
                $target = str_replace('\\', '/', (string) $target);
                if (str_starts_with($target, '/')) {
                    $path = ltrim($target, '/');
                } elseif (str_starts_with($target, 'xl/')) {
                    $path = $target;
                } else {
                    $path = 'xl/'.ltrim($target, '/');
                }

                $xml = $zip->getFromName($path);
                if (is_string($xml) && $xml !== '') {
                    return $xml;
                }
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || ! preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) {
                continue;
            }
            $xml = $zip->getFromName($name);
            if (is_string($xml) && $xml !== '') {
                return $xml;
            }
        }

        return '';
    }

    /**
     * Excel Microsoft sering memakai prefix namespace (mc:Ignorable, dll).
     * Buang deklarasi + atribut ber-prefix supaya SimpleXML tidak error.
     */
    protected static function loadSpreadsheetXml(string $xml): ?\SimpleXMLElement
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $xml = preg_replace('/xmlns(:[A-Za-z0-9]+)?="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace("/xmlns(:[A-Za-z0-9]+)?='[^']*'/", '', $xml) ?? $xml;
        $xml = preg_replace('/\s+(?!xml:)[A-Za-z_][\w.-]*:[A-Za-z_][\w.-]*="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace("/\s+(?!xml:)[A-Za-z_][\w.-]*:[A-Za-z_][\w.-]*='[^']*'/", '', $xml) ?? $xml;

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $parsed === false ? null : $parsed;
    }

    /**
     * @return list<string>
     */
    protected static function parseSharedStrings(string $xml): array
    {
        if (trim($xml) === '') {
            return [];
        }

        $sst = self::loadSpreadsheetXml($xml);
        if ($sst === null) {
            return [];
        }

        $out = [];
        foreach ($sst->si ?? [] as $si) {
            $text = '';
            if (isset($si->t)) {
                $text .= (string) $si->t;
            }
            foreach ($si->r ?? [] as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $out[] = $text;
        }

        return $out;
    }

    /**
     * @param  list<string>  $shared
     */
    protected static function cellValue(\SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? $cell->is->r->t ?? ''));
        }

        $raw = trim((string) ($cell->v ?? ''));
        if ($type === 's' && $raw !== '' && isset($shared[(int) $raw])) {
            return trim($shared[(int) $raw]);
        }

        return $raw;
    }

    protected static function columnIndexFromRef(string $ref): int
    {
        if (! preg_match('/^([A-Za-z]+)/', $ref, $m)) {
            return -1;
        }

        $letters = strtoupper($m[1]);
        $n = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $n = ($n * 26) + (ord($letters[$i]) - 64);
        }

        return $n - 1;
    }

    protected static function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }

    protected static function xml(string|int|float $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected static function normalizeHeader(string $header): string
    {
        $h = Str::lower(trim($header));
        $h = str_replace([' ', '-'], '_', $h);

        return match ($h) {
            'name', 'brand', 'category', 'kategori', 'merek' => 'nama',
            'is_active', 'status', 'active' => 'aktif',
            'sort_order', 'order', 'urut' => 'urutan',
            default => $h,
        };
    }

    protected static function parseActive(mixed $value): bool
    {
        $raw = Str::lower(trim((string) $value));
        if ($raw === '') {
            return true;
        }

        return in_array($raw, ['1', 'ya', 'yes', 'y', 'true', 'aktif', 'active', 'on'], true);
    }

    protected static function parseSort(mixed $value): int
    {
        $n = (int) round(OpportunityProductBulkExcel::toNumber($value));

        return max(0, min(9999, $n));
    }

    protected static function isExampleName(string $name): bool
    {
        return str_starts_with(Str::lower($name), 'contoh ');
    }
}
