<?php

namespace App\Services;

use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Logika inti penawaran: penomoran otomatis & merge data ke template HTML
 * sehingga hasil penawaran selalu konsisten mengikuti standar perusahaan.
 */
class QuotationService
{
    /**
     * Hasilkan nomor penawaran unik, format: QUO/YYYY/MM/0001
     */
    public function generateNumber(?Carbon $date = null): string
    {
        $date = $date ?? now();
        $prefix = sprintf('QUO/%s/%s/', $date->format('Y'), $date->format('m'));

        $last = Quotation::where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = 1;
        if ($last) {
            $sequence = (int) Str::afterLast($last, '/') + 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Daftar placeholder yang didukung beserta nilainya untuk sebuah penawaran.
     *
     * @return array<string, string>
     */
    public function placeholders(Quotation $quotation): array
    {
        $quotation->loadMissing(['items', 'creator']);
        $currency = $quotation->currency;

        return [
            'customer_name' => (string) $quotation->customer_name,
            'company_name' => (string) $quotation->company_name,
            'customer_email' => (string) $quotation->customer_email,
            'customer_phone' => (string) $quotation->customer_phone,
            'customer_address' => nl2br(e((string) $quotation->customer_address)),
            'quotation_number' => (string) $quotation->number,
            'quotation_date' => optional($quotation->quotation_date)->translatedFormat('d F Y'),
            'valid_until' => optional($quotation->valid_until)->translatedFormat('d F Y') ?: '-',
            'currency' => $currency,
            'subtotal' => money($quotation->subtotal, $currency),
            'discount' => money($quotation->discount, $currency),
            'tax_percent' => rtrim(rtrim(number_format((float) $quotation->tax_percent, 2), '0'), '.') . '%',
            'tax_amount' => money($quotation->tax_amount, $currency),
            'total_price' => money($quotation->total, $currency),
            'total' => money($quotation->total, $currency),
            'notes' => nl2br(e((string) $quotation->notes)),
            'terms' => nl2br(e((string) $quotation->terms)),
            'sales_name' => (string) optional($quotation->creator)->name,
            'revision' => (string) $quotation->revision,
            'items_table' => $this->renderItemsTable($quotation),
            'items_rows' => $this->renderItemsRows($quotation),
        ];
    }

    /**
     * Gabungkan data penawaran ke dalam template HTML (mengganti placeholder).
     */
    public function render(Quotation $quotation, string $templateHtml): string
    {
        $data = $this->placeholders($quotation);
        $html = $templateHtml;

        foreach ($data as $key => $value) {
            // Cocokkan {{ key }} dengan spasi opsional.
            $pattern = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/';
            $html = preg_replace($pattern, $value ?? '', $html);
        }

        return $html;
    }

    protected function renderItemsRows(Quotation $quotation): string
    {
        $rows = '';
        $no = 1;

        foreach ($quotation->items as $item) {
            $rows .= '<tr>'
                . '<td style="text-align:center;">' . $no++ . '</td>'
                . '<td>' . e($item->name)
                . ($item->description ? '<br><small style="color:#666;">' . nl2br(e($item->description)) . '</small>' : '')
                . '</td>'
                . '<td style="text-align:center;">' . rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.')
                . ' ' . e($item->unit) . '</td>'
                . '<td style="text-align:right;">' . money($item->unit_price, $quotation->currency) . '</td>'
                . '<td style="text-align:right;">' . money($item->total, $quotation->currency) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#999;">Belum ada item.</td></tr>';
        }

        return $rows;
    }

    protected function renderItemsTable(Quotation $quotation): string
    {
        return '<table style="width:100%;border-collapse:collapse;" border="1" cellpadding="8">'
            . '<thead><tr style="background:#f3f4f6;">'
            . '<th style="width:40px;">No</th><th>Deskripsi</th><th style="width:90px;">Qty</th>'
            . '<th style="width:140px;">Harga</th><th style="width:140px;">Jumlah</th>'
            . '</tr></thead><tbody>'
            . $this->renderItemsRows($quotation)
            . '</tbody></table>';
    }
}
