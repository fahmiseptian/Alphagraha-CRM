<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Receivable</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-crm.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
            background: #1e293b; color: #fff; padding: 12px 20px;
        }
        .toolbar .spacer { flex: 1; }
        .toolbar a, .toolbar button {
            display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
            border: none; border-radius: 8px; padding: 8px 14px; font-size: 14px; text-decoration: none;
        }
        .btn-light { background: rgba(255,255,255,.15); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.25); }
        .paper {
            background: #fff; max-width: 1280px; margin: 24px auto; padding: 28px 32px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15); border-radius: 4px;
        }
        .head { text-align: center; margin-bottom: 18px; }
        .head .title { margin: 2px 0; font-size: 24px; font-weight: 700; color: #7f1d1d; }
        .head .range { margin: 0; font-size: 13px; font-weight: 700; }
        .head .filters { margin: 6px 0 0; font-size: 12px; color: #64748b; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
        .summary-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
        .summary-box .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 600; }
        .summary-box .val { margin-top: 4px; font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .summary-box.danger .val { color: #b91c1c; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #475569; padding: 4px 6px; }
        th { color: #1e3a8a; font-size: 13px; font-weight: 700; text-align: center; vertical-align: middle; background: #fff; }
        td { white-space: nowrap; }
        td.wrap { white-space: normal; min-width: 180px; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .center { text-align: center; }
        .overdue { background: #fef08a; }
        .muted { color: #64748b; font-size: 10px; display: block; }
        tfoot td { font-weight: 700; background: #f8fafc; }
        .empty { text-align: center; color: #94a3b8; padding: 32px 0; }
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            .toolbar { display: none !important; }
            body { background: #fff; }
            .paper { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            .overdue, tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @media (max-width: 800px) {
            .summary { grid-template-columns: 1fr 1fr; }
            .paper { margin: 12px; padding: 16px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $backUrl ?? route('opportunities.index', $filterQuery) }}" class="btn-light"><i class="bi bi-arrow-left"></i> Back</a>
        <strong>Account Receivable</strong>
        <div class="spacer"></div>
        <a href="{{ $exportUrl ?? route('opportunities.account-receivable', $filterQuery + ['format' => 'xlsx']) }}" class="btn-light"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <button type="button" onclick="window.print()" class="btn-light"><i class="bi bi-printer"></i> Print</button>
    </div>

    <div class="paper">
        <div class="head">
            <p class="title">Account Receivable</p>
            <p class="range">
                @if ($rangeStart)
                    From {{ $rangeStart->translatedFormat('d M Y') }} to {{ $rangeEnd->translatedFormat('d M Y') }}
                @else
                    Per {{ $rangeEnd->translatedFormat('d M Y') }}
                @endif
            </p>
            @if ($filterSummary)
                <p class="filters">{{ implode(' · ', $filterSummary) }}</p>
            @endif
        </div>

        <div class="summary">
            <div class="summary-box">
                <div class="lbl">Jumlah invoice belum dibayar</div>
                <div class="val">{{ number_format($totals['count']) }}</div>
            </div>
            <div class="summary-box">
                <div class="lbl">Total piutang (include)</div>
                <div class="val">{{ money($totals['amount_include']) }}</div>
            </div>
            <div class="summary-box">
                <div class="lbl">Total piutang (exclude)</div>
                <div class="val">{{ money($totals['amount_exclude']) }}</div>
            </div>
            <div class="summary-box danger">
                <div class="lbl">Lewat jatuh tempo ({{ $totals['overdue_count'] }})</div>
                <div class="val">{{ money($totals['overdue_amount']) }}</div>
            </div>
        </div>

        @if ($rows->isEmpty())
            <p class="empty">Tidak ada Sales Order ber-invoice yang belum dibayar untuk filter ini.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice<br>Date</th>
                            <th>Invoice No.</th>
                            <th>SO No.</th>
                            <th>PO No.</th>
                            <th>Customer Name</th>
                            <th>Invoice<br>Amount<br>(Include)</th>
                            <th>Invoice<br>Amount<br>(Exclude)</th>
                            <th>Terms<br>Net Days</th>
                            <th>Salesman Name</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="center">{{ $row['invoice_at']?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $row['invoice_no'] ?: '—' }}</td>
                                <td><a href="{{ route('opportunities.show', $row['opportunity_id']) }}" style="color: inherit">{{ $row['so_number'] }}</a></td>
                                <td>{{ $row['po_number'] ?: '—' }}</td>
                                <td class="wrap">{{ $row['customer'] ?: '—' }}</td>
                                <td class="num">{{ number_format($row['amount_include'], 0, ',', '.') }}</td>
                                <td class="num">{{ number_format($row['amount_exclude'], 0, ',', '.') }}</td>
                                <td class="center">{{ $row['term_days'] }}</td>
                                <td class="center">{{ $row['salesman'] }}</td>
                                <td @class(['center', 'overdue' => $row['is_overdue']])>
                                    {{ $row['due_at']?->format('d/m/Y') ?? '—' }}
                                    @if ($row['is_overdue'])
                                        <span class="muted">lewat {{ $row['overdue_days'] }} hari</span>
                                    @elseif ($row['due_estimated'])
                                        <span class="muted">tgl invoice kosong, dari tgl SO</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="num">TOTAL</td>
                            <td class="num">{{ number_format($totals['amount_include'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($totals['amount_exclude'], 0, ',', '.') }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</body>
</html>
