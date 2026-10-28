<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export laporan Account Receivable ke Excel.
 */
class AccountReceivableExcel
{
    public const HEADERS = [
        'Invoice Date',
        'Invoice No.',
        'SO No.',
        'PO No.',
        'Customer Name',
        'Invoice Amount (Include)',
        'Invoice Amount (Exclude)',
        'Terms Net Days',
        'Salesman Name',
        'Due Date',
        'Overdue (hari)',
    ];

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public static function export(Collection $rows, string $filename): StreamedResponse
    {
        $data = $rows->map(fn (array $row) => [
            $row['invoice_at']?->format('d/m/Y') ?? '',
            $row['invoice_no'],
            $row['so_number'],
            $row['po_number'],
            $row['customer'],
            $row['amount_include'],
            $row['amount_exclude'],
            $row['term_days'],
            $row['salesman'],
            $row['due_at']?->format('d/m/Y') ?? '',
            $row['overdue_days'],
        ])->all();

        $data[] = [
            '', '', '', '', 'TOTAL',
            (float) $rows->sum('amount_include'),
            (float) $rows->sum('amount_exclude'),
            '', '', '', '',
        ];

        return CatalogExcel::downloadCustom($filename, self::HEADERS, $data, 'Account Receivable');
    }
}
