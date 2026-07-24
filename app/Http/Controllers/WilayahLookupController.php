<?php

namespace App\Http\Controllers;

use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Services\WilayahSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookup wilayah dari DB (untuk form customer cascading).
 * Kecamatan di-fetch dari API sekali bila belum ada di DB.
 */
class WilayahLookupController extends Controller
{
    public function __construct(
        protected WilayahSyncService $sync
    ) {}

    public function provinces(): JsonResponse
    {
        $rows = WilayahProvince::query()
            ->orderBy('name')
            ->get(['code', 'name']);

        return response()->json(['data' => $rows]);
    }

    public function regencies(Request $request): JsonResponse
    {
        $province = trim((string) $request->query('province', ''));
        if ($province === '') {
            return response()->json(['data' => []]);
        }

        $rows = WilayahRegency::query()
            ->where('province_code', $province)
            ->orderBy('name')
            ->get(['code', 'name', 'province_code']);

        return response()->json(['data' => $rows]);
    }

    public function districts(Request $request): JsonResponse
    {
        $regency = trim((string) $request->query('regency', ''));
        if ($regency === '') {
            return response()->json(['data' => []]);
        }

        $exists = WilayahRegency::query()->where('code', $regency)->exists();
        if (! $exists) {
            return response()->json(['data' => [], 'error' => 'Kota/kab tidak ditemukan'], 404);
        }

        $count = WilayahDistrict::query()->where('regency_code', $regency)->count();
        if ($count === 0) {
            try {
                $this->sync->syncDistrictsForRegency($regency);
            } catch (\Throwable $e) {
                return response()->json([
                    'data' => [],
                    'error' => 'Gagal mengambil kecamatan dari API: '.$e->getMessage(),
                ], 502);
            }
        }

        $rows = WilayahDistrict::query()
            ->where('regency_code', $regency)
            ->orderBy('name')
            ->get(['code', 'name', 'regency_code']);

        return response()->json(['data' => $rows]);
    }
}
