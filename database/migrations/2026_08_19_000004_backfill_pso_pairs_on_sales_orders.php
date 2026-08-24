<?php

use App\Services\SalesOrderService;
use Illuminate\Database\Migrations\Migration;

/**
 * Setiap SO otomatis punya pasangan PSO (prefix berbeda, urutan sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SalesOrderService::class)->backfillPsoPairs();
    }

    public function down(): void
    {
        //
    }
};
