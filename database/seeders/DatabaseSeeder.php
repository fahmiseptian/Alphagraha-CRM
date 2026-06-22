<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Pengguna berasal dari tabel `user` EspoCRM (tidak di-seed di sini).
        $this->call([
            QuotationTemplateSeeder::class,
        ]);
    }
}
