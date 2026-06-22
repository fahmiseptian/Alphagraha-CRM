<?php

namespace Database\Seeders;

use App\Models\QuotationTemplate;
use Illuminate\Database\Seeder;

class QuotationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $html = <<<'HTML'
<div style="font-family: 'Helvetica Neue', Arial, sans-serif; color:#1f2937; font-size:13px; line-height:1.6;">
    <table style="width:100%; border-collapse:collapse; margin-bottom:24px;">
        <tr>
            <td style="vertical-align:top;">
                <div style="font-size:22px; font-weight:700; color:#2563eb;">PT Alpha Graha Computindo</div>
                <div style="color:#6b7280;">Jl. Contoh Alamat No. 123, Jakarta</div>
                <div style="color:#6b7280;">Telp: (021) 1234-5678 &middot; info@agc.co.id</div>
            </td>
            <td style="vertical-align:top; text-align:right;">
                <div style="font-size:26px; font-weight:700; letter-spacing:2px; color:#111827;">PENAWARAN</div>
                <div style="color:#6b7280;">No: <strong>{{ quotation_number }}</strong></div>
                <div style="color:#6b7280;">Tanggal: {{ quotation_date }}</div>
                <div style="color:#6b7280;">Berlaku s/d: {{ valid_until }}</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-bottom:20px;">
        <tr>
            <td style="vertical-align:top;">
                <div style="text-transform:uppercase; font-size:11px; color:#9ca3af; letter-spacing:1px;">Kepada Yth.</div>
                <div style="font-weight:700; font-size:15px;">{{ customer_name }}</div>
                <div>{{ company_name }}</div>
                <div style="color:#6b7280;">{{ customer_address }}</div>
                <div style="color:#6b7280;">{{ customer_email }} &middot; {{ customer_phone }}</div>
            </td>
        </tr>
    </table>

    <p>Dengan hormat,</p>
    <p>Bersama ini kami sampaikan penawaran harga untuk produk/layanan sebagai berikut:</p>

    {{ items_table }}

    <table style="width:100%; margin-top:16px;">
        <tr>
            <td style="width:60%;"></td>
            <td style="width:40%;">
                <table style="width:100%; border-collapse:collapse;">
                    <tr><td style="padding:4px 0;">Subtotal</td><td style="text-align:right;">{{ subtotal }}</td></tr>
                    <tr><td style="padding:4px 0;">Diskon</td><td style="text-align:right;">{{ discount }}</td></tr>
                    <tr><td style="padding:4px 0;">Pajak ({{ tax_percent }})</td><td style="text-align:right;">{{ tax_amount }}</td></tr>
                    <tr style="border-top:2px solid #111827; font-weight:700; font-size:15px;">
                        <td style="padding:8px 0;">TOTAL</td><td style="text-align:right; padding:8px 0; color:#2563eb;">{{ total_price }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top:20px;">
        <div style="text-transform:uppercase; font-size:11px; color:#9ca3af; letter-spacing:1px;">Catatan</div>
        <div>{{ notes }}</div>
    </div>

    <div style="margin-top:12px;">
        <div style="text-transform:uppercase; font-size:11px; color:#9ca3af; letter-spacing:1px;">Syarat &amp; Ketentuan</div>
        <div>{{ terms }}</div>
    </div>

    <table style="width:100%; margin-top:48px;">
        <tr>
            <td style="text-align:center; width:50%;">
                <div>Hormat kami,</div>
                <div style="height:64px;"></div>
                <div style="font-weight:700; border-top:1px solid #d1d5db; display:inline-block; padding-top:4px;">{{ sales_name }}</div>
                <div style="color:#6b7280;">Sales Representative</div>
            </td>
            <td style="text-align:center; width:50%;">
                <div>Menyetujui,</div>
                <div style="height:64px;"></div>
                <div style="font-weight:700; border-top:1px solid #d1d5db; display:inline-block; padding-top:4px;">{{ customer_name }}</div>
                <div style="color:#6b7280;">{{ company_name }}</div>
            </td>
        </tr>
    </table>
</div>
HTML;

        QuotationTemplate::updateOrCreate(
            ['name' => 'Template Standar Perusahaan'],
            [
                'description' => 'Template penawaran resmi yang wajib digunakan seluruh sales.',
                'body_html' => $html,
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }
}
