<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pastikan crm_activities.created_by & assigned_to bertipe VARCHAR(24)
 * agar cocok dengan ID user EspoCRM (bukan integer legacy crm_users).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_activities')) {
            return;
        }

        $this->ensureVarcharUserColumn('crm_activities', 'created_by');
        $this->ensureVarcharUserColumn('crm_activities', 'assigned_to');
    }

    public function down(): void
    {
        // Tidak dikembalikan ke BIGINT — data Espo id string tidak bisa di-cast aman.
    }

    protected function ensureVarcharUserColumn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $type = $this->columnType($table, $column);
        if ($type === null || str_contains($type, 'varchar') || str_contains($type, 'char')) {
            return;
        }

        // Lepas FK bila masih menunjuk ke tabel lama.
        $this->dropForeignKeysOn($table, $column);

        // Null-kan nilai numerik lama yang tidak valid sebagai Espo id.
        DB::table($table)
            ->whereNotNull($column)
            ->whereRaw("{$column} REGEXP '^[0-9]+$'")
            ->update([$column => null]);

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(24) NULL");
    }

    protected function columnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            [$table, $column]
        );

        if (! $row) {
            return null;
        }

        return strtolower((string) ($row->column_type ?: $row->data_type));
    }

    protected function dropForeignKeysOn(string $table, string $column): void
    {
        $keys = DB::select(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($keys as $key) {
            $name = (string) ($key->name ?? '');
            if ($name === '') {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }
};
