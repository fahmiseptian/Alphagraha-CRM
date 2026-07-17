<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, number, boolean, json
            $table->string('group', 50)->default('general');
            $table->string('label')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('crm_settings')->insert([
            [
                'key' => 'tax.ppn_percent',
                'value' => '11',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PPN (%)',
                'description' => 'Persentase PPN untuk harga include/exclude dan penawaran.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'tax.pph_percent',
                'value' => '2',
                'type' => 'number',
                'group' => 'tax',
                'label' => 'PPH (%)',
                'description' => 'Persentase PPH (mis. jasa / wapu) pada perhitungan margin opportunity.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_settings');
    }
};
