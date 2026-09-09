<?php

use App\Models\CrmSetting;
use App\Support\CustomerTop;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CrmSetting::set(CustomerTop::settingKey(CustomerTop::CASH), CustomerTop::minMarginPercent(CustomerTop::CASH), [
            'type' => 'number',
            'group' => 'customer_top',
            'label' => 'Minimal Margin CBD (%)',
            'description' => 'Minimal margin opportunity (%) untuk customer dengan TOP '.CustomerTop::label(CustomerTop::CASH).'.',
        ]);

        CrmSetting::set(CustomerTop::settingKey(CustomerTop::COD), CustomerTop::DEFAULT_MARGINS[CustomerTop::COD], [
            'type' => 'number',
            'group' => 'customer_top',
            'label' => 'Minimal Margin COD (%)',
            'description' => 'Minimal margin opportunity (%) untuk customer dengan TOP '.CustomerTop::label(CustomerTop::COD).'.',
        ]);
    }

    public function down(): void
    {
        CrmSetting::query()->where('key', CustomerTop::settingKey(CustomerTop::COD))->delete();

        CrmSetting::set(CustomerTop::settingKey(CustomerTop::CASH), CustomerTop::minMarginPercent(CustomerTop::CASH), [
            'type' => 'number',
            'group' => 'customer_top',
            'label' => 'Minimal Margin Cash (%)',
            'description' => 'Minimal margin opportunity (%) untuk customer dengan TOP Cash.',
        ]);
    }
};
