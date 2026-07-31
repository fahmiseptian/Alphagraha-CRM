<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Models\User;
use Carbon\Carbon;

/**
 * Logika inti penawaran: penomoran otomatis & merge data ke template HTML
 * sehingga hasil penawaran selalu konsisten mengikuti standar perusahaan.
 */
class QuotationService
{
    /**
     * Format: 0002/KA/QO/VII/26
     * Sequence unik per tahun. Kode sales dari profil user.
     */
    public function generateNumber(?string $salesCode = null, ?Carbon $date = null, ?User $forUser = null): string
    {
        $date = $date ?? now();
        $forUser = $forUser ?? auth()->user();
        $salesCode = strtoupper(trim((string) ($salesCode ?: $this->resolveSalesCode($forUser))));
        $prefix = (string) config('crm.quotation_number.prefix', 'QO');
        $pad = (int) config('crm.quotation_number.sequence_pad', 4);
        $year = $date->format('y');
        $romanMonth = $this->romanMonth((int) $date->format('n'));

        if ($salesCode === '') {
            $who = $forUser?->display_name ?: $forUser?->user_name ?: 'user ini';
            throw new \RuntimeException(
                'Sales Code untuk '.$who.' belum diisi. Minta admin mengisi Sales Code di menu Users.'
            );
        }

        $sequence = $this->nextYearlySequence((int) $date->format('Y'), $pad);
        $seq = str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);

        return sprintf('%s/%s/%s/%s/%s', $seq, $salesCode, $prefix, $romanMonth, $year);
    }

    /**
     * Terapkan suffix revisi dokumen: 0002-R1/KA/QO/VII/26
     */
    public function withDocumentRevision(string $baseNumber, int $documentRevision): string
    {
        if ($documentRevision < 1) {
            return $baseNumber;
        }

        if (! preg_match('/^(\d+)(\/.*)$/', $baseNumber, $m)) {
            return $baseNumber.'-R'.$documentRevision;
        }

        return $m[1].'-R'.$documentRevision.$m[2];
    }

    public function stripDocumentRevision(string $number): string
    {
        return preg_replace('/^(\d+)-R\d+(\/.*)$/', '$1$2', $number) ?: $number;
    }

    public function parseDocumentRevision(string $number): int
    {
        if (preg_match('/^\d+-R(\d+)\//', $number, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    protected function nextYearlySequence(int $year, int $pad): int
    {
        $yy = substr((string) $year, -2);
        $max = 0;

        // Kunci baris terkait tahun berjalan agar sequence aman dari race condition.
        $numbers = Quotation::query()
            ->where(function ($q) use ($yy, $year) {
                $q->where('base_number', 'like', '%/'.$yy)
                    ->orWhere('number', 'like', '%/'.$yy)
                    ->orWhere('number', 'like', 'QUO/'.$year.'/%');
            })
            ->lockForUpdate()
            ->get(['number', 'base_number']);

        foreach ($numbers as $row) {
            foreach ([$row->base_number, $row->number] as $candidate) {
                if (! $candidate) {
                    continue;
                }

                if (preg_match('/^(\d+)(?:-R\d+)?\/[A-Z0-9]+\/QO\//i', $candidate, $m)) {
                    $max = max($max, (int) $m[1]);
                    continue;
                }

                if (preg_match('#^QUO/\d{4}/\d{2}/(\d+)$#', $candidate, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
        }

        return $max + 1;
    }

    /**
     * Ambil Sales Code langsung dari DB (hindari cache relasi yang stale).
     */
    public function resolveSalesCode(?User $user = null): string
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return '';
        }

        $code = \App\Models\UserProfile::query()
            ->where('user_id', $user->id)
            ->value('sales_code');

        return strtoupper(trim((string) ($code ?? '')));
    }

    /**
     * Prioritas kode sales untuk nomor QO:
     * 1) Sales yang di-assign pada opportunity
     * 2) User yang sedang login
     *
     * @return array{code: string, user: ?User}
     */
    public function resolveSalesCodeContext(?string $opportunityId = null, ?User $fallbackUser = null): array
    {
        $fallbackUser = $fallbackUser ?? auth()->user();

        if ($opportunityId) {
            $assignedUserId = \App\Models\Espo\Opportunity::query()
                ->whereKey($opportunityId)
                ->value('assigned_user_id');

            if ($assignedUserId) {
                $assigned = User::query()->whereKey($assignedUserId)->first();
                if ($assigned) {
                    $code = $this->resolveSalesCode($assigned);
                    if ($code !== '') {
                        return ['code' => $code, 'user' => $assigned];
                    }

                    // Assigned ada tapi belum punya kode — tetap laporkan user itu.
                    return ['code' => '', 'user' => $assigned];
                }
            }
        }

        return [
            'code' => $this->resolveSalesCode($fallbackUser),
            'user' => $fallbackUser,
        ];
    }

    public function salesCodeOwnerLabel(?User $user = null): string
    {
        $user = $user ?? auth()->user();

        return $user?->display_name ?: $user?->user_name ?: 'user ini';
    }

    public function romanMonth(int $month): string
    {
        return [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ][$month] ?? 'I';
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
        $jobPosition = trim((string) (optional($creator?->profile)->job_position ?? ''));

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
            'terms' => $this->formatRichText((string) $quotation->terms),
            'sales_name' => (string) optional($creator)->display_name,
            'sales_job_position' => $jobPosition,
            'sales_title' => $jobPosition !== '' ? $jobPosition : (string) (optional($creator)->title ?: 'Account Manager'),
            'sales_signature' => $this->renderSalesSignature($creator, $template),
            'revision' => (string) $quotation->revision,
            'items_table' => $this->renderItemsTable($quotation),
            'items_rows' => $this->renderItemsRows($quotation),
            'items_table_idr' => $this->renderItemsTableIndo($quotation),
            'items_rows_idr' => $this->renderItemsRowsIndo($quotation),
            'items_table_diskon_item' => $this->renderItemsTableDiskonItem($quotation),
            'items_rows_diskon_item' => $this->renderItemsRowsDiskonItem($quotation),
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
        $key = $this->companyKeyForTemplate($template);

        return config("crm.quotation_companies.{$key}", config('crm.quotation_companies.agc', []));
    }

    public function companyKeyForTemplate(?QuotationTemplate $template): string
    {
        $map = config('crm.quotation_company_map', []);
        $key = $map[$template?->category ?? ''] ?? null;

        if (! $key) {
            $key = match ($template?->code) {
                'agc-indo' => 'agc',
                'eps-indo' => 'eps',
                'psi-indo' => 'psi',
                default => 'agc',
            };
        }

        return \App\Models\UserProfile::resolveCompanyKey($key);
    }

    /**
     * Teks plain → escape + nl2br; konten HTML (Summernote) → dipakai apa adanya.
     */
    protected function formatRichText(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/<[^>]+>/', $trimmed)) {
            return $trimmed;
        }

        return nl2br(e($trimmed));
    }

    protected function renderSalesSignature(?User $user, ?QuotationTemplate $template = null): string
    {
        if (! $user) {
            return '';
        }

        $companyKey = $this->companyKeyForTemplate($template);
        $path = $user->signatureAbsolutePath($companyKey);
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
                .($item->description ? '<br><small ">'.nl2br(e($item->description)).'</small>' : '')
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
                .'<td style="'.$this->cellStyle().'"> <strong>'.e($item->name).'</strong>'
                .($item->description ? '<span style="display:block; height:4px;"></span><small">'.nl2br(e($item->description)).'</small>' : '')
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
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;margin:12px 0; line-height: 1;">'
            .'<thead><tr>'
            .'<th style="'.$this->cellStyle('center', true).'width:32px;">No.</th>'
            .'<th style="'.$this->cellStyle('center', true).'">Spesifikasi</th>'
            .'<th style="'.$this->cellStyle('center', true).'width:72px;">Qty</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Harga</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Total Harga</th>'
            .'</tr></thead><tbody>'
            .$this->renderItemsRowsIndo($quotation)
            .$this->renderItemsTableIndoSummary($quotation)
            .'</tbody></table>';
    }

    protected function renderItemsRowsDiskonItem(Quotation $quotation): string
    {
        $quotation->loadMissing(['items', 'opportunity']);
        $oppProducts = $quotation->opportunity?->products?->values() ?? collect();

        $rows = '';
        $no = 1;

        foreach ($quotation->items as $index => $item) {
            $unitLabel = trim((string) $item->unit) ?: 'Unit';
            $qty = (float) $item->quantity;

            // Prefer harga dari opportunity agar Harga Exclude = harga jual list,
            // bukan harga setelah diskon (sering tersimpan salah di item QO lama).
            $opp = $oppProducts->get($index);
            if (! $opp || ($opp['name'] ?? '') !== $item->name) {
                $opp = $oppProducts->firstWhere('name', $item->name);
            }

            if ($opp) {
                $hargaExclude = (float) ($opp['sell_exclude'] ?? 0);
                $disc = (float) ($opp['discount_exclude'] ?? 0);
                $hargaSetelahDiskon = $disc > 0
                    ? $disc
                    : (float) ($opp['effective_sell_exclude'] ?? $hargaExclude);
            } else {
                $hargaExclude = $item->listSellExclude();
                $hargaSetelahDiskon = $item->afterDiscountExclude();
            }

            // Jika QO menyimpan list = setelah diskon, tetap tampilkan unit_price sebagai setelah diskon.
            if ($hargaExclude <= 0) {
                $hargaExclude = $item->listSellExclude();
            }
            if ($hargaSetelahDiskon <= 0) {
                $hargaSetelahDiskon = $item->afterDiscountExclude();
            }

            $total = round($qty * $hargaSetelahDiskon, 2);

            $rows .= '<tr>'
                .'<td style="'.$this->cellStyle('center').'">'.$no++.'</td>'
                .'<td style="'.$this->cellStyle().'"> <strong>'.e($item->name).'</strong>'
                .($item->description ? '<span style="display:block; height:4px;"></span><small>'.nl2br(e($item->description)).'</small>' : '')
                .'</td>'
                .'<td style="'.$this->cellStyle('center').'">'
                .rtrim(rtrim(number_format($qty, 2), '0'), '.').' '.e($unitLabel).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($hargaExclude, $quotation->currency).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($hargaSetelahDiskon, $quotation->currency).'</td>'
                .'<td style="'.$this->cellStyle('right').'">'.money($total, $quotation->currency).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="'.$this->cellStyle('center').'color:#999;">Belum ada item.</td></tr>';
        }

        return $rows;
    }

    protected function renderItemsTableDiskonItem(Quotation $quotation): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;margin:12px 0; line-height: 1;">'
            .'<thead><tr>'
            .'<th style="'.$this->cellStyle('center', true).'width:32px;">No.</th>'
            .'<th style="'.$this->cellStyle('center', true).'">Spesifikasi</th>'
            .'<th style="'.$this->cellStyle('center', true).'width:72px;">Qty</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Harga</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:130px;">Harga Setelah Diskon</th>'
            .'<th style="'.$this->cellStyle('right', true).'width:120px;">Total Harga</th>'
            .'</tr></thead><tbody>'
            .$this->renderItemsRowsDiskonItem($quotation)
            .$this->renderItemsTableSummary($quotation, 4)
            .'</tbody></table>';
    }
}
