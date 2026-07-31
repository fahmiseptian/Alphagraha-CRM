<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    public const DEFAULT_SALES_TARGET = 1_000_000_000_000;

    public const TARGET_PERIOD_1_MONTH = '1_month';

    public const TARGET_PERIOD_3_MONTHS = '3_months';

    public const TARGET_PERIOD_6_MONTHS = '6_months';

    public const TARGET_PERIOD_1_YEAR = '1_year';

    public const TARGET_PERIODS = [
        self::TARGET_PERIOD_1_MONTH => '1 Bulan',
        self::TARGET_PERIOD_3_MONTHS => '3 Bulan',
        self::TARGET_PERIOD_6_MONTHS => '6 Bulan',
        self::TARGET_PERIOD_1_YEAR => '1 Tahun',
    ];

    protected $table = 'crm_user_profiles';

    protected $fillable = [
        'user_id', 'app_role', 'signature_path',
        'signature_agc_path', 'signature_eps_path', 'signature_psi_path',
        'job_position', 'sales_code',
        'sales_target', 'sales_target_period', 'sales_target_deadline',
    ];

    protected $casts = [
        'sales_target' => 'float',
        'sales_target_deadline' => 'date',
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
            $defaults['sales_target_period'] = self::TARGET_PERIOD_1_YEAR;
        }

        return static::query()->firstOrCreate(['user_id' => $userId], $defaults);
    }

    public function resolvedSalesTarget(): float
    {
        $target = (float) ($this->sales_target ?? 0);

        return $target > 0 ? $target : self::DEFAULT_SALES_TARGET;
    }

    public function resolvedSalesTargetPeriod(): string
    {
        $period = (string) ($this->sales_target_period ?? '');

        return array_key_exists($period, self::TARGET_PERIODS)
            ? $period
            : self::TARGET_PERIOD_1_YEAR;
    }

    public function salesTargetPeriodLabel(): string
    {
        return self::TARGET_PERIODS[$this->resolvedSalesTargetPeriod()] ?? self::TARGET_PERIODS[self::TARGET_PERIOD_1_YEAR];
    }

    /**
     * Panjang periode target dalam bulan.
     */
    public static function periodLengthMonths(string $period): int
    {
        return match ($period) {
            self::TARGET_PERIOD_1_MONTH => 1,
            self::TARGET_PERIOD_3_MONTHS => 3,
            self::TARGET_PERIOD_6_MONTHS => 6,
            default => 12,
        };
    }

    /**
     * Bulan tenggat otomatis dalam setahun (1–12), dihitung dari awal tahun.
     * Contoh 3 bulan → [3, 6, 9, 12]; 6 bulan → [6, 12].
     *
     * @return list<int>
     */
    public static function autoDeadlineMonths(string $period): array
    {
        $length = self::periodLengthMonths($period);
        $months = [];
        for ($m = $length; $m <= 12; $m += $length) {
            $months[] = $m;
        }

        return $months;
    }

    /**
     * Label tenggat otomatis setahun, mis. "Maret, Juni, September, Desember".
     */
    public function salesTargetAutoDeadlineScheduleLabel(): string
    {
        $names = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return collect(self::autoDeadlineMonths($this->resolvedSalesTargetPeriod()))
            ->map(fn (int $m) => $names[$m] ?? (string) $m)
            ->implode(', ');
    }

    /**
     * Rentang tanggal untuk menghitung progress target Closed Won.
     * - Jika tenggat manual diisi: [tenggat − periode, tenggat].
     * - Jika kosong: segmen kalender dari awal tahun (1/3/6/12 bulan).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function salesTargetDateRange(?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->copy();
        $period = $this->resolvedSalesTargetPeriod();
        $months = self::periodLengthMonths($period);

        if ($this->sales_target_deadline) {
            $end = Carbon::parse($this->sales_target_deadline)->endOfDay();
            $start = $end->copy()->subMonthsNoOverflow($months)->addDay()->startOfDay();

            return [$start, $end];
        }

        // Segmen otomatis dari awal tahun: Jan–Mar, Apr–Jun, … sesuai panjang periode.
        $segmentIndex = (int) ceil($now->month / $months) - 1;
        $startMonth = ($segmentIndex * $months) + 1;
        $endMonth = min($startMonth + $months - 1, 12);

        $start = Carbon::create($now->year, $startMonth, 1)->startOfDay();
        $end = Carbon::create($now->year, $endMonth, 1)->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    /**
     * Tenggat efektif: tanggal manual, atau akhir segmen periode otomatis.
     */
    public function resolvedSalesTargetDeadline(?Carbon $now = null): Carbon
    {
        if ($this->sales_target_deadline) {
            return Carbon::parse($this->sales_target_deadline)->startOfDay();
        }

        [, $end] = $this->salesTargetDateRange($now);

        return $end->copy()->startOfDay();
    }

    public function hasManualSalesTargetDeadline(): bool
    {
        return $this->sales_target_deadline !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Key perusahaan internal: agc | eps | psi.
     *
     * @return list<string>
     */
    public static function signatureCompanyKeys(): array
    {
        return ['agc', 'eps', 'psi'];
    }

    /**
     * Label tampilan per key perusahaan.
     *
     * @return array<string, string>
     */
    public static function signatureCompanyLabels(): array
    {
        return [
            'agc' => 'Alpha Graha Computindo',
            'eps' => 'Elite Proxy Sistem',
            'psi' => 'Power Sistem Integrasi',
        ];
    }

    public static function signatureColumn(string $companyKey): string
    {
        return match ($companyKey) {
            'eps' => 'signature_eps_path',
            'psi' => 'signature_psi_path',
            default => 'signature_agc_path',
        };
    }

    public static function resolveCompanyKey(?string $companyOrCategory): string
    {
        $value = trim((string) $companyOrCategory);
        if ($value === '') {
            return 'agc';
        }

        $lower = strtolower($value);
        if (in_array($lower, self::signatureCompanyKeys(), true)) {
            return $lower;
        }

        $map = config('crm.quotation_company_map', []);

        return $map[$value] ?? 'agc';
    }

    public function signaturePathFor(string $companyKey): ?string
    {
        $column = self::signatureColumn(self::resolveCompanyKey($companyKey));
        $path = $this->{$column} ?: null;

        // Fallback TTD legacy (kolom lama) hanya untuk AGC.
        if (! $path && $column === 'signature_agc_path') {
            $path = $this->signature_path ?: null;
        }

        return $path ?: null;
    }

    public function setSignaturePathFor(string $companyKey, ?string $path): void
    {
        $column = self::signatureColumn(self::resolveCompanyKey($companyKey));
        $this->{$column} = $path;

        // Sinkronkan kolom legacy agar kode lama tetap aman.
        if ($column === 'signature_agc_path') {
            $this->signature_path = $path;
        }
    }

    public function signatureUrl(?string $companyKey = null): ?string
    {
        $path = $companyKey
            ? $this->signaturePathFor($companyKey)
            : ($this->signature_agc_path ?: $this->signature_path);

        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function signatureAbsolutePath(?string $companyKey = null): ?string
    {
        $path = $companyKey
            ? $this->signaturePathFor($companyKey)
            : ($this->signature_agc_path ?: $this->signature_path);

        if (! $path) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);

        return is_file($absolute) ? $absolute : null;
    }

    public function hasSignatureFor(string $companyKey): bool
    {
        return (bool) $this->signatureAbsolutePath($companyKey);
    }

    public function hasAllCompanySignatures(): bool
    {
        foreach (self::signatureCompanyKeys() as $key) {
            if (! $this->hasSignatureFor($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string> label perusahaan yang belum punya TTD
     */
    public function missingSignatureLabels(): array
    {
        $labels = self::signatureCompanyLabels();
        $missing = [];

        foreach (self::signatureCompanyKeys() as $key) {
            if (! $this->hasSignatureFor($key)) {
                $missing[] = $labels[$key] ?? $key;
            }
        }

        return $missing;
    }
}
