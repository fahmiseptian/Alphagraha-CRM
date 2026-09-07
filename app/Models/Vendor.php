<?php

namespace App\Models;

use App\Support\CustomerTop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $table = 'crm_vendors';

    protected $fillable = [
        'name',
        'company_status',
        'top',
        'is_pkp',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_pkp' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function pics(): HasMany
    {
        return $this->hasMany(VendorPic::class)->orderBy('sort_order')->orderBy('id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(VendorStock::class);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'crm_vendor_brand')
            ->withTimestamps()
            ->orderBy('crm_brands.sort_order')
            ->orderBy('crm_brands.name');
    }

    /**
     * @param  list<int|string>  $brandIds
     */
    public function syncBrands(array $brandIds): void
    {
        $ids = collect($brandIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->brands()->sync($ids);
    }

    public function topValue(): string
    {
        return CustomerTop::isValid($this->top) ? (string) $this->top : CustomerTop::DAYS_30;
    }

    public function topLabel(): string
    {
        return CustomerTop::LABELS[$this->topValue()] ?? 'TOP 30 hari';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncPics(array $rows): void
    {
        $keepIds = [];
        foreach (array_values($rows) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $payload = [
                'name' => $name,
                'job_role' => trim((string) ($row['job_role'] ?? '')) ?: null,
                'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
                'email' => trim((string) ($row['email'] ?? '')) ?: null,
                'sort_order' => (int) ($row['sort_order'] ?? $i),
            ];

            $id = (int) ($row['id'] ?? 0);
            $pic = $id > 0 ? $this->pics()->whereKey($id)->first() : null;
            if ($pic) {
                $pic->update($payload);
            } else {
                $pic = $this->pics()->create($payload);
            }
            $keepIds[] = $pic->id;
        }

        $query = VendorPic::query()->where('vendor_id', $this->id);
        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();
    }
}
