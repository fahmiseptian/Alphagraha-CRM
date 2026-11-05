<?php

use App\Models\Espo\Opportunity;
use App\Services\PurchaseOrderDocumentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_purchase_order_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable()->unique();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('created_by', 24)->nullable();
            $table->timestamps();
        });

        $body = app(PurchaseOrderDocumentService::class)->defaultBodyHtml();
        $now = now();
        $rows = [
            ['po-agc', 'PO AGC', Opportunity::COMPANIES[0], 'Template dokumen PO PT. Alpha Graha Computindo.'],
            ['po-eps', 'PO EPS', Opportunity::COMPANIES[1], 'Template dokumen PO PT. Elite Proxy Sistem.'],
            ['po-psi', 'PO PSI', Opportunity::COMPANIES[2], 'Template dokumen PO PT. Power Sistem Integrasi.'],
        ];

        foreach ($rows as [$code, $name, $category, $description]) {
            \Illuminate\Support\Facades\DB::table('crm_purchase_order_templates')->insert([
                'name' => $name,
                'code' => $code,
                'category' => $category,
                'description' => $description,
                'body_html' => $body,
                'is_active' => true,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_purchase_order_templates');
    }
};
