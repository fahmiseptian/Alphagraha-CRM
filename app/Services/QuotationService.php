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
        $html = $this->processTemplateConditionals($templateHtml, $quotation);

        foreach ($data as $key => $value) {
            foreach ($this->placeholderPatterns($key) as $pattern) {
                $html = preg_replace($pattern, $value ?? '', $html);
            }
        }

        return $this->stripUnprocessedBladeDirectives($html);
    }

    /**
     * @return list<string>
     */
    protected function placeholderPatterns(string $key): array
    {
        $quoted = preg_quote($key, '/');

        return [
            '/\{\{\s*'.$quoted.'\s*\}\}/',
            '/\{!!\s*'.$quoted.'\s*!!\}/',
            '/@\{\{\s*'.$quoted.'\s*\}\}/',
            '/@\{!!\s*'.$quoted.'\s*!!\}/',
        ];
    }

    protected function processTemplateConditionals(string $html, Quotation $quotation): string
    {
        $rules = [
            '/@if\s*\(\s*trim\s*\(\s*\$notes\s*\?\?\s*(?:\'\'|"")\s*\)\s*!==\s*(?:\'\'|"")\s*\)(.*?)@endif/is'
                => static fn (array $m) => trim((string) $quotation->notes) !== '' ? $m[1] : '',
            '/@if\s*\(\s*trim\s*\(\s*\$terms\s*\?\?\s*(?:\'\'|"")\s*\)\s*!==\s*(?:\'\'|"")\s*\)(.*?)@endif/is'
                => static fn (array $m) => trim((string) $quotation->terms) !== '' ? $m[1] : '',
            '/@if\s*\(\s*!\s*empty\s*\(\s*\$notes\s*\)\s*\)(.*?)@endif/is'
                => static fn (array $m) => trim((string) $quotation->notes) !== '' ? $m[1] : '',
            '/@if\s*\(\s*!\s*empty\s*\(\s*\$terms\s*\)\s*\)(.*?)@endif/is'
                => static fn (array $m) => trim((string) $quotation->terms) !== '' ? $m[1] : '',
            '/@if\s*\(\s*\$notes\s*\)(.*?)@endif/is'
                => static fn (array $m) => trim((string) $quotation->notes) !== '' ? $m[1] : '',
            '/@if\s*\(\s*\$terms\s*\)(.*?)@endif/is'
                => static fn (array $m) => trim((string) $quotation->terms) !== '' ? $m[1] : '',
        ];

        foreach ($rules as $pattern => $callback) {
            $html = preg_replace_callback($pattern, $callback, $html) ?? $html;
        }

        return $html;
    }

    protected function stripUnprocessedBladeDirectives(string $html): string
    {
        $html = preg_replace('/@if\s*\([^)]*\)/', '', $html) ?? $html;
        $html = preg_replace('/@elseif\s*\([^)]*\)/', '', $html) ?? $html;
        $html = preg_replace('/@else\b/', '', $html) ?? $html;
        $html = preg_replace('/@endif\b/', '', $html) ?? $html;

        return $html;
    }

    /**
     * Siapkan HTML untuk DomPDF: font aman + path gambar lokal.
     */
    public function prepareHtmlForPdf(string $html): string
    {
        $html = $this->resolveImagesForPdf($html);

        $fallback = 'DejaVu Sans, sans-serif';

        $html = preg_replace(
            '/font-family\s*:\s*([^;"\'}]+)([;"\'}])/i',
            'font-family: '.$fallback.'$2',
            $html
        );

        return preg_replace(
            '/face\s*=\s*("|\')[^"\']*(\1)/i',
            'face="DejaVu Sans"',
            $html
        ) ?? $html;
    }

    protected function resolveImagesForPdf(string $html): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*?)\ssrc=(["\'])([^"\']+)\2([^>]*)>/i',
            function (array $matches): string {
                $resolved = $this->resolveImageSrcForPdf($matches[3]);

                return '<img'.$matches[1].' src='.$matches[2].$resolved.$matches[2].$matches[4].'>';
            },
            $html
        ) ?? $html;
    }

    protected function resolveImageSrcForPdf(string $src): string
    {
        if (str_starts_with($src, 'data:')) {
            return $src;
        }

        $path = $this->localImagePath($src);

        if ($path && is_file($path)) {
            return str_replace('\\', '/', realpath($path) ?: $path);
        }

        return $src;
    }

    protected function localImagePath(string $src): ?string
    {
        if (str_starts_with($src, 'file://')) {
            $path = substr($src, 7);

            return is_file($path) ? $path : null;
        }

        if (preg_match('#^https?://[^/]+(/.+)$#i', $src, $m)) {
            $src = $m[1];
        }

        $relative = ltrim($src, '/');

        if ($relative === '') {
            return null;
        }

        if (str_starts_with($relative, 'storage/')) {
            return public_path($relative);
        }

        if (str_starts_with($relative, 'public/')) {
            return base_path($relative);
        }

        $publicPath = public_path($relative);
        if (is_file($publicPath)) {
            return $publicPath;
        }

        $basePath = base_path($relative);
        if (is_file($basePath)) {
            return $basePath;
        }

        return null;
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

    protected function cellStyle(string $align = 'left', bool $header = false): string
    {
        $base = 'padding:6px;border:1px solid #000000;';
        $alignStyle = match ($align) {
            'right' => 'text-align:right;',
            'center' => 'text-align:center;',
            default => 'text-align:left;',
        };

        return $base.$alignStyle.($header ? 'font-weight:700;background:#ffffff;' : '');
    }

    protected function renderItemsRows(Quotation $quotation): string
    {
        $rows = '';
        $no = 1;

        foreach ($quotation->items as $item) {
            $unitLabel = trim((string) $item->unit) ?: 'unit';

            $rows .= '<tr>'
                .'<td style="'.$this->cellStyle('center').'">'.$no++.'</td>'
                .'<td style="'.$this->cellStyle().'">'.e($item->name)
                .($item->description ? '<br><small style="color:#666;">'.nl2br(e($item->description)).'</small>' : '')
                .'</td>'
                .'<td style="'.$this->cellStyle('center').'">'
                .rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.').' '.e($unitLabel).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($item->unit_price, $quotation->currency).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($item->total, $quotation->currency).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="'.$this->cellStyle('center').'color:#999;">No items yet.</td></tr>';
        }

        return $rows;
    }

    protected function renderItemsTable(Quotation $quotation): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;margin:12px 0;">'
            .'<thead><tr>'
            .'<th style="'.$this->cellStyle('center', true).'width:32px;">No</th>'
            .'<th style="'.$this->cellStyle('left', true).'">Description</th>'
            .'<th style="'.$this->cellStyle('center', true).'width:72px;">Qty</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Unit Price</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Amount</th>'
            .'</tr></thead><tbody>'
            .$this->renderItemsRows($quotation)
            .$this->renderItemsTableSummary($quotation, 3)
            .'</tbody></table>';
    }

    protected function renderItemsRowsIndo(Quotation $quotation): string
    {
        $rows = '';
        $no = 1;

        foreach ($quotation->items as $item) {
            $unitLabel = trim((string) $item->unit) ?: 'Unit';

            $rows .= '<tr>'
                .'<td style="'.$this->cellStyle('center').'">'.$no++.'</td>'
                .'<td style="'.$this->cellStyle().'">'.e($item->name)
                .($item->description ? '<br><small style="color:#666;">'.nl2br(e($item->description)).'</small>' : '')
                .'</td>'
                .'<td style="'.$this->cellStyle('center').'">'
                .rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.').' '.e($unitLabel).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($item->unit_price, $quotation->currency).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($item->total, $quotation->currency).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="'.$this->cellStyle('center').'color:#999;">Belum ada item.</td></tr>';
        }

        return $rows;
    }

    protected function renderItemsTableSummary(Quotation $quotation, int $labelColspan): string
    {
        $currency = $quotation->currency;
        $labelStyle = $this->cellStyle('right', true);
        $valueStyle = $this->cellStyle('right', true);

        $rows = '<tr>'
            .'<td colspan="'.$labelColspan.'" style="border:none;">&nbsp;</td>'
            .'<td style="'.$labelStyle.'">Subtotal</td>'
            .'<td style="'.$valueStyle.'">'.money($quotation->subtotal, $currency).'</td>'
            .'</tr>';

        if ((float) $quotation->discount > 0) {
            $rows .= '<tr>'
                .'<td colspan="'.$labelColspan.'" style="border:none;">&nbsp;</td>'
                .'<td style="'.$labelStyle.'">Diskon</td>'
                .'<td style="'.$valueStyle.'">-'.money($quotation->discount, $currency).'</td>'
                .'</tr>';
        }

        if ((float) $quotation->tax_percent > 0) {
            $taxLabel = 'PPn '.rtrim(rtrim(number_format((float) $quotation->tax_percent, 2), '0'), '.').'%';
            $rows .= '<tr>'
                .'<td colspan="'.$labelColspan.'" style="border:none;">&nbsp;</td>'
                .'<td style="'.$labelStyle.'">'.$taxLabel.'</td>'
                .'<td style="'.$valueStyle.'">'.money($quotation->tax_amount, $currency).'</td>'
                .'</tr>';
        }

        $rows .= '<tr>'
            .'<td colspan="'.$labelColspan.'" style="border:none;">&nbsp;</td>'
            .'<td style="'.$labelStyle.'">Total</td>'
            .'<td style="'.$valueStyle.'">'.money($quotation->total, $currency).'</td>'
            .'</tr>';

        return $rows;
    }

    protected function renderItemsTableIndoSummary(Quotation $quotation): string
    {
        return $this->renderItemsTableSummary($quotation, 3);
    }

    protected function renderItemsTableIndo(Quotation $quotation): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;margin:12px 0;">'
            .'<thead><tr>'
            .'<th style="'.$this->cellStyle('center', true).'width:32px;">No.</th>'
            .'<th style="'.$this->cellStyle('left', true).'">Spesifikasi</th>'
            .'<th style="'.$this->cellStyle('center', true).'width:72px;">Qty</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Harga Unit IDR</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Total Harga IDR</th>'
            .'</tr></thead><tbody>'
            .$this->renderItemsRowsIndo($quotation)
            .$this->renderItemsTableIndoSummary($quotation)
            .'</tbody></table>';
    }
}
