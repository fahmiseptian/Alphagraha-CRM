<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        @include('opportunities.purchase-orders._report-styles')
    </style>
</head>
<body>
    @include('opportunities.purchase-orders._report', ['report' => $report])
</body>
</html>
