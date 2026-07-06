<?php

namespace Database\Seeders;

use App\Models\QuotationTemplate;
use Illuminate\Database\Seeder;

class QuotationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'agc-indo',
                'name' => 'AGC-indo',
                'description' => 'Template penawaran PT. Alpha Graha Computindo (Bahasa Indonesia).',
                'is_default' => true,
                'accent' => '#2563eb',
            ],
            [
                'code' => 'eps-indo',
                'name' => 'EPS-indo',
                'description' => 'Template penawaran PT. Elite Proxy Sistem (Bahasa Indonesia).',
                'is_default' => false,
                'accent' => '#0ea5e9',
            ],
            [
                'code' => 'psi-indo',
                'name' => 'PSI-indo',
                'description' => 'Template penawaran PT. POWER SISTEM INTEGRASI (Bahasa Indonesia).',
                'is_default' => false,
                'accent' => '#16a34a',
            ],
        ];

        foreach ($templates as $meta) {
            QuotationTemplate::updateOrCreate(
                ['code' => $meta['code']],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'body_html' => $this->indoTemplateHtml($meta['accent']),
                    'is_active' => true,
                    'is_default' => $meta['is_default'],
                ]
            );
        }

        QuotationTemplate::query()
            ->whereNull('code')
            ->where('name', 'Company Standard Template')
            ->update(['is_default' => false, 'is_active' => false]);
    }

    protected function indoTemplateHtml(string $accent): string
    {
        return <<<HTML
<div style="font-family: Arial, Helvetica, sans-serif; color:#111827; font-size:12px; line-height:1.55;">
    <table style="width:100%; border-collapse:collapse; border-bottom:2px solid {$accent}; margin-bottom:18px; padding-bottom:12px;">
        <tr>
            <td style="vertical-align:top; width:65%;">
                <div style="font-size:20px; font-weight:700; color:{$accent};">{{ company_legal_name }}</div>
                <div style="color:#4b5563; margin-top:4px;">{{ company_address }}</div>
                <div style="color:#4b5563;">Tel: {{ company_phone }} &middot; {{ company_email }}</div>
            </td>
            <td style="vertical-align:top; text-align:right; width:35%;">
                <div style="font-size:18px; font-weight:700; letter-spacing:1px;">PENAWARAN HARGA</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-bottom:16px;">
        <tr>
            <td style="width:50%; vertical-align:top;">
                <div><strong>Our Ref.</strong> {{ quotation_ref }}</div>
            </td>
            <td style="width:50%; vertical-align:top; text-align:right;">
                <div>{{ quotation_place_date }}</div>
            </td>
        </tr>
    </table>

    <div style="margin-bottom:14px;">
        <div>Kepada Yth,</div>
        <div style="font-weight:700; margin-top:4px;">{{ customer_name }}</div>
        <div>{{ company_name }}</div>
        <div style="color:#4b5563;">{{ customer_address }}</div>
        <div style="margin-top:6px;">Up. : {{ contact_person }}</div>
    </div>

    <p style="margin:12px 0;">Dengan hormat,</p>
    <p style="margin:0 0 12px;">Sesuai dengan permintaan Bapak/Ibu {{ contact_person }}, dengan ini kami sampaikan penawaran harga dengan spesifikasi berikut:</p>

    {{ items_table_idr }}

    <div style="margin-top:16px;">
        <div style="font-weight:700; margin-bottom:6px;">Kondisi penawaran:</div>
        <div>{{ terms }}</div>
        <div style="margin-top:8px;">{{ notes }}</div>
    </div>

    <div style="margin-top:36px;">
        <div>Hormat kami,</div>
        <div style="font-weight:700; margin-top:4px;">{{ company_legal_name }}</div>
        <div style="height:78px; margin:8px 0;">{{ sales_signature }}</div>
        <div style="font-weight:700; text-decoration:underline; display:inline-block;">{{ sales_name }}</div>
        <div style="color:#4b5563;">{{ sales_job_position }}</div>
    </div>
</div>
HTML;
    }
}
