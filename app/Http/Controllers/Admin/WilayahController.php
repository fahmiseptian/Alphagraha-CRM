<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Services\WilayahSyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WilayahController extends Controller
{
    public function __construct(
        protected WilayahSyncService $sync
    ) {}

    public function index(Request $request)
    {
        $provinceCode = $request->get('province');
        $regencyCode = $request->get('regency');

        $provinces = WilayahProvince::query()->orderBy('name')->get();
        $regencies = collect();
        $districts = collect();

        if ($provinceCode) {
            $regencies = WilayahRegency::query()
                ->where('province_code', $provinceCode)
                ->orderBy('name')
                ->get();
        }

        if ($regencyCode) {
            $districts = WilayahDistrict::query()
                ->where('regency_code', $regencyCode)
                ->orderBy('name')
                ->get();
        }

        return view('admin.settings.wilayah', [
            'provinces' => $provinces,
            'regencies' => $regencies,
            'districts' => $districts,
            'provinceCode' => $provinceCode,
            'regencyCode' => $regencyCode,
            'lastSyncedAt' => CrmSetting::get(WilayahSyncService::SETTING_LAST_SYNC),
            'counts' => [
                'provinces' => WilayahProvince::query()->count(),
                'regencies' => WilayahRegency::query()->count(),
                'districts' => WilayahDistrict::query()->count(),
            ],
        ]);
    }

    public function sync(Request $request)
    {
        @set_time_limit(0);

        $mode = $request->input('mode', 'basic');

        try {
            if ($mode === 'full') {
                $counts = $this->sync->syncAll();
                $msg = sprintf(
                    'Sync penuh selesai: %d provinsi, %d kota/kab, %d kecamatan.',
                    $counts['provinces'],
                    $counts['regencies'],
                    $counts['districts']
                );
            } else {
                $counts = $this->sync->syncProvincesAndRegencies();
                $msg = sprintf(
                    'Sync selesai: %d provinsi, %d kota/kab. Kecamatan diisi otomatis saat dipilih di form (atau Sync Kecamatan).',
                    $counts['provinces'],
                    $counts['regencies']
                );
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync gagal: '.$e->getMessage());
        }

        return back()->with('success', $msg);
    }

    public function syncDistricts(Request $request)
    {
        $data = $request->validate([
            'regency_code' => ['required', 'string', 'max:10', Rule::exists('crm_wilayah_regencies', 'code')],
        ]);

        try {
            $count = $this->sync->syncDistrictsForRegency($data['regency_code']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync kecamatan gagal: '.$e->getMessage());
        }

        return redirect()
            ->route('settings.wilayah.index', [
                'province' => $request->input('province')
                    ?: WilayahRegency::query()->where('code', $data['regency_code'])->value('province_code'),
                'regency' => $data['regency_code'],
            ])
            ->with('success', "Sync kecamatan selesai ({$count} data).");
    }

    public function storeProvince(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:crm_wilayah_provinces,code'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $this->sync->upsertProvince($data['code'], $data['name'], 'manual');

        return back()->with('success', 'Provinsi ditambahkan.');
    }

    public function storeRegency(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:crm_wilayah_regencies,code'],
            'province_code' => ['required', 'string', Rule::exists('crm_wilayah_provinces', 'code')],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $this->sync->upsertRegency($data['code'], $data['province_code'], $data['name'], 'manual');

        return redirect()
            ->route('settings.wilayah.index', ['province' => $data['province_code']])
            ->with('success', 'Kota/Kabupaten ditambahkan.');
    }

    public function storeDistrict(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:15', 'unique:crm_wilayah_districts,code'],
            'regency_code' => ['required', 'string', Rule::exists('crm_wilayah_regencies', 'code')],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $regency = WilayahRegency::query()->findOrFail($data['regency_code']);
        $this->sync->upsertDistrict($data['code'], $data['regency_code'], $data['name'], 'manual');

        return redirect()
            ->route('settings.wilayah.index', [
                'province' => $regency->province_code,
                'regency' => $data['regency_code'],
            ])
            ->with('success', 'Kecamatan ditambahkan.');
    }
}
