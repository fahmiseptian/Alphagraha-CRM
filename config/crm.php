<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mata uang default
    |--------------------------------------------------------------------------
    | Dipakai sebagai nilai awal saat membuat penawaran baru.
    */
    'default_currency' => env('CRM_DEFAULT_CURRENCY', 'IDR'),

    /*
    |--------------------------------------------------------------------------
    | Salt password EspoCRM
    |--------------------------------------------------------------------------
    | Diambil dari data/config.php (passwordSalt) instalasi EspoCRM.
    | Dipakai untuk memverifikasi login terhadap tabel `user` EspoCRM.
    */
    'espo_password_salt' => env('ESPO_PASSWORD_SALT', '$6$54f4818aae49b0e4$'),

    /*
    |--------------------------------------------------------------------------
    | Informasi perusahaan (dipakai pada dokumen penawaran)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('CRM_COMPANY_NAME', 'PT Alpha Graha Computindo'),
        'address' => env('CRM_COMPANY_ADDRESS', 'Jakarta, Indonesia'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Perusahaan untuk template penawaran (AGC / EPS / PSI)
    |--------------------------------------------------------------------------
    */
    'quotation_companies' => [
        'agc' => [
            'legal_name' => 'PT. Alpha Graha Computindo',
            'address' => 'Jakarta, Indonesia',
            'phone' => '(021) 1234-5678',
            'email' => 'info@alphagraha.co.id',
            'color' => '#2563eb',
        ],
        'eps' => [
            'legal_name' => 'PT. Elite Proxy Sistem',
            'address' => 'Sudirman Park Apartment, Kav. A-11, Jl. K.H. Mas Mansyur Kav. 35, Jakarta',
            'phone' => '(021) 5794-8922',
            'email' => 'info@eliteproxy.co.id',
            'color' => '#0ea5e9',
        ],
        'psi' => [
            'legal_name' => 'PT. Power Sistem Integrasi',
            'address' => 'Ruko Sudirman Park, Kav. A-11, Jl. K.H. Mas Mansyur Kav. 35, Jakarta',
            'phone' => '(021) 5794-8922',
            'email' => 'info@powersistem.co.id',
            'color' => '#16a34a',
        ],
    ],

    'quotation_company_map' => [
        'Alpha Graha' => 'agc',
        'Alpha Graha Computindo' => 'agc',
        'Elite Proxy' => 'eps',
        'Elite Proxy System' => 'eps',
        'Elite Proxy Sistem' => 'eps',
        'Power Sistem' => 'psi',
        'Power Sistem Integrasi' => 'psi',
    ],

    /*
    |--------------------------------------------------------------------------
    | URL EspoCRM lama (untuk unduh dokumen legacy)
    |--------------------------------------------------------------------------
    */
    'legacy_crm_url' => env('CRM_LEGACY_URL', 'https://crm.alphagraha.co.id'),

    /*
    |--------------------------------------------------------------------------
    | Pengingat deadline follow-up (hari ke depan)
    |--------------------------------------------------------------------------
    */
    'deadline_alert_days' => (int) env('CRM_DEADLINE_ALERT_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Timezone Google Calendar (TEMPLATE link)
    |--------------------------------------------------------------------------
    */
    'google_calendar_timezone' => env('CRM_GOOGLE_CALENDAR_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Format nomor penawaran otomatis
    |--------------------------------------------------------------------------
    | Contoh: 0002/KA/QO/VII/26
    */
    'quotation_number' => [
        'prefix' => 'QO',
        'sequence_pad' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Format nomor Sales Order otomatis
    |--------------------------------------------------------------------------
    | No SO:  SO26081800001  (prefix + YYMMDD + 5 digit urutan harian)
    | PSO:    PSO26081800001
    | Nomor ref: 0001/DP/SO/VIII/26 (atau /PSO/)
    */
    'sales_order_number' => [
        'prefix' => 'SO',
        'pso_prefix' => 'PSO',
        'sequence_pad' => 5,
        'ref_sequence_pad' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Format nomor Purchase Order (default, tetap bisa diedit)
    |--------------------------------------------------------------------------
    | Contoh: AGC/26/09/1367  (prefix / tahun 2 digit / bulan / nomor urut purchasing)
    */
    'purchase_order_number' => [
        'prefix' => env('CRM_PO_NUMBER_PREFIX', 'AGC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback tarif pajak (jika belum ada di DB crm_settings)
    |--------------------------------------------------------------------------
    */
    'tax' => [
        'ppn_percent' => (float) env('CRM_PPN_PERCENT', 11),
        'pph_percent' => (float) env('CRM_PPH_PERCENT', 2),
    ],
];
