<?php

namespace App\Services;

use App\Models\Espo\Opportunity;
use App\Models\OpportunityLog;
use App\Models\User;
use App\Support\CustomerTop;
use App\Support\OpportunityProductPricing;

class OpportunityLogService
{
    /**
     * Field yang dicatat di snapshot & diff (bukan kolom teknis / array produk).
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'name' => 'Nama',
        'company' => 'Perusahaan',
        'stage' => 'Stage',
        'type' => 'Tipe',
        'amount' => 'Amount',
        'amount_currency' => 'Mata uang',
        'crm_top' => 'TOP',
        'close_date' => 'Close date',
        'probability' => 'Probability',
        'lead_source' => 'Lead source',
        'description' => 'Deskripsi',
        'assigned_user_name' => 'Sales assign',
        'account_name' => 'Customer',
        'contact_name' => 'Kontak',
        'crm_lost_reason' => 'Alasan Closed Lost',
        'crm_has_discount' => 'Pakai diskon tambahan',
        'crm_discount_amount' => 'Nominal diskon',
        'crm_discount_status' => 'Status diskon',
        'crm_discount_note' => 'Catatan diskon',
        'crm_margin_status' => 'Status margin',
        'crm_margin_percent' => 'Margin %',
        'crm_margin_nominal' => 'Margin nominal',
        'crm_margin_note' => 'Catatan margin',
        'crm_has_shipping_charge' => 'Pakai ongkir jual',
        'crm_shipping_sell' => 'Ongkir jual',
        'crm_shipping_cost' => 'Ongkir modal',
        'crm_won_margin' => 'Won margin',
        'crm_sales_order_no' => 'No. Sales Order',
    ];

    /**
     * @var list<string>
     */
    protected const MONEY_FIELDS = [
        'amount',
        'crm_discount_amount',
        'crm_margin_nominal',
        'crm_shipping_sell',
        'crm_shipping_cost',
        'crm_won_margin',
        'sell_exclude',
        'cost_exclude',
        'discount_exclude',
        'shipping_exclude',
    ];

    /**
     * @var array<string, string>
     */
    protected const PRODUCT_FIELD_LABELS = [
        'name' => 'Nama',
        'quantity' => 'Qty',
        'sell_exclude' => 'Harga jual',
        'cost_exclude' => 'Harga modal',
        'discount_exclude' => 'Diskon item',
        'shipping_exclude' => 'Ongkir item',
        'vendor' => 'Vendor',
        'brand' => 'Brand',
        'sku' => 'SKU',
        'category' => 'Kategori',
        'tax_category' => 'Pajak',
        'item_kind' => 'Jenis',
        'royalty_type' => 'Royalty',
    ];

    /**
     * @var list<string>
     */
    protected const ALWAYS_RECORD = [
        OpportunityLog::ACTION_CREATED,
        OpportunityLog::ACTION_DELETED,
        OpportunityLog::ACTION_DISCOUNT_APPROVED,
        OpportunityLog::ACTION_DISCOUNT_REJECTED,
        OpportunityLog::ACTION_DISCOUNT_REVERTED,
        OpportunityLog::ACTION_MARGIN_APPROVED,
        OpportunityLog::ACTION_MARGIN_REJECTED,
        OpportunityLog::ACTION_NOTE_ADDED,
        OpportunityLog::ACTION_NOTE_DELETED,
        OpportunityLog::ACTION_DOCUMENT_UPLOADED,
        OpportunityLog::ACTION_DOCUMENT_DELETED,
        OpportunityLog::ACTION_ENTERTAINMENT_ADDED,
        OpportunityLog::ACTION_ENTERTAINMENT_COMPLETED,
        OpportunityLog::ACTION_ENTERTAINMENT_DELETED,
        OpportunityLog::ACTION_SALES_ORDER_CREATED,
        OpportunityLog::ACTION_SALES_ORDER_UPDATED,
        OpportunityLog::ACTION_SALES_ORDER_ARCHIVED,
        OpportunityLog::ACTION_SALES_ORDER_CANCEL_REQUESTED,
        OpportunityLog::ACTION_SALES_ORDER_CANCEL_APPROVED,
        OpportunityLog::ACTION_SALES_ORDER_CANCEL_REJECTED,
    ];

    /**
     * Snapshot ringkas untuk audit (field + produk).
     *
     * @return array<string, mixed>
     */
    public function capture(Opportunity $opportunity): array
    {
        return $opportunity->toLogSnapshot();
    }

    /**
     * Catat aksi ke log opportunity.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $meta  summary, note, force, actor
     */
    public function record(
        Opportunity $opportunity,
        string $action,
        ?array $before = null,
        array $meta = []
    ): ?OpportunityLog {
        $after = $this->capture($opportunity);
        $changes = $before === null
            ? ['fields' => [], 'products' => ['added' => [], 'removed' => [], 'changed' => []]]
            : $this->diff($before, $after);

        $force = (bool) ($meta['force'] ?? false)
            || in_array($action, self::ALWAYS_RECORD, true);

        if (! $force && ! $this->hasChanges($changes)) {
            return null;
        }

        if ($action === OpportunityLog::ACTION_UPDATED) {
            $action = $this->refineUpdatedAction($changes);
        }

        /** @var User|null $actor */
        $actor = $meta['actor'] ?? auth()->user();

        $note = isset($meta['note']) ? trim((string) $meta['note']) : '';
        if ($note !== '') {
            $changes['note'] = $note;
        }

        $summary = trim((string) ($meta['summary'] ?? ''));
        if ($summary === '') {
            $summary = $this->buildSummary($action, $changes, $after);
        }

        return OpportunityLog::query()->create([
            'opportunity_id' => $opportunity->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->display_name,
            'snapshot' => $after,
            'changes' => $changes,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{fields: list<array<string, mixed>>, products: array{added: list<mixed>, removed: list<mixed>, changed: list<mixed>}}  $changes
     */
    public function hasChanges(array $changes): bool
    {
        if (($changes['fields'] ?? []) !== []) {
            return true;
        }

        $products = $changes['products'] ?? [];

        return ($products['added'] ?? []) !== []
            || ($products['removed'] ?? []) !== []
            || ($products['changed'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{fields: list<array<string, mixed>>, products: array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, changed: list<array<string, mixed>>}}
     */
    public function diff(array $before, array $after): array
    {
        $fields = [];
        foreach (self::FIELD_LABELS as $key => $label) {
            $from = $before[$key] ?? null;
            $to = $after[$key] ?? null;
            if ($this->valuesEqual($key, $from, $to)) {
                continue;
            }

            $fields[] = [
                'field' => $key,
                'label' => $label,
                'from' => $from,
                'to' => $to,
                'from_display' => $this->displayValue($key, $from, $before['amount_currency'] ?? 'IDR'),
                'to_display' => $this->displayValue($key, $to, $after['amount_currency'] ?? 'IDR'),
            ];
        }

        return [
            'fields' => $fields,
            'products' => $this->diffProducts(
                is_array($before['products'] ?? null) ? $before['products'] : [],
                is_array($after['products'] ?? null) ? $after['products'] : [],
                $after['amount_currency'] ?? ($before['amount_currency'] ?? 'IDR')
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, changed: list<array<string, mixed>>}
     */
    protected function diffProducts(array $before, array $after, string $currency): array
    {
        $beforeRows = array_values($before);
        $afterRows = array_values($after);
        $usedAfter = [];
        $changed = [];
        $removed = [];

        foreach ($beforeRows as $old) {
            $matchIndex = $this->matchProductIndex($old, $afterRows, $usedAfter);
            if ($matchIndex === null) {
                $removed[] = $this->productSummary($old);
                continue;
            }

            $usedAfter[$matchIndex] = true;
            $new = $afterRows[$matchIndex];
            $rowChanges = [];
            foreach (self::PRODUCT_FIELD_LABELS as $key => $label) {
                $from = $old[$key] ?? null;
                $to = $new[$key] ?? null;
                if ($this->valuesEqual($key, $from, $to)) {
                    continue;
                }
                $rowChanges[] = [
                    'field' => $key,
                    'label' => $label,
                    'from' => $from,
                    'to' => $to,
                    'from_display' => $this->displayValue($key, $from, $currency),
                    'to_display' => $this->displayValue($key, $to, $currency),
                ];
            }

            if ($rowChanges !== []) {
                $changed[] = [
                    'name' => (string) ($new['name'] ?? $old['name'] ?? 'Item'),
                    'changes' => $rowChanges,
                ];
            }
        }

        $added = [];
        foreach ($afterRows as $i => $new) {
            if (! empty($usedAfter[$i])) {
                continue;
            }
            $added[] = $this->productSummary($new);
        }

        return compact('added', 'removed', 'changed');
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  list<array<string, mixed>>  $afterRows
     * @param  array<int, bool>  $usedAfter
     */
    protected function matchProductIndex(array $old, array $afterRows, array $usedAfter): ?int
    {
        $oldName = mb_strtolower(trim((string) ($old['name'] ?? '')));
        $oldSku = mb_strtolower(trim((string) ($old['sku'] ?? '')));

        foreach ($afterRows as $i => $new) {
            if (! empty($usedAfter[$i])) {
                continue;
            }
            $newSku = mb_strtolower(trim((string) ($new['sku'] ?? '')));
            if ($oldSku !== '' && $newSku !== '' && $oldSku === $newSku) {
                return $i;
            }
        }

        foreach ($afterRows as $i => $new) {
            if (! empty($usedAfter[$i])) {
                continue;
            }
            $newName = mb_strtolower(trim((string) ($new['name'] ?? '')));
            if ($oldName !== '' && $oldName === $newName) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function productSummary(array $row): array
    {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'quantity' => $row['quantity'] ?? null,
            'sell_exclude' => $row['sell_exclude'] ?? null,
            'cost_exclude' => $row['cost_exclude'] ?? null,
            'vendor' => (string) ($row['vendor'] ?? ''),
        ];
    }

    /**
     * @param  array{fields: list<array<string, mixed>>, products: array{added: list<mixed>, removed: list<mixed>, changed: list<mixed>}}  $changes
     */
    protected function refineUpdatedAction(array $changes): string
    {
        $fieldKeys = collect($changes['fields'] ?? [])->pluck('field')->all();
        $hasProducts = ($changes['products']['added'] ?? []) !== []
            || ($changes['products']['removed'] ?? []) !== []
            || ($changes['products']['changed'] ?? []) !== [];

        if ($fieldKeys === ['stage'] || $fieldKeys === ['stage', 'probability'] || $fieldKeys === ['probability', 'stage']) {
            return OpportunityLog::ACTION_STAGE_CHANGED;
        }

        if ($hasProducts && $fieldKeys === []) {
            return OpportunityLog::ACTION_PRODUCTS_UPDATED;
        }

        $amountOnly = array_diff($fieldKeys, ['amount', 'crm_won_margin', 'crm_margin_percent', 'crm_margin_nominal', 'crm_margin_status']) === [];
        if ($hasProducts && $amountOnly) {
            return OpportunityLog::ACTION_PRODUCTS_UPDATED;
        }

        return OpportunityLog::ACTION_UPDATED;
    }

    /**
     * @param  array{fields?: list<array<string, mixed>>, products?: array<string, mixed>}  $changes
     * @param  array<string, mixed>  $snapshot
     */
    protected function buildSummary(string $action, array $changes, array $snapshot): string
    {
        $products = $changes['products'] ?? [];
        $added = count($products['added'] ?? []);
        $removed = count($products['removed'] ?? []);
        $changed = count($products['changed'] ?? []);
        $name = (string) ($snapshot['name'] ?? 'Opportunity');

        $productBits = [];
        if ($added > 0) {
            $productBits[] = 'menambah '.$added.' barang';
        }
        if ($removed > 0) {
            $productBits[] = 'menghapus '.$removed.' barang';
        }
        if ($changed > 0) {
            $productBits[] = 'mengubah '.$changed.' barang';
        }
        $productText = $productBits !== [] ? implode(', ', $productBits) : '';

        $fieldBits = [];
        foreach (array_slice($changes['fields'] ?? [], 0, 4) as $field) {
            $label = (string) ($field['label'] ?? $field['field'] ?? '');
            $from = (string) ($field['from_display'] ?? '—');
            $to = (string) ($field['to_display'] ?? '—');
            $fieldBits[] = $label.': '.$from.' → '.$to;
        }
        $fieldText = $fieldBits !== [] ? implode('; ', $fieldBits) : '';

        return match ($action) {
            OpportunityLog::ACTION_CREATED => $this->joinSummary(
                'Opportunity dibuat'.(count($snapshot['products'] ?? []) ? ' dengan '.count($snapshot['products']).' barang' : ''),
                $name
            ),
            OpportunityLog::ACTION_DELETED => 'Opportunity dihapus',
            OpportunityLog::ACTION_STAGE_CHANGED => $fieldText !== '' ? $fieldText : 'Stage diubah',
            OpportunityLog::ACTION_PRODUCTS_UPDATED => $productText !== '' ? ucfirst($productText) : 'Daftar produk / harga diubah',
            OpportunityLog::ACTION_DISCOUNT_APPROVED => 'Menyetujui diskon tambahan',
            OpportunityLog::ACTION_DISCOUNT_REJECTED => 'Menolak diskon tambahan',
            OpportunityLog::ACTION_DISCOUNT_REVERTED => 'Mengembalikan diskon ke menunggu approval',
            OpportunityLog::ACTION_MARGIN_APPROVED => 'Menyetujui margin',
            OpportunityLog::ACTION_MARGIN_REJECTED => 'Menolak margin',
            OpportunityLog::ACTION_QUOTATION_SYNCED => $productText !== ''
                ? 'Produk disinkronkan dari Quotation ('.$productText.')'
                : 'Produk disinkronkan dari Quotation',
            OpportunityLog::ACTION_PO_SYNCED => $productText !== ''
                ? 'Modal/vendor di-update dari PO ('.$productText.')'
                : 'Modal/vendor di-update dari PO',
            OpportunityLog::ACTION_SHIPPING_UPDATED => $fieldText !== '' ? $fieldText : 'Ongkir diubah',
            OpportunityLog::ACTION_UPDATED => $this->joinSummary($fieldText, $productText) ?: 'Menyimpan perubahan',
            default => OpportunityLog::ACTIONS[$action] ?? 'Perubahan tersimpan',
        };
    }

    protected function joinSummary(string ...$parts): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts)));

        return implode(' · ', $parts);
    }

    protected function valuesEqual(string $key, mixed $from, mixed $to): bool
    {
        if (in_array($key, self::MONEY_FIELDS, true) || in_array($key, ['quantity', 'probability', 'crm_margin_percent'], true)) {
            return round((float) $from, 4) === round((float) $to, 4);
        }

        if (in_array($key, ['crm_has_discount', 'crm_has_shipping_charge'], true)) {
            return (bool) $from === (bool) $to;
        }

        $from = is_string($from) ? trim($from) : $from;
        $to = is_string($to) ? trim($to) : $to;

        if ($from === '' || $from === null) {
            $from = null;
        }
        if ($to === '' || $to === null) {
            $to = null;
        }

        return $from == $to; // loose: "10" vs 10
    }

    protected function displayValue(string $key, mixed $value, string $currency = 'IDR'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($key, ['crm_has_discount', 'crm_has_shipping_charge'], true)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if ($key === 'crm_top') {
            return CustomerTop::label((string) $value);
        }

        if ($key === 'crm_discount_status') {
            return match ((string) $value) {
                Opportunity::DISCOUNT_PENDING => 'Menunggu Approval',
                Opportunity::DISCOUNT_APPROVED => 'Disetujui',
                Opportunity::DISCOUNT_REJECTED => 'Ditolak',
                default => (string) $value ?: '—',
            };
        }

        if ($key === 'crm_margin_status') {
            return match ((string) $value) {
                Opportunity::MARGIN_PENDING => 'Menunggu approval',
                Opportunity::MARGIN_APPROVED => 'Disetujui',
                Opportunity::MARGIN_REJECTED => 'Ditolak',
                default => (string) $value ?: '—',
            };
        }

        if ($key === 'tax_category') {
            return OpportunityProductPricing::taxCategoryLabel((string) $value);
        }

        if ($key === 'probability') {
            return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
        }

        if ($key === 'crm_margin_percent') {
            return number_format((float) $value, 2, ',', '.').'%';
        }

        if ($key === 'quantity') {
            return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        }

        if (in_array($key, self::MONEY_FIELDS, true) && function_exists('money')) {
            return money($value, $currency ?: 'IDR');
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        $text = is_scalar($value) ? (string) $value : json_encode($value);

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'…' : $text;
    }
}
