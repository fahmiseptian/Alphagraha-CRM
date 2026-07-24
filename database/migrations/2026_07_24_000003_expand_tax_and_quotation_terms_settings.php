<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed setting pajak detail (PPH per kategori), PNBP, PPH 29, dan Terms QO default.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'key' => 'tax.pph_non_wapu_jasa',
                'value' => '2',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PPH Non Wapu — Jasa (%)',
                'description' => 'PPH untuk item Non Wapu + Jasa. Non Wapu + Barang tetap tanpa PPH.',
            ],
            [
                'key' => 'tax.pph_wapu_barang',
                'value' => '1.5',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PPH Wapu — Barang (%)',
                'description' => 'PPH untuk item Wapu + Barang.',
            ],
            [
                'key' => 'tax.pph_wapu_jasa',
                'value' => '2',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PPH Wapu — Jasa (%)',
                'description' => 'PPH untuk item Wapu + Jasa.',
            ],
            [
                'key' => 'tax.pnbp_percent',
                'value' => '0.4',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PNBP (%)',
                'description' => 'Persentase PNBP (referensi / perhitungan lanjutan).',
            ],
            [
                'key' => 'tax.pph29_percent',
                'value' => '22',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PPH Pasal 29 (%)',
                'description' => 'Tarif PPH badan (Pasal 29), default 22%.',
            ],
            [
                'key' => 'quotation.default_terms',
                'value' => "1. Harga di atas belum termasuk PPN\n2. Harga dan ketersediaan barang dapat berubah sewaktu-waktu tanpa pemberitahuan terlebih dahulu.",
                'type' => 'string',
                'group' => 'quotation',
                'label' => 'Terms & Conditions default (QO)',
                'description' => 'Teks default Terms & Conditions saat membuat Quotation baru.',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('crm_settings')->where('key', $row['key'])->exists()) {
                continue;
            }
            DB::table('crm_settings')->insert(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Samakan legacy tax.pph_percent dengan Non Wapu Jasa bila ada.
        $legacy = DB::table('crm_settings')->where('key', 'tax.pph_percent')->value('value');
        $nonWapuJasa = DB::table('crm_settings')->where('key', 'tax.pph_non_wapu_jasa')->value('value');
        if ($legacy !== null && $nonWapuJasa === null) {
            // already inserted above
        } elseif ($legacy !== null && $nonWapuJasa !== null && (float) $legacy !== (float) $nonWapuJasa) {
            // keep both; pricing akan pakai key baru
        }

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }

    public function down(): void
    {
        DB::table('crm_settings')->whereIn('key', [
            'tax.pph_non_wapu_jasa',
            'tax.pph_wapu_barang',
            'tax.pph_wapu_jasa',
            'tax.pnbp_percent',
            'tax.pph29_percent',
            'quotation.default_terms',
        ])->delete();

        if (class_exists(\App\Models\CrmSetting::class)) {
            \App\Models\CrmSetting::forgetCache();
        }
    }
};
