<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Entertainment — {{ $opportunity->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-crm.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font-family: Inter, Arial, sans-serif; color: #1e293b; }
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 12px;
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
            background: #fff; max-width: 980px; margin: 28px auto; padding: 36px 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15); border-radius: 4px;
        }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .subtitle { margin: 0 0 24px; color: #64748b; font-size: 13px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 24px; font-size: 13px; }
        .meta dt { color: #64748b; }
        .meta dd { margin: 2px 0 0; font-weight: 600; }
        .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .summary-box { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; }
        .summary-box .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 600; }
        .summary-box .val { margin-top: 4px; font-size: 16px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 1px solid #e2e8f0; padding: 8px 6px; }
        td { border-bottom: 1px solid #f1f5f9; padding: 10px 6px; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #fffbeb; color: #b45309; }
        .badge-complete { background: #ecfdf5; color: #047857; }
        tfoot td { border-bottom: none; padding-top: 12px; font-weight: 700; }
        .empty { text-align: center; color: #94a3b8; padding: 32px 0; }
        .desc { color: #64748b; font-size: 12px; margin-top: 2px; white-space: pre-line; }
        .muted { color: #94a3b8; font-size: 11px; }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .paper { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        }
        @media (max-width: 700px) {
            .summary, .meta { grid-template-columns: 1fr; }
            .paper { margin: 12px; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('opportunities.show', $opportunity) }}" class="btn-light"><i class="bi bi-arrow-left"></i> Back</a>
        <strong>Laporan Entertainment</strong>
        <div class="spacer"></div>
        <button type="button" onclick="window.print()" class="btn-light"><i class="bi bi-printer"></i> Print</button>
    </div>
    <div class="paper">
        <h1>Laporan Entertainment</h1>
        <p class="subtitle">{{ $opportunity->name }}</p>

        <dl class="meta">
            <div>
                <dt>Customer</dt>
                <dd>{{ optional($opportunity->account)->name ?: ($opportunity->company ?: '—') }}</dd>
            </div>
            <div>
                <dt>Sales</dt>
                <dd>{{ optional($opportunity->assignedUser)->display_name ?: '—' }}</dd>
            </div>
            <div>
                <dt>Stage</dt>
                <dd>{{ $opportunity->stage ?: '—' }}</dd>
            </div>
            <div>
                <dt>Jumlah item</dt>
                <dd>{{ $items->count() }}</dd>
            </div>
        </dl>

        <div class="summary">
            <div class="summary-box">
                <div class="lbl">Total semua</div>
                <div class="val">{{ money($total, $currency) }}</div>
            </div>
            <div class="summary-box">
                <div class="lbl">Pending</div>
                <div class="val" style="color:#b45309">{{ money($totalPending, $currency) }}</div>
            </div>
            <div class="summary-box">
                <div class="lbl">Complete</div>
                <div class="val" style="color:#047857">{{ money($totalComplete, $currency) }}</div>
            </div>
        </div>

        @if ($items->count())
            <table>
                <thead>
                    <tr>
                        <th style="width:36px">No</th>
                        <th>Keperluan</th>
                        <th>Status</th>
                        <th>Oleh</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $i => $item)
                        <tr>
                            <td class="muted">{{ $i + 1 }}</td>
                            <td>
                                <strong>{{ $item->name }}</strong>
                                @if ($item->description)
                                    <div class="desc">{{ $item->description }}</div>
                                @endif
                                <div class="muted">{{ $item->created_at?->translatedFormat('d M Y H:i') }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $item->isComplete() ? 'badge-complete' : 'badge-pending' }}">{{ $item->statusLabel() }}</span>
                                @if ($item->isComplete())
                                    <div class="muted">{{ optional($item->completedByUser)->display_name ?: '—' }}</div>
                                @endif
                            </td>
                            <td>{{ optional($item->creator)->display_name ?: '—' }}</td>
                            <td class="num">{{ money($item->amount, $currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Total</td>
                        <td class="num">{{ money($total, $currency) }}</td>
                    </tr>
                </tfoot>
            </table>
        @else
            <p class="empty">Belum ada entertainment pada opportunity ini.</p>
        @endif
    </div>
</body>
</html>
