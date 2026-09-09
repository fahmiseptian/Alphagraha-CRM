<?php

use App\Models\CrmSetting;
use App\Support\CustomerTop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai TOP customer `cash` di database diganti menjadi `CBD`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceTopValue('cash', CustomerTop::CASH);
        $this->replaceTopValue('cbd', CustomerTop::CASH);

        $oldKey = 'customer_top.margin_cash';
        $newKey = CustomerTop::settingKey(CustomerTop::CASH);
        $old = CrmSetting::query()->where('key', $oldKey)->first();
        if ($old) {
            $existing = CrmSetting::query()->where('key', $newKey)->first();
            if ($existing && $existing->id !== $old->id) {
                $old->delete();
            } else {
                $old->key = $newKey;
                $old->label = 'Minimal Margin CBD (%)';
                $old->description = 'Minimal margin opportunity (%) untuk customer dengan TOP CBD.';
                $old->save();
            }
            CrmSetting::forgetCache();
        }
    }

    public function down(): void
    {
        $this->replaceTopValue(CustomerTop::CASH, 'cash');

        $oldKey = 'customer_top.margin_cash';
        $newKey = CustomerTop::settingKey(CustomerTop::CASH);
        $row = CrmSetting::query()->where('key', $newKey)->first();
        if ($row && $newKey !== $oldKey) {
            $row->key = $oldKey;
            $row->label = 'Minimal Margin Cash (%)';
            $row->save();
            CrmSetting::forgetCache();
        }
    }

    protected function replaceTopValue(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $columns = [
            ['account', 'crm_top'],
            ['opportunity', 'crm_top'],
            ['crm_vendors', 'top'],
            ['crm_purchase_order_item_vendors', 'top'],
        ];

        foreach ($columns as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereRaw('LOWER('.$column.') = ?', [strtolower($from)])
                ->update([$column => $to]);
        }
    }
};
