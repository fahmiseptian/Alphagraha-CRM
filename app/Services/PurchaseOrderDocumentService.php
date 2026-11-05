<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderTemplate;
use App\Models\UserProfile;
use App\Support\OpportunityProductPricing;

class PurchaseOrderDocumentService
{
    /**
     * @return array<string, string>
     */
    public function placeholders(Opportunity $opportunity, PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing([
            'items.vendorQuotes',
            'vendor.pics',
            'creator',
            'approver',
            'salesOrder',
        ]);

        $companyKey = UserProfile::resolveCompanyKey($opportunity->company);
        $profile = $this->companyProfile($companyKey);
        $addressLines = array_values(array_filter(
            $profile['lines'],
            fn ($line) => ! preg_match('/^(phones?|telp?|fax|email)\b/i', $line)
        ));

        $poDate = $purchaseOrder->created_at;
        $expected = $purchaseOrder->salesOrder?->required_delivery ?: $poDate;
        $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '';

        $lines = $this->lines($opportunity, $purchaseOrder);
        $subTotal = 0.0;
        $ppnTotal = 0.0;
        $notes = [];
        foreach ($lines as $line) {
            $subTotal += $line['amount_raw'];
            $ppnTotal += $line['ppn_raw'];
            if ($line['note'] !== '') {
                $notes[] = $line['note'];
            }
        }
        $ppnPercent = OpportunityProductPricing::ppnPercent();

        $vendorLines = [e($purchaseOrder->displayVendorName())];
        foreach ($purchaseOrder->vendor?->pics ?? [] as $pic) {
            $phone = trim((string) ($pic->phone ?? ''));
            if ($phone !== '') {
                $vendorLines[] = 'UP : '.e($phone);
            }
        }

        $preparedBy = trim((string) (optional($purchaseOrder->creator)->display_name ?: ''));
        $approvedBy = $purchaseOrder->isApproved()
            ? trim((string) (optional($purchaseOrder->approver)->display_name ?: ''))
            : '';

        return [
            'company_letterhead' => $this->letterheadHtml($companyKey, $profile),
            'company_legal_name' => e($profile['name']),
            'company_address' => e(implode(', ', $addressLines)),
            'po_number' => e((string) $purchaseOrder->number),
            'po_date' => e($fmtDate($poDate)),
            'po_time' => e((string) ($poDate?->format('H:i:s') ?? '')),
            'expected_date' => e($fmtDate($expected)),
            'ship_via' => 'Dikirim',
            'vendor_name' => e($purchaseOrder->displayVendorName()),
            'vendor_detail' => implode('<br>', $vendorLines),
            'ship_to' => e($profile['name']).'<br>'.implode('<br>', array_map('e', $addressLines)),
            'currency' => 'Rp.',
            'items_rows' => $this->itemsRowsHtml($lines),
            'items_table' => $this->itemsTableHtml($lines),
            'subtotal' => e($this->fmt($subTotal)),
            'discount' => '',
            'ppn_percent' => e($this->percentLabel($ppnPercent)),
            'ppn_amount' => $ppnTotal > 0 ? e($this->fmt($ppnTotal)) : '',
            'total' => e($this->fmt($subTotal + $ppnTotal)),
            'notes' => implode('<br>', array_map('e', $notes)),
            'prepared_by' => e($preparedBy !== '' ? mb_strtoupper($preparedBy) : ''),
            'approved_by' => e($approvedBy !== '' ? mb_strtoupper($approvedBy) : ''),
            'footer_note' => 'PO ini harus dilampirkan pada waktu pengiriman barang dan penagihan',
        ];
    }

    public function render(Opportunity $opportunity, PurchaseOrder $purchaseOrder, ?PurchaseOrderTemplate $template = null): string
    {
        $template ??= $this->resolveTemplate($opportunity);
        $html = $template?->body_html ?: $this->defaultBodyHtml();
        $data = $this->placeholders($opportunity, $purchaseOrder);

        foreach ($data as $key => $value) {
            $quoted = preg_quote($key, '/');
            $html = preg_replace_callback(
                '/@?\{\{\s*'.$quoted.'\s*\}\}/',
                static fn () => $value ?? '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    public function resolveTemplate(Opportunity $opportunity): ?PurchaseOrderTemplate
    {
        $company = $opportunity->company;

        $template = PurchaseOrderTemplate::query()
            ->where('is_active', true)
            ->forCompany($company)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if ($template) {
            return $template;
        }

        return PurchaseOrderTemplate::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }

    public function defaultBodyHtml(): string
    {
        return <<<'HTML'
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 68%; vertical-align: top;">{{ company_letterhead }}</td>
            <td style="vertical-align: top; text-align: right;">
                <div style="font-size: 28px; font-weight: 700; line-height: 1;">Purchase Order</div>
            </td>
        </tr>
    </table>
    <table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
        <tr>
            <td></td>
            <td style="width: 280px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">PO Date</td>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">PO Number</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">{{ po_date }}</td>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">{{ po_number }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">Expected Date</td>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">Ship Via</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">{{ expected_date }}</td>
                        <td style="border: 1px solid #000; padding: 3px 8px; text-align: center;">{{ ship_via }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <td style="border: 1px solid #000; vertical-align: top; padding: 6px 8px; width: 50%; height: 92px;">
                <strong>Vendor</strong><br>{{ vendor_detail }}
            </td>
            <td style="border: 1px solid #000; vertical-align: top; padding: 6px 8px; width: 50%;">
                <strong>Ship To</strong><br>{{ ship_to }}
            </td>
        </tr>
    </table>
    <div style="text-align: right; padding: 8px 2px 4px;">{{ po_time }}</div>
    <table style="width: auto; margin-left: auto; border-collapse: collapse;">
        <tr>
            <td style="border: 1px solid #000; padding: 3px 10px; text-align: center;">Currency</td>
            <td style="border: 1px solid #000; padding: 3px 10px; text-align: center;">{{ currency }}</td>
        </tr>
    </table>
    <div style="margin-top: 8px;">{{ items_table }}</div>
    <table style="width: 100%; border-collapse: collapse; margin-top: 18px;">
        <tr>
            <td style="width: 25%; text-align: center;">Prepared by :</td>
            <td style="width: 25%; text-align: center;">Checked by :</td>
            <td style="width: 25%; text-align: center;">Approved by :</td>
            <td style="width: 25%; text-align: center;">Approved by :</td>
        </tr>
        <tr>
            <td style="text-align: center; padding-top: 54px; border-top: 1px solid #000; font-weight: 700;">{{ prepared_by }}</td>
            <td style="text-align: center; padding-top: 54px; border-top: 1px solid #000;"></td>
            <td style="text-align: center; padding-top: 54px; border-top: 1px solid #000; font-weight: 700;">{{ approved_by }}</td>
            <td style="text-align: center; padding-top: 54px; border-top: 1px solid #000;"></td>
        </tr>
    </table>
    <p style="margin-top: 28px;">{{ footer_note }}</p>
</div>
HTML;
    }

    /**
     * @param  array{name: string, lines: list<string>}  $profile
     */
    protected function letterheadHtml(string $companyKey, array $profile): string
    {
        $kopPath = null;
        foreach (['jpeg', 'jpg', 'png'] as $ext) {
            $candidate = public_path("images/kop-{$companyKey}.{$ext}");
            if (is_file($candidate)) {
                $kopPath = $candidate;
                break;
            }
        }

        if ($kopPath) {
            $ext = strtolower(pathinfo($kopPath, PATHINFO_EXTENSION));
            $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
            $src = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($kopPath));

            return '<img src="'.$src.'" alt="'.e($profile['name']).'" style="width: 100%; height: auto; max-height: 92px; object-fit: contain; object-position: left center;">';
        }

        return '<strong>'.e($profile['name']).'</strong><br>'.implode('<br>', array_map('e', $profile['lines']));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function itemsTableHtml(array $lines): string
    {
        $subTotal = 0.0;
        $ppnTotal = 0.0;
        $notes = [];
        foreach ($lines as $line) {
            $subTotal += (float) $line['amount_raw'];
            $ppnTotal += (float) $line['ppn_raw'];
            if (($line['note'] ?? '') !== '') {
                $notes[] = (string) $line['note'];
            }
        }

        $cell = 'border: 1px solid #000; padding: 4px 6px; vertical-align: middle;';
        $ppnLabel = e($this->percentLabel(OpportunityProductPricing::ppnPercent()));
        $noteHtml = implode('<br>', array_map(fn ($note) => e($note), $notes));

        $footer = '<tr>'
            .'<td colspan="5" rowspan="4" style="'.$cell.' vertical-align: top; height: 92px;"><strong>Description</strong><br>'.$noteHtml.'</td>'
            .'<td style="'.$cell.'">Sub Total :</td>'
            .'<td style="'.$cell.' text-align: right; white-space: nowrap;">'.e($this->fmt($subTotal)).'</td>'
            .'</tr>'
            .'<tr>'
            .'<td style="'.$cell.'">Discount :</td>'
            .'<td style="'.$cell.'"></td>'
            .'</tr>'
            .'<tr>'
            .'<td style="'.$cell.'">PPN '.$ppnLabel.'% :</td>'
            .'<td style="'.$cell.' text-align: right; white-space: nowrap;">'.($ppnTotal > 0 ? e($this->fmt($ppnTotal)) : '').'</td>'
            .'</tr>'
            .'<tr>'
            .'<td style="'.$cell.'">Total Order :</td>'
            .'<td style="'.$cell.' text-align: right; white-space: nowrap;">'.e($this->fmt($subTotal + $ppnTotal)).'</td>'
            .'</tr>';

        return '<table style="width: 100%; border-collapse: collapse; table-layout: fixed;">'
            .'<colgroup>'
            .'<col style="width: 6%;">'
            .'<col style="width: 16%;">'
            .'<col>'
            .'<col style="width: 8%;">'
            .'<col style="width: 8%;">'
            .'<col style="width: 16%;">'
            .'<col style="width: 16%;">'
            .'</colgroup>'
            .'<thead><tr>'
            .'<th style="'.$cell.' text-align: center;">No</th>'
            .'<th style="'.$cell.' text-align: center;">Item</th>'
            .'<th style="'.$cell.' text-align: center;">Description</th>'
            .'<th style="'.$cell.' text-align: center;">Qty</th>'
            .'<th style="'.$cell.' text-align: center;">Unit</th>'
            .'<th style="'.$cell.' text-align: center;">Unit Price</th>'
            .'<th style="'.$cell.' text-align: center;">Amount</th>'
            .'</tr></thead><tbody>'
            .$this->itemsRowsHtml($lines)
            .$footer
            .'</tbody></table>';
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function itemsRowsHtml(array $lines): string
    {
        if ($lines === []) {
            return '<tr><td colspan="7" style="border: 1px solid #000; padding: 16px; text-align: center;">Tidak ada item</td></tr>';
        }

        $cell = 'border: 1px solid #000; padding: 4px 6px; vertical-align: top;';
        $html = '';
        foreach ($lines as $i => $line) {
            $html .= '<tr>'
                .'<td style="'.$cell.' text-align: center;">'.($i + 1).'</td>'
                .'<td style="'.$cell.' word-break: break-all;">'.e($line['sku']).'</td>'
                .'<td style="'.$cell.' white-space: pre-line;">'.e($line['description']).'</td>'
                .'<td style="'.$cell.' text-align: center;">'.e($line['qty']).'</td>'
                .'<td style="'.$cell.' text-align: center;">UNIT</td>'
                .'<td style="'.$cell.' text-align: right; white-space: nowrap;">'.e($line['unit_price']).'</td>'
                .'<td style="'.$cell.' text-align: right; white-space: nowrap;">'.e($line['amount']).'</td>'
                .'</tr>';
        }

        return $html;
    }

    /**
     * @return list<array{sku: string, description: string, qty: string, unit_price: string, amount: string, amount_raw: float, ppn_raw: float, note: string}>
     */
    protected function lines(Opportunity $opportunity, PurchaseOrder $purchaseOrder): array
    {
        $soItems = is_array($purchaseOrder->salesOrder?->items) ? $purchaseOrder->salesOrder->items : [];
        if ($soItems === [] && is_array($purchaseOrder->salesOrder?->agc_payload['items'] ?? null)) {
            $soItems = $purchaseOrder->salesOrder->agc_payload['items'];
        }
        $skuByName = [];
        foreach ($soItems as $soItem) {
            if (! is_array($soItem)) {
                continue;
            }
            $name = mb_strtolower(trim((string) ($soItem['name'] ?? '')));
            $sku = trim((string) ($soItem['sku'] ?? ''));
            if ($name !== '' && $sku !== '') {
                $skuByName[$name] = $sku;
            }
        }

        $ppnPercent = OpportunityProductPricing::ppnPercent();
        $lines = [];
        foreach ($purchaseOrder->items as $item) {
            $quote = $item->selectedVendorQuote();
            $unit = $quote ? (float) $quote->hargaExclude() : (float) $item->unit_price;
            $qty = (float) $item->quantity;
            $amount = round($qty * $unit, 2);
            $isPkp = $quote !== null
                ? $quote->is_pkp !== false
                : (bool) ($purchaseOrder->vendor?->is_pkp ?? true);
            $lookup = mb_strtolower(trim((string) ($item->opportunity_product_name ?: $item->product_name)));
            $description = trim((string) $item->product_name);
            $extra = trim((string) ($item->description ?: ''));
            if ($extra !== '' && mb_strtolower($extra) !== mb_strtolower($description)) {
                $description = trim($description."\n".$extra);
            }

            $lines[] = [
                'sku' => $skuByName[$lookup] ?? '',
                'description' => $description !== '' ? $description : '—',
                'qty' => $this->fmtQty($qty),
                'unit_price' => $this->fmt($unit),
                'amount' => $this->fmt($amount),
                'amount_raw' => $amount,
                'ppn_raw' => $isPkp ? round($amount * ($ppnPercent / 100), 2) : 0.0,
                'note' => trim((string) ($item->note ?: '')),
            ];
        }

        return $lines;
    }

    /**
     * @return array{name: string, lines: list<string>}
     */
    protected function companyProfile(string $companyKey): array
    {
        $profiles = [
            'agc' => [
                'name' => 'pt. Alpha Graha Computindo',
                'lines' => [
                    'Sudirman Park Office Complex, Blok A12 - A15',
                    'Jalan K.H Mas Mansyur Kav. 35, Jakarta 10220',
                    'Phones : 021 - 5794 3777',
                    'Fax : 021 - 5794 3778',
                ],
            ],
            'eps' => [
                'name' => 'PT. Elite Proxy Sistem',
                'lines' => [
                    'Rukan Blok A/15 Sudirman Park Apartment',
                    'Jl. K.H Mas Mansyur Kav. 35, Jakarta 10220',
                    'Telp : 021 - 5794 3757',
                    'Fax : 021 - 2598 1039',
                ],
            ],
            'psi' => [
                'name' => 'PT. POWER SISTEM INTEGRASI',
                'lines' => [
                    'Ruko Sudirman Park Blok A 12 Lt. 1',
                    'Jl. K.H Mas Mansyur Kav. 35, Jakarta 10220',
                    'Phone : 021 - 5794 3893',
                    'Fax : 021 - 5794 3894',
                ],
            ],
        ];

        return $profiles[$companyKey] ?? $profiles['agc'];
    }

    protected function fmt(float $amount): string
    {
        $amount = round($amount, 2);
        $decimals = abs($amount - round($amount)) < 0.001 ? 0 : 2;

        return number_format($amount, $decimals, ',', '.');
    }

    protected function fmtQty(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
    }

    protected function percentLabel(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');
    }
}
