<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview {{ $quotation->number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font-family: 'Helvetica Neue', Arial, sans-serif; }
        .toolbar {
            position: sticky; top: 0; display: flex; align-items: center; gap: 12px;
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
            background: #fff; max-width: 800px; margin: 28px auto; padding: 48px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15); border-radius: 4px;
        }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .paper { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('quotations.show', $quotation) }}" class="btn-light"><i class="bi bi-arrow-left"></i> Kembali</a>
        <strong>{{ $quotation->number }}</strong>
        <div class="spacer"></div>
        <button onclick="window.print()" class="btn-light"><i class="bi bi-printer"></i> Cetak</button>
        <a href="{{ route('quotations.pdf', $quotation) }}" class="btn-primary"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
    </div>
    <div class="paper">
        {!! $html !!}
    </div>
</body>
</html>
