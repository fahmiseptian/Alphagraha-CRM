<?php

namespace App\Support;

use App\Models\OpportunitySalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export daftar Sales Order ke Excel.
 */
class SalesOrderExcel
{
    /**
     * @param  Collection<int, OpportunitySalesOrder>|iterable<OpportunitySalesOrder>  $salesOrders
     * @param  array{
     *     canSeeInvoice?: bool,
     *     showCommissionOwner?: bool,
     *     showPoStatus?: bool,
     *     canEditDelivery?: bool
     * }  $columns
     */
    public static function export(iterable $salesOrders, array $columns, string $filename): StreamedResponse
    {
        $canSeeInvoice = (bool) ($columns['canSeeInvoice'] ?? false);
        $showCommissionOwner = (bool) ($columns['showCommissionOwner'] ?? false);
        $showPoStatus = (bool) ($columns['showPoStatus'] ?? false);
        $canEditDelivery = (bool) ($columns['canEditDelivery'] ?? false);

        $headers = ['No. SO', 'PSO', 'No. Referensi', 'PO Customer', 'Pembayaran'];
        if ($canSeeInvoice) {
            array_push($headers, 'Invoice', 'Tanggal Invoice', 'Status Bayar');
        }
        $headers[] = 'Customer';
        $headers[] = 'Perusahaan';
        if ($showCommissionOwner) {
            $headers[] = 'Pemilik Komisi';
        }
        if ($showPoStatus) {
            $headers[] = 'Data PO';
        }
        if ($canEditDelivery) {
            array_push($headers, 'File DO', 'File BAST', 'No. Resi', 'Metode Kirim');
        }
        $headers[] = 'Dibuat';

        $rows = [];
        foreach ($salesOrders as $so) {
            $row = [
                $so->displayNumber(),
                $so->displayPsoNumber(),
                $so->displayRefNumber(),
                $so->displayPoNumber(),
                $so->paymentLabel() === '—' ? '' : $so->paymentLabel(),
            ];

            if ($canSeeInvoice) {
                $invoiceNo = trim((string) ($so->payloadValue('invoice_no') ?? ''));
                $invoiceDt = self::invoiceDate($so);
                $payStatus = strtolower(trim((string) ($so->statusSnapshot()['payment_status'] ?? ''))) ?: 'unpaid';
                $row[] = $invoiceNo;
                $row[] = $invoiceDt?->format('d/m/Y') ?? '';
                $row[] = in_array($payStatus, ['paid', 'settlement'], true) ? 'Paid' : 'Unpaid';
            }

            $row[] = (string) ($so->opportunity?->account?->name ?: '');
            $row[] = (string) ($so->opportunity?->company ?: '');

            if ($showCommissionOwner) {
                $row[] = $so->commissionOwnerName() === '—' ? '' : $so->commissionOwnerName();
            }

            if ($showPoStatus) {
                $poCount = (int) ($so->purchase_orders_count ?? 0);
                $row[] = $poCount > 0 ? 'Ada PO · '.$poCount : 'Belum ada PO';
            }

            if ($canEditDelivery) {
                $row[] = trim((string) ($so->payloadValue('file_do') ?? ''));
                $row[] = trim((string) ($so->payloadValue('foto_serah_terima') ?? ''));
                $row[] = trim((string) ($so->payloadValue('no_resi') ?: $so->payloadValue('resi') ?: ''));
                $row[] = trim((string) ($so->payloadValue('courier_name') ?: $so->payloadValue('shipping_name') ?: ''));
            }

            $row[] = $so->created_at?->format('d/m/Y H:i') ?? '';
            $rows[] = $row;
        }

        return CatalogExcel::downloadCustom($filename, $headers, $rows, 'Sales Orders');
    }

    protected static function invoiceDate(OpportunitySalesOrder $so): ?Carbon
    {
        $raw = $so->payloadValue('invoice_dt') ?? $so->payloadValue('invoiced_dt') ?? $so->payloadValue('invoice_date');
        if (! filled($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
