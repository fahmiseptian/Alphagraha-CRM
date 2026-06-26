<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Logika inti penawaran: penomoran otomatis & merge data ke template HTML
 * sehingga hasil penawaran selalu konsisten mengikuti standar perusahaan.
 */
class QuotationService
{
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
     * @return array<string, string>
     */
    public function placeholders(Quotation $quotation, ?QuotationTemplate $template = null): array
    {
        $quotation->loadMissing(['items', 'creator.profile', 'opportunity.contact']);
        $currency = $quotation->currency;
        $creator = $quotation->creator;
        $contact = $quotation->opportunity?->contact;
        $company = $this->companyConfigForTemplate($template);

        $placeDate = optional($quotation->quotation_date)->translatedFormat('d F Y') ?: now()->translatedFormat('d F Y');

        return [
            'customer_name' => (string) $quotation->customer_name,
            'company_name' => (string) $quotation->company_name,
            'customer_email' => (string) $quotation->customer_email,
            'customer_phone' => (string) $quotation->customer_phone,
            'customer_address' => nl2br(e((string) $quotation->customer_address)),
            'contact_person' => e((string) ($contact?->full_name ?? '')),
            'quotation_number' => (string) $quotation->number,
            'quotation_ref' => (string) $quotation->number,
            'quotation_date' => optional($quotation->quotation_date)->translatedFormat('d F Y'),
            'quotation_place_date' => 'Jakarta, '.$placeDate,
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
            'sales_name' => (string) optional($creator)->display_name,
            'sales_title' => (string) (optional($creator)->title ?: 'Account Manager'),
            'sales_signature' => $this->renderSalesSignature($creator),
            'revision' => (string) $quotation->revision,
            'items_table' => $this->renderItemsTable($quotation),
            'items_rows' => $this->renderItemsRows($quotation),
            'items_table_idr' => $this->renderItemsTableIndo($quotation),
            'items_rows_idr' => $this->renderItemsRowsIndo($quotation),
            'company_legal_name' => $company['legal_name'] ?? '',
            'company_address' => $company['address'] ?? '',
            'company_phone' => $company['phone'] ?? '',
            'company_email' => $company['email'] ?? '',
            'company_color' => $company['color'] ?? '#2563eb',
        ];
    }

    public function render(Quotation $quotation, string $templateHtml, ?QuotationTemplate $template = null): string
    {
        $data = $this->placeholders($quotation, $template);
        $html = $templateHtml;

        foreach ($data as $key => $value) {
            $pattern = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/';
            $html = preg_replace($pattern, $value ?? '', $html);
        }

        return $html;
    }

    protected function companyConfigForTemplate(?QuotationTemplate $template): array
    {
        $key = match ($template?->code) {
            'agc-indo' => 'agc',
            'eps-indo' => 'eps',
            'psi-indo' => 'psi',
            default => 'agc',
        };

        return config("crm.quotation_companies.{$key}", config('crm.quotation_companies.agc', []));
    }

    protected function renderSalesSignature(?User $user): string
    {
        if (! $user) {
            return '';
        }

        $path = $user->signatureAbsolutePath();
        if (! $path) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/png';
        $data = base64_encode((string) file_get_contents($path));

        return '<img src="data:'.$mime.';base64,'.$data.'" alt="Signature" style="max-height:72px;max-width:220px;">';
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
            . '<th style="width:40px;">No</th><th>Description</th><th style="width:90px;">Qty</th>'
            . '<th style="width:140px;">Price</th><th style="width:140px;">Amount</th>'
            . '</tr></thead><tbody>'
            . $this->renderItemsRows($quotation)
            . '</tbody></table>';
    }

    protected function renderItemsRowsIndo(Quotation $quotation): string
    {
        $rows = '';
        $no = 1;
        $taxRate = (float) $quotation->tax_percent / 100;

        foreach ($quotation->items as $item) {
            $qty = (float) $item->quantity;
            $excl = (float) $item->unit_price;
            $incl = $excl * (1 + $taxRate);
            $lineTotal = (float) $item->total;
            $unitLabel = trim((string) $item->unit) ?: 'unit';

            $rows .= '<tr>'
                . '<td style="text-align:center;padding:6px;border:1px solid #000000;">'.$no++.'</td>'
                . '<td style="padding:6px;border:1px solid #000000;">'.e($item->name)
                . ($item->description ? '<br><small style="color:#666;">'.nl2br(e($item->description)).'</small>' : '')
                . '</td>'
                . '<td style="text-align:center;padding:6px;border:1px solid #000000;">'
                .rtrim(rtrim(number_format($qty, 2), '0'), '.').' '.e($unitLabel).'</td>'
                . '<td style="text-align:right;padding:6px;border:1px solid #000000;">'.money($excl, $quotation->currency).'</td>'
                . '<td style="text-align:right;padding:6px;border:1px solid #000000;">'.money($incl, $quotation->currency).'</td>'
                . '<td style="text-align:right;padding:6px;border:1px solid #000000;">'.money($lineTotal, $quotation->currency).'</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="text-align:center;padding:8px;border:1px solid #000000;color:#999;">Belum ada item.</td></tr>';
        }

        return $rows;
    }

    protected function renderItemsTableIndoSummary(Quotation $quotation): string
    {
        $currency = $quotation->currency;
        $taxLabel = 'PPn '.rtrim(rtrim(number_format((float) $quotation->tax_percent, 2), '0'), '.').'%';
        $labelStyle = 'padding:6px;border:1px solid #000000;text-align:right;font-weight:700;';
        $valueStyle = 'padding:6px;border:1px solid #000000;text-align:right;font-weight:700;';

        $rows = '<tr>'
            . '<td colspan="4" style="border:none;">&nbsp;</td>'
            . '<td style="'.$labelStyle.'">Subtotal</td>'
            . '<td style="'.$valueStyle.'">'.money($quotation->subtotal, $currency).'</td>'
            . '</tr>';

        if ((float) $quotation->discount > 0) {
            $rows .= '<tr>'
                . '<td colspan="4" style="border:none;">&nbsp;</td>'
                . '<td style="'.$labelStyle.'">Diskon</td>'
                . '<td style="'.$valueStyle.'">-'.money($quotation->discount, $currency).'</td>'
                . '</tr>';
        }

        $rows .= '<tr>'
            . '<td colspan="4" style="border:none;">&nbsp;</td>'
            . '<td style="'.$labelStyle.'">'.$taxLabel.'</td>'
            . '<td style="'.$valueStyle.'">'.money($quotation->tax_amount, $currency).'</td>'
            . '</tr>'
            . '<tr>'
            . '<td colspan="4" style="border:none;">&nbsp;</td>'
            . '<td style="'.$labelStyle.'">Total</td>'
            . '<td style="'.$valueStyle.'">'.money($quotation->total, $currency).'</td>'
            . '</tr>';

        return $rows;
    }

    protected function renderItemsTableIndo(Quotation $quotation): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;margin:12px 0;">'
            . '<thead><tr style="background:#ffffff;">'
            . '<th style="padding:6px;border:1px solid #000000;width:32px;">No.</th>'
            . '<th style="padding:6px;border:1px solid #000000;">Deskripsi</th>'
            . '<th style="padding:6px;border:1px solid #000000;width:72px;">Qty</th>'
            . '<th style="padding:6px;border:1px solid #000000;width:110px;">Harga Unit Excl PPN</th>'
            . '<th style="padding:6px;border:1px solid #000000;width:110px;">Harga Unit Incl PPN</th>'
            . '<th style="padding:6px;border:1px solid #000000;width:120px;">Total Harga IDR</th>'
            . '</tr></thead><tbody>'
            . $this->renderItemsRowsIndo($quotation)
            . $this->renderItemsTableIndoSummary($quotation)
            . '</tbody></table>';
    }
}
