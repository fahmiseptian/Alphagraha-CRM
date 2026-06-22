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
];
