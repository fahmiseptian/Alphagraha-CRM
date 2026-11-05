<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order {{ $purchaseOrder->number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
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
        .btn-primary { background: #2563eb; color: #fff; }
        .paper {
            background: #fff; width: 210mm; min-height: 297mm; margin: 24px auto; padding: 14mm 12mm;
            box-shadow: 0 10px 30px rgba(0,0,0,.15);
        }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .paper { box-shadow: none; margin: 0; width: auto; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('opportunities.show', $opportunity) }}#purchase-orders" class="btn-light"><i class="bi bi-arrow-left"></i> Back</a>
        <strong>Purchase Order {{ $purchaseOrder->number }}</strong>
        <span class="spacer"></span>
        <button type="button" class="btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
    </div>
    <div class="paper">
        {!! $html !!}
    </div>
</body>
</html>
