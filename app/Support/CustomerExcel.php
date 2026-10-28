<?php

namespace App\Support;

use App\Models\Espo\Account;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export daftar customer ke Excel.
 */
class CustomerExcel
{
    public const HEADERS = [
        'Nama',
        'Tipe',
        'Industri',
        'Email',
        'Telepon',
        'Website',
        'Alamat',
        'Kecamatan',
        'Kota',
        'Provinsi',
        'Kode Pos',
        'Negara',
        'Level Pembayaran',
        'TOP',
        'Sales',
        'Jumlah Deal',
        'Keterangan',
    ];

    /**
     * @param  iterable<Account>  $accounts
     */
    public static function export(iterable $accounts, string $filename = 'customers.xlsx'): StreamedResponse
    {
        $rows = [];
        foreach ($accounts as $account) {
            $rows[] = [
                (string) ($account->name ?? ''),
                (string) ($account->type ?? ''),
                (string) ($account->industry ?? ''),
                (string) ($account->email ?? ''),
                (string) ($account->phone ?? ''),
                (string) ($account->website ?? ''),
                (string) ($account->billing_address_street ?? ''),
                (string) ($account->crm_billing_district ?? ''),
                (string) ($account->billing_address_city ?? ''),
                (string) ($account->billing_address_state ?? ''),
                (string) ($account->billing_address_postal_code ?? ''),
                (string) ($account->billing_address_country ?? ''),
                $account->paymentLevelLabel(),
                $account->topLabel(),
                (string) (optional($account->assignedUser)->display_name ?: ''),
                (int) ($account->opportunities_count ?? 0),
                (string) ($account->description ?? ''),
            ];
        }

        return CatalogExcel::downloadCustom($filename, self::HEADERS, $rows, 'Customers');
    }
}
