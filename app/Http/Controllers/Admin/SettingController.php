<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = CrmSetting::query()
            ->where('group', 'tax')
            ->orderBy('id')
            ->get()
            ->keyBy('key');

        return view('admin.settings.edit', [
            'ppnPercent' => (float) ($settings->get('tax.ppn_percent')?->castedValue() ?? 11),
            'pphPercent' => (float) ($settings->get('tax.pph_percent')?->castedValue() ?? 2),
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ppn_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pph_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'ppn_percent.required' => 'PPN wajib diisi.',
            'pph_percent.required' => 'PPH wajib diisi.',
        ]);

        CrmSetting::set('tax.ppn_percent', round((float) $data['ppn_percent'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPN (%)',
            'description' => 'Persentase PPN untuk harga include/exclude dan penawaran.',
        ]);

        CrmSetting::set('tax.pph_percent', round((float) $data['pph_percent'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPH (%)',
            'description' => 'Persentase PPH (mis. jasa / wapu) pada perhitungan margin opportunity.',
        ]);

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Pengaturan pajak berhasil disimpan.');
    }
}
