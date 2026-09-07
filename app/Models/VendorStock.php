<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorStock extends Model
{
    public const STATUS_READY = 'ready';

    public const STATUS_INDENT = 'indent';

    public const STATUS_KOSONG = 'kosong';

    public const STATUSES = [
        self::STATUS_READY => 'Ready',
        self::STATUS_INDENT => 'Indent',
        self::STATUS_KOSONG => 'Kosong',
    ];

    protected $table = 'crm_vendor_stocks';

    protected $fillable = [
        'vendor_id',
        'product_name',
        'product_key',
        'sku',
        'status',
        'price',
        'note',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $stock) {
            $stock->product_name = trim((string) $stock->product_name);
            $stock->product_key = static::makeProductKey($stock->product_name);
            $stock->sku = trim((string) $stock->sku) ?: null;
            $stock->note = trim((string) $stock->note) ?: null;
            $stock->status = static::normalizeStatus($stock->status);
        });
    }

    public static function makeProductKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public static function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return array_key_exists($status, self::STATUSES)
            ? $status
            : self::STATUS_READY;
    }

    public static function badgeColorForStatus(?string $status): string
    {
        return match ($status) {
            self::STATUS_READY => 'green',
            self::STATUS_KOSONG => 'slate',
            default => 'amber',
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('product_name')->orderBy('id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poQuotes(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemVendor::class, 'vendor_stock_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function badgeColor(): string
    {
        return self::badgeColorForStatus($this->status);
    }
}
