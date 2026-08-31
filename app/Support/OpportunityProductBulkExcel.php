<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Template & parsing bulk produk opportunity (Excel .xlsx / CSV).
 */
class OpportunityProductBulkExcel
{
    public const HEADERS = [
        'jenis',
        'nama',
        'brand',
        'category',
        'qty',
        'vendor',
        'harga_jual_exclude',
        'diskon_exclude',
        'ongkir_exclude',
        'harga_beli_exclude',
    ];

    /**
     * Contoh baris di template (boleh dihapus user).
     *
     * @return list<list<string|int|float>>
     */
    public static function exampleRows(): array
    {
        return [
            ['barang', 'Contoh Item A', 'Acer', 'Laptop', 1, 'Vendor A', 1000000, 0, 0, 800000],
            ['jasa', 'Contoh Jasa B', 'ABB', 'Service', 2, 'Vendor B', 500000, 0, 0, 350000],
        ];
    }

    public static function downloadTemplate(): StreamedResponse
    {
        $filename = 'template-produk-opportunity.xlsx';
        $binary = self::buildXlsx(self::HEADERS, self::exampleRows());

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float>>  $rows
     */
    public static function buildXlsx(array $headers, array $rows): string
    {
        $sheetRows = array_merge([$headers], $rows);
        $sheetXml = self::sheetXml($sheetRows);

        $tmp = tempnam(sys_get_temp_dir(), 'oppxlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Gagal membuat file sementara template Excel.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Gagal menulis template Excel.');
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

        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Produk" sheetId="1" r:id="rId1"/>
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
            throw new \RuntimeException('Gagal membaca template Excel.');
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

    /**
     * Normalisasi baris hasil parse (dari FE SheetJS / CSV) ke struktur produk form.
     *
     * @param  array<string, mixed>  $row
     * @return array{name: string, quantity: float, vendor: string, sell_exclude: float, discount_exclude: float, shipping_exclude: float, cost_exclude: float, item_kind: string}|null
     */
    public static function normalizeRow(array $row): ?array
    {
        $map = [];
        foreach ($row as $key => $value) {
            $map[self::normalizeHeader((string) $key)] = $value;
        }

        $name = trim((string) ($map['nama'] ?? $map['name'] ?? $map['item'] ?? ''));
        if ($name === '') {
            return null;
        }

        $kindRaw = Str::lower(trim((string) ($map['jenis'] ?? $map['item_kind'] ?? $map['barang_jasa'] ?? 'barang')));
        $itemKind = str_contains($kindRaw, 'jasa')
            ? OpportunityProductPricing::KIND_JASA
            : OpportunityProductPricing::KIND_BARANG;

        return [
            'name' => $name,
            'quantity' => self::toNumber($map['qty'] ?? $map['quantity'] ?? $map['jumlah'] ?? 1),
            'vendor' => trim((string) ($map['vendor'] ?? '')),
            'brand' => trim((string) ($map['brand'] ?? $map['merek'] ?? '')),
            'category' => trim((string) ($map['category'] ?? $map['kategori'] ?? '')),
            'sell_exclude' => self::toNumber($map['harga_jual_exclude'] ?? $map['sell_exclude'] ?? $map['harga_jual'] ?? 0),
            'discount_exclude' => self::toNumber($map['diskon_exclude'] ?? $map['discount_exclude'] ?? $map['diskon'] ?? 0),
            'shipping_exclude' => self::toNumber($map['ongkir_exclude'] ?? $map['shipping_exclude'] ?? $map['ongkir'] ?? 0),
            'cost_exclude' => self::toNumber($map['harga_beli_exclude'] ?? $map['cost_exclude'] ?? $map['harga_beli'] ?? $map['modal'] ?? 0),
            'item_kind' => $itemKind,
        ];
    }

    public static function normalizeHeader(string $header): string
    {
        $h = Str::lower(trim($header));
        $h = str_replace([' ', '-'], '_', $h);

        return match ($h) {
            'barang_jasa', 'tipe', 'kind' => 'jenis',
            'item', 'product', 'produk', 'nama_item', 'nama_produk' => 'nama',
            'quantity', 'jumlah', 'qty.' => 'qty',
            'harga_jual', 'sell', 'sell_exclude', 'harga_jual_excl' => 'harga_jual_exclude',
            'diskon', 'discount', 'discount_exclude', 'diskon_item' => 'diskon_exclude',
            'ongkir', 'shipping', 'shipping_exclude', 'ongkir_item', 'harga_ongkir' => 'ongkir_exclude',
            'harga_beli', 'modal', 'cost', 'cost_exclude', 'harga_modal' => 'harga_beli_exclude',
            'merek', 'brand_name' => 'brand',
            'kategori', 'category_name' => 'category',
            default => $h,
        };
    }

    public static function toNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return 0.0;
        }

        // Format ID: 1.000.000,50 atau EN: 1,000,000.50 / plain 1000000
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $str)) {
            $str = str_replace(',', '', $str);
        } else {
            $str = str_replace([' ', ','], ['', '.'], $str);
            // Jika ada lebih dari satu titik, anggap pemisah ribuan.
            if (substr_count($str, '.') > 1) {
                $str = str_replace('.', '', $str);
            }
        }

        return (float) $str;
    }
}
