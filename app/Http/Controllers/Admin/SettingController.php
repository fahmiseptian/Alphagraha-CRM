<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Support\CustomerTop;
use App\Support\FreeShippingZone;
use App\Support\OpportunityProductPricing;
use App\Support\PaymentLevel;
use App\Support\PurchaseOrderPricing;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'ppnPercent' => OpportunityProductPricing::ppnPercent(),
            'pphNonWapuJasa' => OpportunityProductPricing::pphNonWapuJasaPercent(),
            'pphWapuBarang' => OpportunityProductPricing::pphWapuBarangPercent(),
            'pphWapuJasa' => OpportunityProductPricing::pphWapuJasaPercent(),
            'pnbpTiers' => OpportunityProductPricing::pnbpTiers(),
            'pph29Percent' => OpportunityProductPricing::pph29Percent(),
            'zinitTiers' => OpportunityProductPricing::zinitTiers(),
            'royaltyPercent' => OpportunityProductPricing::royaltyPercent(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ppn_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pph_non_wapu_jasa' => ['required', 'numeric', 'min:0', 'max:100'],
            'pph_wapu_barang' => ['required', 'numeric', 'min:0', 'max:100'],
            'pph_wapu_jasa' => ['required', 'numeric', 'min:0', 'max:100'],
            'pph29_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'royalty_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pnbp_tiers' => ['required', 'array', 'min:1'],
            'pnbp_tiers.*.max' => ['nullable', 'numeric', 'min:0'],
            'pnbp_tiers.*.rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pnbp_tiers.*.cap' => ['required', 'numeric', 'min:0'],
            'zinit_tiers' => ['required', 'array', 'min:1'],
            'zinit_tiers.*.max' => ['nullable', 'numeric', 'min:0'],
            'zinit_tiers.*.platform_fee' => ['required', 'numeric', 'min:0'],
            'zinit_tiers.*.rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'zinit_tiers.*.cap' => ['nullable', 'numeric', 'min:0'],
        ]);

        CrmSetting::set('tax.ppn_percent', round((float) $data['ppn_percent'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPN (%)',
            'description' => 'Persentase PPN untuk harga include/exclude dan penawaran.',
        ]);

        $nonWapuJasa = round((float) $data['pph_non_wapu_jasa'], 2);
        CrmSetting::set('tax.pph_non_wapu_jasa', $nonWapuJasa, [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPH Non Wapu — Jasa (%)',
            'description' => 'PPH untuk item Non Wapu + Jasa. Non Wapu + Barang tetap tanpa PPH.',
        ]);
        // Legacy key — tetap disinkronkan agar kode lama tidak pecah.
        CrmSetting::set('tax.pph_percent', $nonWapuJasa, [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPH (%)',
            'description' => 'Legacy: disamakan dengan PPH Non Wapu Jasa.',
        ]);

        CrmSetting::set('tax.pph_wapu_barang', round((float) $data['pph_wapu_barang'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPH Wapu — Barang (%)',
            'description' => 'PPH untuk item Wapu + Barang.',
        ]);

        CrmSetting::set('tax.pph_wapu_jasa', round((float) $data['pph_wapu_jasa'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPH Wapu — Jasa (%)',
            'description' => 'PPH untuk item Wapu + Jasa.',
        ]);

        $tiers = OpportunityProductPricing::normalizePnbpTiers($data['pnbp_tiers']);
        CrmSetting::set('tax.pnbp_tiers', $tiers, [
            'type' => 'json',
            'group' => 'tax',
            'label' => 'PNBP berjenjang',
            'description' => 'Jenjang PNBP Inaproc dari harga jual include: batas, rate %, cap (MIN).',
        ]);
        // Legacy single % = rate jenjang pertama.
        CrmSetting::set('tax.pnbp_percent', round((float) ($tiers[0]['rate_percent'] ?? 0.4), 4), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PNBP (%)',
            'description' => 'Legacy: disamakan dengan rate PNBP jenjang pertama.',
        ]);

        CrmSetting::set('tax.pph29_percent', round((float) $data['pph29_percent'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'PPH Pasal 29 (%)',
            'description' => 'PPH Pasal 29 Inaproc: (jual exclude − modal exclude) × % × qty.',
        ]);

        $zinitTiers = OpportunityProductPricing::normalizeZinitTiers($data['zinit_tiers']);
        CrmSetting::set('tax.zinit_tiers', $zinitTiers, [
            'type' => 'json',
            'group' => 'tax',
            'label' => 'Rate Scale Zinit',
            'description' => 'Jenjang Fee Zinit: batas jual include, platform fee, service fee %, dan CAP.',
        ]);

        CrmSetting::set('tax.royalty_percent', round((float) $data['royalty_percent'], 2), [
            'type' => 'number',
            'group' => 'tax',
            'label' => 'Royalti (%)',
            'description' => 'Persentase royalti dari modal exclude bila checkbox Royalti dicentang (semua kategori pajak).',
        ]);

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Pengaturan pajak berhasil disimpan.');
    }

    public function editMargin()
    {
        $defaults = PaymentLevel::allMinMargins();
        $topMargins = CustomerTop::allMinMargins();

        return view('admin.settings.margin', [
            'marginLancar' => PaymentLevel::minMarginPercent(PaymentLevel::LANCAR) ?? $defaults[PaymentLevel::LANCAR],
            'marginMandek' => PaymentLevel::minMarginPercent(PaymentLevel::MANDEK) ?? $defaults[PaymentLevel::MANDEK],
            'marginJelek' => PaymentLevel::minMarginPercent(PaymentLevel::JELEK) ?? $defaults[PaymentLevel::JELEK],
            'topMargins' => $topMargins,
            'topLabels' => CustomerTop::LABELS,
            'nominalUmum' => PaymentLevel::marginNominalUmum(),
            'nominalOngkirPribadi' => PaymentLevel::marginNominalOngkirPribadi(),
            'marginMaxPercent' => PaymentLevel::maxMarginPercent(),
        ]);
    }

    public function updateMargin(Request $request)
    {
        $rules = [
            'margin_lancar' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin_mandek' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin_jelek' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin_max_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'nominal_umum' => ['required', 'numeric', 'min:0'],
            'nominal_ongkir_pribadi' => ['required', 'numeric', 'min:0'],
        ];

        foreach (CustomerTop::OPTIONS as $top) {
            $rules['margin_top_'.$top] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        $data = $request->validate($rules);

        CrmSetting::set('payment_level.margin_lancar', round((float) $data['margin_lancar'], 2), [
            'type' => 'number',
            'group' => 'payment_level',
            'label' => 'Minimal Margin Lancar (%)',
            'description' => 'Minimal margin opportunity (%) untuk customer level Lancar sebelum quotation perlu approval Superadmin.',
        ]);

        CrmSetting::set('payment_level.margin_mandek', round((float) $data['margin_mandek'], 2), [
            'type' => 'number',
            'group' => 'payment_level',
            'label' => 'Minimal Margin Mandek (%)',
            'description' => 'Minimal margin opportunity (%) untuk customer level Mandek sebelum quotation perlu approval Superadmin.',
        ]);

        CrmSetting::set('payment_level.margin_jelek', round((float) $data['margin_jelek'], 2), [
            'type' => 'number',
            'group' => 'payment_level',
            'label' => 'Minimal Margin Jelek (%)',
            'description' => 'Minimal margin opportunity (%) untuk customer level Jelek sebelum quotation perlu approval Superadmin.',
        ]);

        $topSettingLabels = [
            CustomerTop::CASH => 'Minimal Margin Cash (%)',
            CustomerTop::DAYS_7 => 'Minimal Margin TOP 7 Hari (%)',
            CustomerTop::DAYS_14 => 'Minimal Margin TOP 14 Hari (%)',
            CustomerTop::DAYS_30 => 'Minimal Margin TOP 30 Hari (%)',
            CustomerTop::DAYS_45 => 'Minimal Margin TOP 45 Hari (%)',
            CustomerTop::DAYS_60 => 'Minimal Margin TOP 60 Hari (%)',
        ];

        foreach (CustomerTop::OPTIONS as $top) {
            $field = 'margin_top_'.$top;
            CrmSetting::set(CustomerTop::settingKey($top), round((float) $data[$field], 2), [
                'type' => 'number',
                'group' => 'customer_top',
                'label' => $topSettingLabels[$top] ?? 'Minimal Margin TOP (%)',
                'description' => 'Minimal margin opportunity (%) untuk customer dengan TOP '.CustomerTop::label($top).'.',
            ]);
        }

        CrmSetting::set(PaymentLevel::SETTING_NOMINAL_UMUM, round((float) $data['nominal_umum'], 2), [
            'type' => 'number',
            'group' => 'payment_level',
            'label' => 'Margin Nominal Umum (Rp)',
            'description' => 'Ambang margin nominal umum (Rp).',
        ]);

        CrmSetting::set(PaymentLevel::SETTING_NOMINAL_ONGKIR_PRIBADI, round((float) $data['nominal_ongkir_pribadi'], 2), [
            'type' => 'number',
            'group' => 'payment_level',
            'label' => 'Margin Nominal Ongkir Pribadi (Rp)',
            'description' => 'Ambang margin nominal bila memakai ongkir pribadi (Rp).',
        ]);

        CrmSetting::set(PaymentLevel::SETTING_MAX_PERCENT, round((float) $data['margin_max_percent'], 2), [
            'type' => 'number',
            'group' => 'payment_level',
            'label' => 'Batas Atas Margin (%)',
            'description' => 'Maksimal margin opportunity (%). Di atas nilai ini memerlukan approval Superadmin.',
        ]);

        return redirect()
            ->route('settings.margin.edit')
            ->with('success', 'Pengaturan margin berhasil disimpan.');
    }

    public function editPo()
    {
        return view('admin.settings.po', [
            'surchargeCash' => PurchaseOrderPricing::cashSurchargePercent(),
            'surchargeTop' => PurchaseOrderPricing::topSurchargePercent(),
        ]);
    }

    public function updatePo(Request $request)
    {
        $data = $request->validate([
            'surcharge_cash_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'surcharge_top_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        CrmSetting::set('po.surcharge_cash_percent', round((float) $data['surcharge_cash_percent'], 2), [
            'type' => 'number',
            'group' => 'po',
            'label' => 'Biaya Tambahan Cash (%)',
            'description' => 'Persentase tambahan pada modal PO bila kondisi pembayaran Cash (exclude & include).',
        ]);

        CrmSetting::set('po.surcharge_top_percent', round((float) $data['surcharge_top_percent'], 2), [
            'type' => 'number',
            'group' => 'po',
            'label' => 'Biaya Tambahan TOP (%)',
            'description' => 'Persentase tambahan pada modal PO bila kondisi pembayaran TOP (exclude & include).',
        ]);

        return redirect()
            ->route('settings.po.edit')
            ->with('success', 'Pengaturan biaya tambahan PO berhasil disimpan.');
    }

    public function editTerms()
    {
        $terms = CrmSetting::get('quotation.default_terms');
        if (! is_string($terms) || trim($terms) === '') {
            $terms = OpportunityProductPricing::defaultQuotationTerms();
        }

        return view('admin.settings.terms', [
            'defaultTerms' => $terms,
            'ppnPercent' => OpportunityProductPricing::ppnPercent(),
        ]);
    }

    public function updateTerms(Request $request)
    {
        $data = $request->validate([
            'default_terms' => ['required', 'string', 'max:50000'],
        ], [
            'default_terms.required' => 'Terms & Conditions wajib diisi.',
        ]);

        $termsHtml = trim($data['default_terms']);
        if (trim(strip_tags($termsHtml)) === '') {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['default_terms' => 'Terms & Conditions wajib diisi.']);
        }

        CrmSetting::set('quotation.default_terms', $termsHtml, [
            'type' => 'string',
            'group' => 'quotation',
            'label' => 'Terms & Conditions default (QO)',
            'description' => 'Teks default Terms & Conditions saat membuat Quotation baru. Placeholder {{ppn}} diganti tarif PPN.',
        ]);

        return redirect()
            ->route('settings.terms.edit')
            ->with('success', 'Terms & Conditions default berhasil disimpan.');
    }

    public function editShipping()
    {
        $availableRegencies = FreeShippingZone::availableRegencies();
        $selectedCodes = FreeShippingZone::selectedRegencyCodes();

        $availableCodes = collect($availableRegencies)->pluck('code')->all();
        $orphanSelected = array_values(array_filter(
            $selectedCodes,
            fn (string $code) => ! in_array($code, $availableCodes, true)
        ));

        return view('admin.settings.shipping', [
            'availableRegencies' => $availableRegencies,
            'selectedCodes' => $selectedCodes,
            'orphanSelected' => $orphanSelected,
        ]);
    }

    public function updateShipping(Request $request)
    {
        $data = $request->validate([
            'regency_codes' => ['nullable', 'array'],
            'regency_codes.*' => ['string', 'max:10'],
        ]);

        $codes = FreeShippingZone::normalizeCodes($data['regency_codes'] ?? []);

        CrmSetting::set(FreeShippingZone::SETTING_KEY_CODES, $codes, [
            'type' => 'json',
            'group' => 'shipping',
            'label' => 'Kawasan free ongkir (kode kota/kab)',
            'description' => 'Daftar kode kota/kabupaten master wilayah yang mendapat free ongkir.',
        ]);

        // Sinkron nama untuk kompatibilitas pembaca lama.
        $names = \App\Models\WilayahRegency::query()
            ->whereIn('code', $codes)
            ->orderBy('name')
            ->pluck('name')
            ->all();
        CrmSetting::set(FreeShippingZone::SETTING_KEY, $names, [
            'type' => 'json',
            'group' => 'shipping',
            'label' => 'Kawasan free ongkir (nama kota)',
            'description' => 'Mirror nama kota dari kode terpilih (kompatibilitas).',
        ]);

        return redirect()
            ->route('settings.shipping.edit')
            ->with('success', 'Kawasan free ongkir berhasil disimpan ('.count($codes).' kota).');
    }
}
