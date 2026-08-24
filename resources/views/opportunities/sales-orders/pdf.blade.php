<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 28px; }
        @include('opportunities.sales-orders._report-styles')
    </style>
</head>
<body>
    @include('opportunities.sales-orders._report', ['detail' => $detail, 'salesOrder' => $salesOrder])
</body>
</html>
