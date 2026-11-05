<?php

use App\Services\PurchaseOrderDocumentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan garis tabel total dengan kolom barang: satu tabel, bukan dua tabel terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('crm_purchase_order_templates')) {
            return;
        }

        DB::table('crm_purchase_order_templates')
            ->whereIn('code', ['po-agc', 'po-eps', 'po-psi'])
            ->update([
                'body_html' => app(PurchaseOrderDocumentService::class)->defaultBodyHtml(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Template bawaan sudah diganti. Tidak dikembalikan.
    }
};
