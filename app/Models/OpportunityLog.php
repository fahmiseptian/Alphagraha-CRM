<?php

namespace App\Models;

use App\Models\Espo\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityLog extends Model
{
    protected $table = 'crm_opportunity_logs';

    public $timestamps = false;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_STAGE_CHANGED = 'stage_changed';

    public const ACTION_PRODUCTS_UPDATED = 'products_updated';

    public const ACTION_DISCOUNT_APPROVED = 'discount_approved';

    public const ACTION_DISCOUNT_REJECTED = 'discount_rejected';

    public const ACTION_DISCOUNT_REVERTED = 'discount_reverted';

    public const ACTION_MARGIN_APPROVED = 'margin_approved';

    public const ACTION_MARGIN_REJECTED = 'margin_rejected';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_NOTE_ADDED = 'note_added';

    public const ACTION_NOTE_DELETED = 'note_deleted';

    public const ACTION_DOCUMENT_UPLOADED = 'document_uploaded';

    public const ACTION_DOCUMENT_DELETED = 'document_deleted';

    public const ACTION_ENTERTAINMENT_ADDED = 'entertainment_added';

    public const ACTION_ENTERTAINMENT_COMPLETED = 'entertainment_completed';

    public const ACTION_ENTERTAINMENT_DELETED = 'entertainment_deleted';

    public const ACTION_QUOTATION_SYNCED = 'quotation_synced';

    public const ACTION_PO_SYNCED = 'po_synced';

    public const ACTION_SHIPPING_UPDATED = 'shipping_updated';

    public const ACTION_SALES_ORDER_CREATED = 'sales_order_created';

    public const ACTION_SALES_ORDER_UPDATED = 'sales_order_updated';

    public const ACTION_SALES_ORDER_ARCHIVED = 'sales_order_archived';

    public const ACTION_SALES_ORDER_CANCEL_REQUESTED = 'sales_order_cancel_requested';

    public const ACTION_SALES_ORDER_CANCEL_APPROVED = 'sales_order_cancel_approved';

    public const ACTION_SALES_ORDER_CANCEL_REJECTED = 'sales_order_cancel_rejected';

    public const ACTIONS = [
        self::ACTION_CREATED => 'Dibuat',
        self::ACTION_UPDATED => 'Diubah',
        self::ACTION_STAGE_CHANGED => 'Stage diubah',
        self::ACTION_PRODUCTS_UPDATED => 'Produk / harga diubah',
        self::ACTION_DISCOUNT_APPROVED => 'Diskon disetujui',
        self::ACTION_DISCOUNT_REJECTED => 'Diskon ditolak',
        self::ACTION_DISCOUNT_REVERTED => 'Diskon dikembalikan',
        self::ACTION_MARGIN_APPROVED => 'Margin disetujui',
        self::ACTION_MARGIN_REJECTED => 'Margin ditolak',
        self::ACTION_DELETED => 'Dihapus',
        self::ACTION_NOTE_ADDED => 'Catatan ditambah',
        self::ACTION_NOTE_DELETED => 'Catatan dihapus',
        self::ACTION_DOCUMENT_UPLOADED => 'Dokumen diunggah',
        self::ACTION_DOCUMENT_DELETED => 'Dokumen dihapus',
        self::ACTION_ENTERTAINMENT_ADDED => 'Entertainment ditambah',
        self::ACTION_ENTERTAINMENT_COMPLETED => 'Entertainment complete',
        self::ACTION_ENTERTAINMENT_DELETED => 'Entertainment dihapus',
        self::ACTION_QUOTATION_SYNCED => 'Sinkron dari Quotation',
        self::ACTION_PO_SYNCED => 'Modal dari PO',
        self::ACTION_SHIPPING_UPDATED => 'Ongkir diubah',
        self::ACTION_SALES_ORDER_CREATED => 'SO dibuat',
        self::ACTION_SALES_ORDER_UPDATED => 'SO diubah',
        self::ACTION_SALES_ORDER_ARCHIVED => 'SO diarsipkan',
        self::ACTION_SALES_ORDER_CANCEL_REQUESTED => 'Request batal SO',
        self::ACTION_SALES_ORDER_CANCEL_APPROVED => 'Batal SO disetujui',
        self::ACTION_SALES_ORDER_CANCEL_REJECTED => 'Batal SO ditolak',
    ];

    protected $fillable = [
        'opportunity_id',
        'action',
        'summary',
        'actor_id',
        'actor_name',
        'snapshot',
        'changes',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace('_', ' ', (string) $this->action));
    }

    public function actionBadgeColor(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED, self::ACTION_DISCOUNT_APPROVED, self::ACTION_MARGIN_APPROVED, self::ACTION_SALES_ORDER_CREATED, self::ACTION_ENTERTAINMENT_COMPLETED, self::ACTION_SALES_ORDER_CANCEL_APPROVED => 'green',
            self::ACTION_UPDATED, self::ACTION_PRODUCTS_UPDATED, self::ACTION_QUOTATION_SYNCED, self::ACTION_PO_SYNCED, self::ACTION_SALES_ORDER_UPDATED => 'blue',
            self::ACTION_STAGE_CHANGED, self::ACTION_SHIPPING_UPDATED, self::ACTION_DISCOUNT_REVERTED, self::ACTION_SALES_ORDER_CANCEL_REQUESTED => 'brand',
            self::ACTION_DISCOUNT_REJECTED, self::ACTION_MARGIN_REJECTED, self::ACTION_DELETED, self::ACTION_SALES_ORDER_ARCHIVED, self::ACTION_SALES_ORDER_CANCEL_REJECTED => 'red',
            self::ACTION_NOTE_ADDED, self::ACTION_DOCUMENT_UPLOADED, self::ACTION_ENTERTAINMENT_ADDED => 'purple',
            self::ACTION_NOTE_DELETED, self::ACTION_DOCUMENT_DELETED, self::ACTION_ENTERTAINMENT_DELETED => 'amber',
            default => 'slate',
        };
    }

    public function actionIcon(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'bi-plus-circle',
            self::ACTION_UPDATED => 'bi-pencil-square',
            self::ACTION_STAGE_CHANGED => 'bi-signpost-split',
            self::ACTION_PRODUCTS_UPDATED => 'bi-box-seam',
            self::ACTION_DISCOUNT_APPROVED, self::ACTION_MARGIN_APPROVED => 'bi-check-circle',
            self::ACTION_DISCOUNT_REJECTED, self::ACTION_MARGIN_REJECTED => 'bi-x-circle',
            self::ACTION_DISCOUNT_REVERTED => 'bi-arrow-counterclockwise',
            self::ACTION_DELETED => 'bi-trash',
            self::ACTION_NOTE_ADDED => 'bi-sticky',
            self::ACTION_NOTE_DELETED => 'bi-sticky',
            self::ACTION_DOCUMENT_UPLOADED, self::ACTION_DOCUMENT_DELETED => 'bi-paperclip',
            self::ACTION_ENTERTAINMENT_ADDED, self::ACTION_ENTERTAINMENT_COMPLETED, self::ACTION_ENTERTAINMENT_DELETED => 'bi-wallet2',
            self::ACTION_QUOTATION_SYNCED => 'bi-file-earmark-text',
            self::ACTION_PO_SYNCED => 'bi-truck',
            self::ACTION_SHIPPING_UPDATED => 'bi-truck',
            self::ACTION_SALES_ORDER_CREATED, self::ACTION_SALES_ORDER_UPDATED, self::ACTION_SALES_ORDER_ARCHIVED, self::ACTION_SALES_ORDER_CANCEL_REQUESTED, self::ACTION_SALES_ORDER_CANCEL_APPROVED, self::ACTION_SALES_ORDER_CANCEL_REJECTED => 'bi-receipt',
            default => 'bi-clock-history',
        };
    }

    public function snapshotValue(string $key, mixed $default = null): mixed
    {
        $snap = is_array($this->snapshot) ? $this->snapshot : [];

        return data_get($snap, $key, $default);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldChanges(): array
    {
        $changes = is_array($this->changes) ? $this->changes : [];
        $fields = $changes['fields'] ?? [];

        return is_array($fields) ? array_values($fields) : [];
    }

    /**
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, changed: list<array<string, mixed>>}
     */
    public function productChanges(): array
    {
        $changes = is_array($this->changes) ? $this->changes : [];
        $products = is_array($changes['products'] ?? null) ? $changes['products'] : [];

        return [
            'added' => array_values(is_array($products['added'] ?? null) ? $products['added'] : []),
            'removed' => array_values(is_array($products['removed'] ?? null) ? $products['removed'] : []),
            'changed' => array_values(is_array($products['changed'] ?? null) ? $products['changed'] : []),
        ];
    }

    public function hasProductChanges(): bool
    {
        $p = $this->productChanges();

        return $p['added'] !== [] || $p['removed'] !== [] || $p['changed'] !== [];
    }
}
