{{-- Laporan Sales Order AGC (shared preview + PDF) --}}
@php
    $soCode = $salesOrder->displayRefNumber()
        ?: (string) ($detail['nomor_ref'] ?? $salesOrder->nomor_ref ?? '')
        ?: $salesOrder->displayNumber();
    $docTitle = 'Sales Order';
    $soDate = $detail['so_date'] ?? $salesOrder->created_at;
    $soDateLabel = $soDate ? \Illuminate\Support\Carbon::parse($soDate)->translatedFormat('d M Y') : '';
    $requiredDelivery = (string) ($detail['required_delivery'] ?? '');
    $requiredDeliveryYmd = $requiredDelivery !== '' ? substr($requiredDelivery, 0, 10) : '';
    $poNumber = (string) ($detail['po_number'] ?? $salesOrder->po_number ?? '');
    $customerOrderRef = $poNumber;

    $billing = $detail['billing'] ?? [];
    $shipping = $detail['shipping'] ?? [];

    $billingName = (string) ($detail['customer'] ?? optional($opportunity ?? null)->company ?? '');
    $billingContact = (string) ($billing['contact'] ?? '');
    $billingPhone = (string) ($billing['phone'] ?? '');
    $billingAddress = (string) ($billing['address'] ?? '');
    $billingCity = (string) ($billing['city'] ?? '');
    $billingPostal = (string) ($billing['postal'] ?? '');

    $shippingContact = (string) ($shipping['contact'] ?? '');
    $shippingPhone = (string) ($shipping['phone'] ?? '');
    $shippingAddress = (string) ($shipping['address'] ?? '');
    $shippingCity = (string) ($shipping['city'] ?? '');
    $shippingPostal = (string) ($shipping['postal'] ?? '');

    $paymentRaw = (string) ($detail['payment'] ?? $salesOrder->payment ?? '');
    $paymentDays = 0;
    if (preg_match('/^top(\d+)$/i', $paymentRaw, $m)) {
        $paymentDays = (int) ($m[1] ?? 0);
    }
    $paymentLabel = $paymentDays.' Hari';

    $salesName = optional($salesOrder->creator)->display_name ?: '—';

    $fmt = function ($amount) {
        return number_format((float) ($amount ?? 0), 0, '.', '.');
    };

    $kopPath = public_path('images/kop-agc.png');
    $kopBase64 = file_exists($kopPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($kopPath))
        : '';
@endphp

<table style="width: 100%; border-collapse: collapse; border-bottom: 1px solid #424242; margin-bottom: 6px;">
    <tr>
        <td style="vertical-align: top; padding: 4px 0 8px;">
            @if ($kopBase64)
                <img src="{{ $kopBase64 }}" style="width: 420px; height: auto;">
            @endif
        </td>
        <td style="vertical-align: top; text-align: right; padding: 4px 0 8px;">
            <span style="font-size: 16px; font-weight: 700;">{{ $docTitle }}</span><br>
            <span>Nr</span><br>
            <span style="font-size: 14px; border-bottom: 1px solid #000000;">{{ $soCode }}</span>
        </td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; border-bottom: 1px solid #424242; margin-bottom: 6px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding: 6px 8px 10px 0;">
            <strong>SEND INVOICE TO:</strong><br>
            {{ $billingName }}<br>
            {{ $billingAddress }}<br>
            {{ $billingCity }}{{ $billingPostal !== '' ? ' - '.$billingPostal : '' }}<br>
            Attn. {{ $billingContact }}<br>
            {{ $billingPhone }}
        </td>
        <td style="width: 50%; vertical-align: top; padding: 6px 0 10px;">
            <table style="border: none; border-collapse: collapse;">
                <tr>
                    <td style="border: none; padding: 2px 8px 2px 0; vertical-align: top;"><strong>Date Order</strong></td>
                    </tr>
                    <tr>
                    <td style="border: none; padding: 2px 0; vertical-align: top;">{{ $soDateLabel }}</td>
                </tr>
                <tr>
                    <td style="border: none; padding: 2px 8px 2px 0; vertical-align: top;"><strong>CUSTOMER ORDER REF</strong></td>
                </tr>
                <tr>
                    <td style="border: none; padding: 2px 0; vertical-align: top;">{{ $customerOrderRef }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding: 6px 8px 10px 0;">
            <strong>DELIVER TO:</strong><br>
            Attn. {{ $shippingContact }}<br>
            {{ $shippingPhone }}<br>
            {{ $shippingAddress }}<br>
            {{ $shippingCity }}{{ $shippingPostal !== '' ? ' - '.$shippingPostal : '' }}
        </td>
        <td style="width: 50%; vertical-align: top; padding: 6px 0 10px;">
            <table style="border: none; border-collapse: collapse;">
                <tr>
                    <td style="border: none; padding: 2px 8px 2px 0; vertical-align: top;"><strong>REQUIRED DELIVERY</strong></td>
                </tr>
                <tr>
                    <td style="border: none; padding: 2px 0; vertical-align: top; border-bottom: 1px solid #000000;">{{ $requiredDeliveryYmd }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; border: 1px solid #424242;">
    <thead>
        <tr>
            <th style="border: 0.5px solid #424242; padding: 5px; width: 50px;">QTY</th>
            <th style="border: 0.5px solid #424242; padding: 5px;">DESCRIPTION</th>
            <th style="border: 0.5px solid #424242; padding: 5px; width: 140px;">NO.PO</th>
            <th style="border: 0.5px solid #424242; padding: 5px; width: 100px; text-align: right;">Price</th>
            <th style="border: 0.5px solid #424242; padding: 5px; width: 100px; text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($detail['items'] ?? []) as $item)
            <tr>
                <td style="border: 0.5px solid #424242; padding: 5px; text-align: center;">{{ (int) ($item['qty'] ?? 0) }}</td>
                <td style="border: 0.5px solid #424242; padding: 5px;">
                    @if (! empty($item['brand']))
                        <strong>{{ $item['brand'] }}</strong>
                    @endif
                    {{ $item['name'] ?? '—' }}
                    @if (! empty($item['category']))
                        <span style="display: block; font-size: 11px;">{{ $item['category'] }}</span>
                    @endif
                    @if (! empty($item['sku']))
                        [{{ $item['sku'] }}]
                    @endif
                </td>
                <td style="border: 0.5px solid #424242; padding: 5px;">{{ $customerOrderRef }}</td>
                <td style="border: 0.5px solid #424242; padding: 5px; text-align: right;">{{ $fmt($item['price_display'] ?? $item['price'] ?? 0) }}</td>
                <td style="border: 0.5px solid #424242; padding: 5px; text-align: right;">{{ $fmt($item['amount_display'] ?? $item['subtotal'] ?? 0) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4" style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right; font-weight: 700;">Total</td>
            <td style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right; font-weight: 700;">{{ $fmt($detail['subtotal_display'] ?? $detail['total_price_item'] ?? 0) }}</td>
        </tr>
        <tr>
            <td colspan="4" style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right; font-weight: 700;">PPN {{ (float) ($detail['ppn_percent'] ?? 11) }}%</td>
            <td style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right;">{{ $fmt($detail['ppn_amount'] ?? 0) }}</td>
        </tr>
        <tr>
            <td colspan="4" style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right; font-weight: 700;">Shipping Cost</td>
            <td style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right;">{{ $fmt($detail['shipping_price'] ?? 0) }}</td>
        </tr>
        <tr>
            <td colspan="4" style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right; font-weight: 800; font-size: 12px;">Grand Total</td>
            <td style="border: 0.5px solid #424242; padding: 4px 5px; text-align: right; font-weight: 800; font-size: 12px;">{{ $fmt($detail['grand_total'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #424242;">
    <tr>
        <td style="border: 1px solid #424242; padding: 8px; vertical-align: top; width: 33%;">
            <strong>TERMS OF PAYMENT :</strong><br>
            {{ $paymentLabel }}
        </td>
        <td style="border: 1px solid #424242; padding: 8px; vertical-align: top; width: 34%; text-align: center;">
            <strong>SALES REPRESENTATIVE :</strong><br>
            <br><br><br>
            {{ $salesName }}<br>
            MARKETING DEPARTMENT
        </td>
        <td style="border: 1px solid #424242; padding: 8px; vertical-align: top; width: 33%;">
            <strong>ORDERED BY :</strong><br>
            <br><br><br>
            {{-- {{ $customerOrderRef }} --}}
        </td>
    </tr>
    <tr>
        <td style="border: 1px solid #424242; padding: 8px; vertical-align: top; height: 60px;" rowspan="1"></td>
        <td style="border: 1px solid #424242; padding: 8px; vertical-align: bottom; text-align: center;">
            <br><br><br><br><br>
            ACCOUNTING DEPARTMENT
        </td>
        <td style="border: 1px solid #424242; padding: 8px; vertical-align: bottom;">
            DATE ORDERED
        </td>
    </tr>
    <tr>
        <td colspan="3" style="border: 1px solid #424242; padding: 8px; vertical-align: top; min-height: 60px;">
            <strong>NOTES :</strong><br>
            {{ $detail['note'] ?? $salesOrder->note ?? '' }}
        </td>
    </tr>
</table>

@php
    $specItems = collect($detail['items'] ?? []);
    $hasQuotationSpecs = $specItems->contains(fn ($item) => filled($item['description_html'] ?? '') || filled($item['image_src'] ?? ''));
@endphp
@if ($hasQuotationSpecs)
<div class="so-page-break"></div>

<table style="width: 100%; border-collapse: collapse; border-bottom: 1px solid #424242; margin-bottom: 6px;">
    <tr>
        <td style="vertical-align: top; padding: 4px 0 8px;">
            @if ($kopBase64)
                <img src="{{ $kopBase64 }}" style="width: 420px; height: auto;">
            @endif
        </td>
        <td style="vertical-align: top; text-align: right; padding: 4px 0 8px;">
            <span style="font-size: 16px; font-weight: 700;">{{ $docTitle }}</span><br>
            <span>Nr</span><br>
            <span style="font-size: 14px; border-bottom: 1px solid #000000;">{{ $soCode }}</span>
        </td>
    </tr>
</table>

<p style="font-size: 13px; font-weight: 700; margin: 8px 0 10px; text-transform: uppercase;">Spesifikasi Produk</p>

<table style="width: 100%; border-collapse: collapse; border: 1px solid #424242;">
    <thead>
        <tr>
            <th style="border: 0.5px solid #424242; padding: 5px; width: 36px;">No.</th>
            <th style="border: 0.5px solid #424242; padding: 5px;">Spesifikasi</th>
            <th style="border: 0.5px solid #424242; padding: 5px; width: 90px;">Gambar</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($specItems as $i => $item)
            <tr>
                <td style="border: 0.5px solid #424242; padding: 6px; text-align: center; vertical-align: top;">{{ $i + 1 }}</td>
                <td style="border: 0.5px solid #424242; padding: 6px; vertical-align: top;">
                    @if (! empty($item['brand']))
                        <strong>{{ $item['brand'] }} - </strong>{{ $item['name'] ?? '—' }}
                    @else
                        <strong>{{ $item['name'] ?? '—' }}</strong>
                    @endif
                    @if (! empty($item['category']))
                        <span style="display: block; font-size: 10px; color: #444;">{{ $item['category'] }}</span>
                    @endif
                    @if (! empty($item['description_html']))
                        <div class="so-spec-html" style="margin-top: 6px; font-size: 10px; line-height: 1.35;">
                            {!! $item['description_html'] !!}
                        </div>
                    @endif
                </td>
                <td style="border: 0.5px solid #424242; padding: 6px; text-align: center; vertical-align: middle;">
                    @if (! empty($item['image_src']))
                        <img src="{{ $item['image_src'] }}" alt="" style="max-height: 90px; max-width: 80px; object-fit: contain;">
                    @else
                        —
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
