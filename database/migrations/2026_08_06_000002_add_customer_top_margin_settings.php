<?php

use App\Models\CrmSetting;
use App\Support\CustomerTop;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            CustomerTop::CASH => 'Minimal Margin Cash (%)',
            CustomerTop::DAYS_7 => 'Minimal Margin TOP 7 Hari (%)',
            CustomerTop::DAYS_14 => 'Minimal Margin TOP 14 Hari (%)',
            CustomerTop::DAYS_30 => 'Minimal Margin TOP 30 Hari (%)',
            CustomerTop::DAYS_45 => 'Minimal Margin TOP 45 Hari (%)',
            CustomerTop::DAYS_60 => 'Minimal Margin TOP 60 Hari (%)',
        ];

        foreach (CustomerTop::DEFAULT_MARGINS as $top => $percent) {
            CrmSetting::set(CustomerTop::settingKey($top), round((float) $percent, 2), [
                'type' => 'number',
                'group' => 'customer_top',
                'label' => $labels[$top] ?? 'Minimal Margin TOP (%)',
                'description' => 'Minimal margin opportunity (%) untuk customer dengan TOP '.CustomerTop::label($top).'.',
            ]);
        }
    }

    public function down(): void
    {
        foreach (CustomerTop::OPTIONS as $top) {
            CrmSetting::query()->where('key', CustomerTop::settingKey($top))->delete();
        }
    }
};
