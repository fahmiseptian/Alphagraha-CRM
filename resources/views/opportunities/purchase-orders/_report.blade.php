{{-- Laporan rekap PO per opportunity (shared preview + PDF) --}}
@php
    $s = $report['summary'];
    $fmt = function ($amount) {
        if ($amount === null || $amount === '') {
            return '';
        }
        return number_format((float) $amount, 0, ',', '.');
    };
    $fmtPct = function ($pct) {
        if ($pct === null || $pct === '') {
            return '';
        }
        return number_format((float) $pct, 2, ',', '.').'%';
    };
@endphp

<div class="po-report">
    <table class="po-meta">
        <tr>
            <td class="po-meta-left">
                <table class="po-meta-fields">
                    <tr>
                        <td class="lbl">Tgl Invoice</td>
                        <td class="sep">:</td>
                        <td class="val blank">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="lbl">No Invoice</td>
                        <td class="sep">:</td>
                        <td class="val blank">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="lbl">Sales</td>
                        <td class="sep">:</td>
                        <td class="val">{{ $report['sales_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Customer</td>
                        <td class="sep">:</td>
                        <td class="val">{{ $report['customer_name'] }}</td>
                    </tr>
                </table>
            </td>
            <td class="po-meta-right">
                <div class="payment-term-box">&nbsp;</div>
            </td>
        </tr>
    </table>

    <table class="po-summary">
        <thead>
            <tr>
                <th class="col-label"></th>
                <th>Nilai Incl PPN</th>
                <th>Nilai Excl PPN</th>
                <th>PPh 23</th>
                <th>Terima Uang</th>
                <th>Persentase</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="col-label">Nilai Jual</td>
                <td class="num">{{ $fmt($s['nilai_jual_incl']) }}</td>
                <td class="num">{{ $fmt($s['nilai_jual_excl']) }}</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
            </tr>
            <tr>
                <td class="col-label">Modal</td>
                <td class="num">{{ $fmt($s['modal_incl']) }}</td>
                <td class="num">{{ $fmt($s['modal_excl']) }}</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="num">{{ $fmtPct($s['margin_percent']) }}</td>
            </tr>
            <tr>
                <td class="col-label">Jual Ongkir</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
            </tr>
            <tr>
                <td class="col-label">Modal Ongkir</td>
                <td class="num">{{ $fmt($s['modal_ongkir_incl']) }}</td>
                <td class="num">{{ $fmt($s['modal_ongkir_excl']) }}</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
            </tr>
            <tr>
                <td class="col-label">DISKON</td>
                <td class="num">{{ $fmt($s['diskon_incl']) }}</td>
                <td class="num">{{ $fmt($s['diskon_excl']) }}</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="num">{{ $fmtPct($s['diskon_percent']) }}</td>
            </tr>
            <tr>
                <td class="col-label">Profit Barang</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="num">{{ $fmt($s['profit_barang']) }}</td>
                <td class="blank">&nbsp;</td>
            </tr>
            <tr>
                <td class="col-label">Profit Ongkir</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="num">{{ $s['modal_ongkir_excl'] !== null || $s['jual_ongkir_excl'] !== null ? $fmt($s['profit_ongkir']) : '' }}</td>
                <td class="blank">&nbsp;</td>
            </tr>
            <tr class="row-total">
                <td class="col-label">Total Profit</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="blank">&nbsp;</td>
                <td class="num">{{ $fmt($s['total_profit']) }}</td>
                <td class="num">{{ $fmtPct($s['margin_percent'] !== null && $s['total_profit'] != 0 ? 100 : null) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="margin-banner">{{ $fmtPct($s['margin_percent']) }}</div>

    <table class="po-settled">
        <tr>
            <td class="lbl">Tgl Lunas</td>
            <td class="sep">:</td>
            <td class="val blank">&nbsp;</td>
        </tr>
    </table>

    <table class="po-detail">
        <thead>
            <tr>
                <th rowspan="2" class="col-no">NO</th>
                <th rowspan="2" class="col-po">PO</th>
                <th rowspan="2" class="col-vendor">VENDOR</th>
                <th>HARGA EXCL</th>
                <th>TAMBAHAN</th>
                <th>JUMLAH</th>
                <th>HARGA INCL</th>
                <th>TAMBAHAN</th>
                <th>JUMLAH</th>
            </tr>
            <tr>
                <th>(A)</th>
                <th>(EXCL) (B)</th>
                <th>(A + B)</th>
                <th>(C)</th>
                <th>(INCL) (D)</th>
                <th>(C + D)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['po_rows'] as $row)
                <tr>
                    <td class="col-no">{{ $row['no'] }}</td>
                    <td class="col-po">{{ $row['number'] }}</td>
                    <td class="col-vendor">{{ $row['vendor'] ?? '—' }}</td>
                    <td class="num">{{ $fmt($row['harga_exclude']) }}</td>
                    <td class="num">{{ $fmt($row['extra_exclude']) }}</td>
                    <td class="num">{{ $fmt($row['jumlah_exclude']) }}</td>
                    <td class="num">{{ $fmt($row['harga_include']) }}</td>
                    <td class="num">{{ $fmt($row['extra_include']) }}</td>
                    <td class="num">{{ $fmt($row['jumlah_include']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="empty">Belum ada Purchase Order</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="total-label">TOTAL EXCLUDE PPN</td>
                <td colspan="2"></td>
                <td class="num total-val">{{ $fmt($report['po_total_exclude']) }}</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td colspan="3" class="total-label">TOTAL INCLUDE PPN</td>
                <td colspan="5"></td>
                <td class="num total-val">{{ $fmt($report['po_total_include']) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($report['has_surcharge'] ?? $report['has_cash'] ?? false)
        @php
            $cashPct = rtrim(rtrim(number_format((float) ($report['surcharge_cash_percent'] ?? 1), 2, ',', '.'), '0'), ',');
            $topPct = rtrim(rtrim(number_format((float) ($report['surcharge_top_percent'] ?? 0), 2, ',', '.'), '0'), ',');
        @endphp
        <p class="po-note">
            <strong>Note :</strong>
            biaya tambahan modal sesuai setting —
            Cash +{{ $cashPct }}%, TOP +{{ $topPct }}%.
        </p>
    @endif
</div>
