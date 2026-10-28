@extends('layouts.app')
@section('title', 'Sales Orders')

@section('content')
    @php
        $canDeleteSo = auth()->user()?->canAccessAdministration() ?? false;
        $canSeeInvoice = (bool) ($canSeeInvoice ?? false);
        $canEditDelivery = (bool) ($canEditDelivery ?? false);
        $showPoStatus = ! (auth()->user()?->isEkspedisi() ?? false);
        $showCommissionOwner = ! (auth()->user()?->isEkspedisi() ?? false);
        $hasExtraFilters = ($search ?? '') !== ''
            || ($canSeeInvoice && ($invoiceSearch ?? '') !== '')
            || ($accountId ?? '') !== ''
            || ($companyFilter ?? '') !== ''
            || ($selectedUserId ?? '') !== ''
            || ($queue ?? '') !== '';
        $hasFilters = $hasExtraFilters || ($period ?? 'year') !== 'year';
        $queueLabels = [
            'needs_invoice' => 'Belum invoice',
            'unpaid' => 'Unpaid',
            'paid' => 'Sudah paid',
            'delivery_pending' => 'DO pending',
        ];
        $queueLabel = $queueLabels[$queue ?? ''] ?? null;
        $pageDesc = number_format($salesOrders->total()).' SO · '.($periodLabel ?? 'tahun ini')
            .($queueLabel ? ' · '.$queueLabel : '');
    @endphp

    <x-page-header title="Sales Orders" :description="$pageDesc">
        <x-slot:actions>
            @if (auth()->user()?->canViewSalesOrderLogs())
                <x-btn href="{{ route('sales-order-logs.index') }}" variant="secondary" icon="bi-journal-text">Log Sales Order</x-btn>
            @endif
            @if (auth()->user()?->canViewOpportunities())
                <x-btn href="{{ route('opportunities.index') }}" icon="bi bi-briefcase">Lihat Opportunity</x-btn>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4" :padding="false">
        <form method="GET" action="{{ route('sales-orders.index') }}" class="crm-opp-filters">
            <div @class([
                'crm-opp-filters__grid',
                'crm-opp-filters__grid--admin' => ($showCommissionOwner && ($canFilterSales ?? false)),
            ])>
                <div class="crm-opp-filters__field">
                    <label class="crm-label">Pencarian</label>
                    <div class="crm-search">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" value="{{ $search }}"
                               placeholder="{{ $canSeeInvoice ? 'No. SO, PSO, PO, invoice, referensi, customer...' : 'No. SO, PSO, PO, referensi, opportunity, customer, sales...' }}"
                               class="crm-field" autocomplete="off">
                    </div>
                </div>

                @if ($canSeeInvoice)
                    <div class="crm-opp-filters__field">
                        <label class="crm-label">Invoice</label>
                        <div class="crm-search">
                            <i class="bi bi-receipt"></i>
                            <input type="text" name="invoice" value="{{ $invoiceSearch ?? '' }}"
                                   placeholder="No. invoice..."
                                   class="crm-field" autocomplete="off">
                        </div>
                    </div>
                @endif

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Customer</label>
                    <select name="account_id" class="select2 select2-search w-full" data-placeholder="Cari customer...">
                        <option value="">Semua customer</option>
                        @foreach ($filterAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected(($accountId ?? '') === $acc->id)>{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Nama perusahaan</label>
                    <select name="company" class="select2 select2-compact w-full" data-placeholder="Semua perusahaan">
                        <option value="">Semua perusahaan</option>
                        @foreach ($companies as $co)
                            <option value="{{ $co }}" @selected(($companyFilter ?? '') === $co)>{{ $co }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($showCommissionOwner && ($canFilterSales ?? false))
                    <div class="crm-opp-filters__field">
                        <label class="crm-label">Pemilik komisi</label>
                        <select name="assigned_user_id" class="select2 select2-search w-full" data-placeholder="Semua sales">
                            <option value="">Semua sales</option>
                            @foreach ($salesUsers as $user)
                                <option value="{{ $user->id }}" @selected(($selectedUserId ?? '') === $user->id)>
                                    {{ $user->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Periode</label>
                    <select name="period" class="select2 select2-compact w-full" data-placeholder="Periode">
                        <option value="year" @selected(($period ?? 'year') === 'year')>Tahun ini</option>
                        <option value="month" @selected(($period ?? 'year') === 'month')>Bulan ini</option>
                        <option value="3months" @selected(($period ?? 'year') === '3months')>3 Bulan</option>
                        <option value="6months" @selected(($period ?? 'year') === '6months')>6 Bulan</option>
                        <option value="alltime" @selected(($period ?? 'year') === 'alltime')>All Time</option>
                    </select>
                </div>

                <div class="crm-opp-filters__field">
                    <label class="crm-label">Antrian</label>
                    <select name="queue" class="select2 select2-compact w-full" data-placeholder="Semua antrian">
                        <option value="">Semua antrian</option>
                        <option value="needs_invoice" @selected(($queue ?? '') === 'needs_invoice')>Belum invoice</option>
                        <option value="unpaid" @selected(($queue ?? '') === 'unpaid')>Unpaid</option>
                        <option value="paid" @selected(($queue ?? '') === 'paid')>Sudah paid</option>
                        <option value="delivery_pending" @selected(($queue ?? '') === 'delivery_pending')>DO pending</option>
                    </select>
                </div>
            </div>

            <div class="crm-opp-filters__actions">
                <div class="crm-opp-filters__buttons">
                    <x-btn type="submit" variant="primary" icon="bi-funnel">Filter</x-btn>
                    @if ($canViewAr ?? false)
                        <x-btn type="submit" variant="secondary" icon="bi-receipt"
                               formaction="{{ route('sales-orders.account-receivable') }}" formtarget="_blank"
                               title="Account Receivable: invoice belum dibayar sesuai filter">AR</x-btn>
                    @endif
                    @if ($hasFilters)
                        <x-btn href="{{ route('sales-orders.index') }}" variant="ghost">Reset</x-btn>
                    @endif
                </div>
                <p class="crm-opp-filters__total">
                    Total: <strong>{{ number_format($salesOrders->total()) }}</strong>
                    <span>· {{ $periodLabel }}</span>
                </p>
            </div>
        </form>
    </x-card>

    <x-card :padding="false">
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>No. SO / PSO</th>
                        @if ($canSeeInvoice)
                            <th>Invoice</th>
                            <th>Status bayar</th>
                        @endif
                        <th>Customer</th>
                        @if ($showCommissionOwner && ($canFilterSales ?? false))
                            <th>Pemilik komisi</th>
                        @endif
                        @if ($showPoStatus)
                            <th>Data PO</th>
                        @endif
                        @if ($canEditDelivery)
                            <th>File DO</th>
                            <th>File BAST</th>
                            <th>No. Resi</th>
                            <th>Metode kirim</th>
                        @endif
                        <th class="text-right">Dibuat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesOrders as $so)
                        @php
                            $poCount = (int) ($so->purchase_orders_count ?? 0);
                            $hasPo = $poCount > 0;
                        @endphp
                        <tr>
                            <td style="white-space: nowrap;">
                                <a href="{{ route('opportunities.sales-orders.show', [$so->opportunity, $so]) }}"
                                   class="font-medium text-brand-600 hover:text-brand-700"
                                >
                                    {{ $so->displayNumber() }}
                                </a>
                                <div class="text-xs text-slate-400">
                                    @if ($so->displayPsoNumber() !== '')
                                        PSO {{ $so->displayPsoNumber() }}
                                        &middot;
                                    @endif
                                    @if ($so->displayRefNumber() !== '')
                                        {{ $so->displayRefNumber() }}
                                        &middot;
                                    @endif
                                    {{ $so->paymentLabel() }}
                                </div>
                            </td>
                            @if ($canSeeInvoice)
                                @php
                                    $invoiceNo = trim((string) ($so->payloadValue('invoice_no') ?? ''));
                                    $invoiceDtRaw = $so->payloadValue('invoice_dt') ?? $so->payloadValue('invoiced_dt') ?? $so->payloadValue('invoice_date');
                                    $invoiceDt = null;
                                    if (filled($invoiceDtRaw)) {
                                        try { $invoiceDt = \Illuminate\Support\Carbon::parse((string) $invoiceDtRaw); } catch (\Throwable) {}
                                    }
                                    $payStatus = strtolower(trim((string) ($so->statusSnapshot()['payment_status'] ?? ''))) ?: 'unpaid';
                                @endphp
                                <td>
                                    @if ($invoiceNo !== '')
                                        <span class="font-medium text-slate-800">{{ $invoiceNo }}</span>
                                        @if ($invoiceDt)
                                            <div class="text-xs text-slate-400">{{ $invoiceDt->translatedFormat('d M Y') }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (in_array($payStatus, ['paid', 'settlement'], true))
                                        <x-badge color="green">Paid</x-badge>
                                    @else
                                        <x-badge color="amber">Unpaid</x-badge>
                                    @endif
                                </td>
                            @endif
                            <td>
                                <span class="font-medium text-slate-800">{{ $so->opportunity?->account?->name ?: '—' }}</span>
                            </td>
                            @if ($showCommissionOwner && ($canFilterSales ?? false))
                                <td class="text-slate-600">
                                    {{ $so->commissionOwnerName() }}
                                    @if ($so->isCommissionReassigned())
                                        <div class="text-xs text-amber-600">
                                            Opp: {{ $so->opportunity?->assignedUser?->display_name ?: '—' }}
                                        </div>
                                    @endif
                                </td>
                            @endif
                            @if ($showPoStatus)
                            <td>
                                @if ($hasPo)
                                    <x-badge color="green">Ada PO · {{ $poCount }}</x-badge>
                                @else
                                    <x-badge color="slate">Belum ada PO</x-badge>
                                @endif
                            </td>
                            @endif
                            @if ($canEditDelivery)
                                @php
                                    $lockedDelivery = $so->isCancelled() || $so->isCancelPending();
                                    $fileDo = trim((string) ($so->payloadValue('file_do') ?? ''));
                                    $fileBast = trim((string) ($so->payloadValue('foto_serah_terima') ?? ''));
                                    $noResi = trim((string) ($so->payloadValue('no_resi') ?: $so->payloadValue('resi') ?: ''));
                                    $shipMethod = trim((string) ($so->payloadValue('courier_name') ?: $so->payloadValue('shipping_name') ?: ''));
                                    $updateUrl = $so->opportunity
                                        ? route('opportunities.sales-orders.update', [$so->opportunity, $so])
                                        : '';
                                @endphp
                                <td class="align-top" style="min-width: 9.5rem;">
                                    @include('sales-orders._delivery-file', [
                                        'label' => 'DO',
                                        'field' => 'file_do_file',
                                        'url' => $fileDo,
                                        'updateUrl' => $updateUrl,
                                        'locked' => $lockedDelivery,
                                    ])
                                </td>
                                <td class="align-top" style="min-width: 9.5rem;">
                                    @include('sales-orders._delivery-file', [
                                        'label' => 'BAST',
                                        'field' => 'foto_serah_terima_file',
                                        'url' => $fileBast,
                                        'updateUrl' => $updateUrl,
                                        'locked' => $lockedDelivery,
                                    ])
                                </td>
                                <td style="min-width: 9rem;">
                                    <input type="text" value="{{ $noResi }}" maxlength="100"
                                           @disabled($lockedDelivery || $updateUrl === '')
                                           data-so-field="no_resi" data-so-url="{{ $updateUrl }}"
                                           placeholder="No. resi / AWB"
                                           class="crm-field py-1.5 text-xs">
                                    <p class="so-delivery-msg hidden text-[11px] text-slate-400"></p>
                                </td>
                                <td style="min-width: 9rem;">
                                    <input type="text" value="{{ $shipMethod }}" maxlength="150"
                                           @disabled($lockedDelivery || $updateUrl === '')
                                           data-so-field="shipping_method" data-so-url="{{ $updateUrl }}"
                                           placeholder="JNE, ambil sendiri..."
                                           class="crm-field py-1.5 text-xs">
                                    <p class="so-delivery-msg hidden text-[11px] text-slate-400"></p>
                                </td>
                            @endif
                            <td class="text-right text-slate-600">
                                {{ $so->created_at?->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('opportunities.sales-orders.show', [$so->opportunity, $so]) }}" class="crm-icon-btn" title="Detail">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                                @if ($canDeleteSo)
                                    <form method="POST"
                                          action="{{ route('opportunities.sales-orders.destroy', [$so->opportunity, $so]) }}"
                                          style="display:inline-block;"
                                          onsubmit="return confirm('Sales Order akan diarsipkan ke Log SO, tidak dihapus permanen. Lanjutkan?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Arsipkan SO" type="submit">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + (int) $showPoStatus + (int) ($showCommissionOwner && ($canFilterSales ?? false)) + ((int) $canSeeInvoice * 2) + ((int) $canEditDelivery * 4) }}" class="py-10 text-center text-slate-500">
                                @if ($hasFilters)
                                    Tidak ada Sales Order sesuai filter.
                                    <a href="{{ route('sales-orders.index') }}" class="ml-1 text-brand-600 hover:underline">Reset</a>
                                @else
                                    Belum ada Sales Order.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $salesOrders->links() }}
        </div>
    </x-card>
@endsection

@if ($canEditDelivery)
@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const timers = new WeakMap();

    const setMsg = (el, text, ok) => {
        const box = el.closest('td')?.querySelector('.so-delivery-msg')
            || el.parentElement?.querySelector('.so-delivery-msg');
        if (!box) return;
        box.textContent = text;
        box.classList.remove('hidden', 'text-red-600', 'text-slate-400');
        box.classList.add(ok ? 'text-slate-400' : 'text-red-600');
    };

    const save = async (url, fields, fileField, file) => {
        const fd = new FormData();
        fd.append('_method', 'PUT');
        Object.entries(fields).forEach(([key, value]) => fd.append(key, value ?? ''));
        if (fileField && file) fd.append(fileField, file);

        const res = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: fd,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) {
            throw new Error(json.message || 'Gagal menyimpan.');
        }
        return json;
    };

    document.querySelectorAll('input[data-so-field]').forEach((input) => {
        const persist = async () => {
            if (!input.dataset.soUrl || input.disabled) return;
            input.dataset.last = input.value;
            try {
                await save(input.dataset.soUrl, { [input.dataset.soField]: input.value.trim() });
                setMsg(input, 'Tersimpan', true);
            } catch (error) {
                setMsg(input, error.message, false);
            }
        };

        input.addEventListener('input', () => {
            clearTimeout(timers.get(input));
            timers.set(input, setTimeout(persist, 600));
        });
        input.addEventListener('change', persist);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                persist();
            }
        });
    });

    document.querySelectorAll('.so-delivery-file input[type="file"]').forEach((input) => {
        input.addEventListener('change', async () => {
            const wrap = input.closest('.so-delivery-file');
            const file = input.files?.[0];
            if (!wrap || !file || !wrap.dataset.soUrl) return;
            const msg = wrap.querySelector('.so-delivery-msg');
            if (msg) {
                msg.textContent = 'Mengunggah...';
                msg.classList.remove('hidden', 'text-red-600');
                msg.classList.add('text-slate-400');
            }
            try {
                await save(wrap.dataset.soUrl, {}, wrap.dataset.soFileField, file);
                window.location.reload();
            } catch (error) {
                if (msg) {
                    msg.textContent = error.message;
                    msg.classList.remove('text-slate-400');
                    msg.classList.add('text-red-600');
                }
                input.value = '';
            }
        });
    });
})();
</script>
@endpush
@endif
