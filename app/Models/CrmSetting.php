<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CrmSetting extends Model
{
    protected $table = 'crm_settings';

    protected $fillable = [
        'key', 'value', 'type', 'group', 'label', 'description',
    ];

    public const CACHE_KEY = 'crm_settings_all';

    public const CACHE_TTL = 3600;

    /**
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::query()
                ->get()
                ->mapWithKeys(fn (self $row) => [$row->key => $row->castedValue()])
                ->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allCached();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default;
    }

    public static function getFloat(string $key, float $default = 0): float
    {
        return (float) self::get($key, $default);
    }

    public static function set(string $key, mixed $value, array $meta = []): self
    {
        $setting = static::query()->firstOrNew(['key' => $key]);

        if (! empty($meta['type'])) {
            $setting->type = $meta['type'];
        } elseif (! $setting->exists) {
            $setting->type = is_numeric($value) ? 'number' : 'string';
        }

        if (isset($meta['group'])) {
            $setting->group = $meta['group'];
        } elseif (! $setting->exists) {
            $setting->group = 'general';
        }

        if (array_key_exists('label', $meta)) {
            $setting->label = $meta['label'];
        }

        if (array_key_exists('description', $meta)) {
            $setting->description = $meta['description'];
        }

        $setting->value = is_bool($value)
            ? ($value ? '1' : '0')
            : (is_array($value) ? json_encode($value) : (string) $value);

        $setting->save();
        self::forgetCache();

        return $setting;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function castedValue(): mixed
    {
        return match ($this->type) {
            'number' => $this->value === null || $this->value === '' ? null : (float) $this->value,
            'boolean' => in_array((string) $this->value, ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
