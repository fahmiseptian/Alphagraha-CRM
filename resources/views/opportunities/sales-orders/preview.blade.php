<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview Sales Order — {{ $soCode }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font-family: "Times New Roman", Times, serif; }
        .toolbar { font-family: Inter, Arial, sans-serif; }
   
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
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .paper {
            background: #fff; max-width: 980px; margin: 28px auto; padding: 36px 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15); border-radius: 4px;
        }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .paper { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        }

        @include('opportunities.sales-orders._report-styles')

        @media screen {
            .so-page-break {
                display: block;
                height: 28px;
                margin: 36px -40px;
                background: #e2e8f0;
                border-top: 1px dashed #94a3b8;
                border-bottom: 1px dashed #94a3b8;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('opportunities.sales-orders.show', [$opportunity, $salesOrder]) }}" class="btn-light">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <strong>Sales Order {{ $soCode }}</strong>
        <div class="spacer"></div>
        <button type="button" onclick="window.print()" class="btn-light"><i class="bi bi-printer"></i> Print</button>
        <a href="{{ route('opportunities.sales-orders.pdf', [$opportunity, $salesOrder]) }}" class="btn-primary">
            <i class="bi bi-file-earmark-pdf"></i> Download PDF
        </a>
    </div>
    <div class="paper">
        @include('opportunities.sales-orders._report', ['detail' => $detail, 'salesOrder' => $salesOrder])
    </div>
</body>
</html>
