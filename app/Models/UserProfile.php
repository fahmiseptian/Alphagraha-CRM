<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    public const DEFAULT_SALES_TARGET = 1_000_000_000_000;

    protected $table = 'crm_user_profiles';

    protected $fillable = [
        'user_id', 'app_role', 'signature_path', 'job_position', 'sales_code', 'sales_target',
    ];

    protected $casts = [
        'sales_target' => 'float',
    ];

    public static function ensureForUser(string $userId, bool $isSales = true, ?string $appRole = null): self
    {
        $defaults = [];
        if ($appRole) {
            $defaults['app_role'] = $appRole;
        } elseif ($isSales) {
            $defaults['app_role'] = User::ROLE_SALES;
        }

        if ($isSales) {
            $defaults['sales_target'] = self::DEFAULT_SALES_TARGET;
        }

        return static::query()->firstOrCreate(['user_id' => $userId], $defaults);
    }

    public function resolvedSalesTarget(): float
    {
        $target = (float) ($this->sales_target ?? 0);

        return $target > 0 ? $target : self::DEFAULT_SALES_TARGET;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function signatureUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return Storage::disk('public')->url($this->signature_path);
    }

    public function signatureAbsolutePath(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $path = Storage::disk('public')->path($this->signature_path);

        return is_file($path) ? $path : null;
    }
}
