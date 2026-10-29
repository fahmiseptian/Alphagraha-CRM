@extends('layouts.app')

@php
    $sales = $summary['sales'];
    $quarter = $summary['quarter'];
    $quarters = $summary['quarters'] ?? [];
    $canManage = $canManage ?? auth()->user()->canManageCommissions();
    $isOwn = (string) auth()->id() === (string) $sales->id;
    $pageTitle = $isOwn && ! $canManage ? 'Komisi Saya' : 'Komisi · '.$sales->display_name;
@endphp

@section('title', $pageTitle)

@section('content')
@if ($canManage)
<div class="mb-6">
    <a href="{{ route('commissions.index', ['year' => $year]) }}" class="crm-back"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
@endif

<x-page-header :title="$pageTitle"
               :description="'Target '.$summary['target_period_label'].' · '.money($summary['target'] ?? 0)">
    <x-slot:actions>
        <form method="GET" action="{{ route('commissions.show', $sales->id) }}" class="flex flex-wrap items-center gap-2">
            <select name="year" class="crm-field py-1.5 text-sm" onchange="this.form.submit()">
                @for ($y = now()->year; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                @endfor
            </select>
            <select name="period" class="crm-field py-1.5 text-sm" onchange="this.form.submit()">
                @foreach ($quarters as $q)
                    <option value="{{ $q['key'] }}" @selected(($quarter['key'] ?? '') === $q['key'])>{{ $q['label'] }}</option>
                @endforeach
            </select>
        </form>
    </x-slot:actions>
</x-page-header>

@if (! $quarter)
    <x-card>
        <p class="text-sm text-slate-500">Periode komisi tidak ditemukan.</p>
    </x-card>
@else
    @php
        $targetProgress = (float) ($summary['target_progress'] ?? 0);
        $targetMet = (bool) ($summary['target_met'] ?? false);
        $progressLabel = number_format($targetProgress, 1, ',', '.').'%';
        $progressSub = $targetMet
            ? 'Sudah mencapai target'
            : 'Belum mencapai target · '.money($summary['net_gp']).' / '.money($summary['target']);
        $rateLabel = rtrim(rtrim(number_format((float) ($summary['commission_rate_percent'] ?? 0), 2, ',', '.'), '0'), ',').'%';
        $commissionSub = $rateLabel.' dari Nett Profit'
            .(((float) ($summary['commission_raw'] ?? 0) > (float) ($summary['commission'] ?? 0))
                ? ' · dibulatkan ke bawah per Rp 500'
                : '');
    @endphp
    @php
        $detailQuery = ['year' => $year, 'period' => $quarter['key']];
        $detailUrl = fn (string $section) => route('commissions.detail', array_merge(
            ['user' => $sales->id, 'section' => $section],
            $detailQuery
        ));
    @endphp
    @php
        $soOverdueCount = (int) ($summary['so_overdue_count'] ?? 0);
        $soOutstandingSub = money($summary['so_outstanding_total'] ?? 0).' belum complete'
            .($soOverdueCount > 0 ? ' · '.$soOverdueCount.' lewat TOP' : '');
        $invoiceOutstandingSub = money($summary['invoice_outstanding_total'] ?? 0).' belum bayar';
    @endphp
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card title="SO Keseluruhan" :value="number_format($summary['so_all_count'] ?? 0)" icon="bi-collection" color="slate"
                     :sub="money($summary['so_all_total'] ?? 0).' · by tgl dibuat'"
                     :href="$detailUrl('so-all')" />
        <x-stat-card title="SO Outstanding" :value="number_format($summary['so_outstanding_count'] ?? 0)" icon="bi-hourglass-split"
                     :color="$soOverdueCount > 0 ? 'rose' : 'amber'"
                     :sub="$soOutstandingSub"
                     :href="$detailUrl('so-outstanding')" />
        <x-stat-card title="SO Complete" :value="number_format($summary['so_count'])" icon="bi-receipt" color="brand"
                     :sub="money($summary['so_total']).' · sudah complete'"
                     :href="$detailUrl('so-complete')" />
    </div>
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card title="Invoice Keseluruhan" :value="number_format($summary['invoice_all_count'] ?? 0)" icon="bi-file-earmark-text" color="slate"
                     :sub="money($summary['invoice_all_total'] ?? 0).' · by tgl invoice'"
                     :href="$detailUrl('invoice-all')" />
        <x-stat-card title="Invoice Outstanding" :value="number_format($summary['invoice_outstanding_count'] ?? 0)" icon="bi-cash-coin" color="rose"
                     :sub="$invoiceOutstandingSub"
                     :href="$detailUrl('invoice-outstanding')" />
        <x-stat-card title="Invoice Complete" :value="number_format($summary['invoice_complete_count'] ?? 0)" icon="bi-check2-circle" color="green"
                     :sub="money($summary['invoice_complete_total'] ?? 0).' sudah bayar'"
                     :href="$detailUrl('invoice-complete')" />
    </div>
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card title="Gross Profit" :value="money_signed($summary['gp_total'])" icon="bi-graph-up-arrow" color="slate"
                     :sub="'By tgl invoice · '.number_format($summary['gp_order_count'] ?? 0).' SO'"
                     :href="$detailUrl('gross-profit')">
            <div class="mt-3 space-y-1 border-t border-slate-100 pt-2 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Invoice</span>
                    <span class="font-medium tabular-nums text-slate-700">{{ money($summary['gp_invoice_total'] ?? 0) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">HPP</span>
                    <span class="font-medium tabular-nums text-slate-700">{{ money($summary['gp_hpp_total'] ?? 0) }}</span>
                </div>
            </div>
        </x-stat-card>
        <x-stat-card title="Biaya Tambahan" :value="money($summary['entertainment_total'] + $summary['expense_total'])" icon="bi-cup-hot" color="amber"
                     :sub="'Entertainment '.money($summary['entertainment_total']).' + Biaya '.money($summary['expense_total'])"
                     :href="$detailUrl('biaya-tambahan')" />
        <x-stat-card title="Nett Profit" :value="money_signed($summary['net_gp'])" icon="bi-cash-coin" color="green"
                     :sub="$quarter['label']"
                     :href="$detailUrl('nett-profit')" />
        <x-stat-card title="Komisi" :value="money($summary['commission'] ?? 0)" icon="bi-wallet2" color="brand"
                     :sub="$commissionSub"
                     :href="$detailUrl('komisi')" />
        <x-stat-card title="Pencapaian Target"
                     :value="$progressLabel"
                     icon="{{ $targetMet ? 'bi-trophy' : 'bi-bullseye' }}"
                     :color="$targetMet ? 'green' : 'rose'"
                     :sub="$progressSub"
                     :href="$detailUrl('target')" />
    </div>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @php
                $canApproveEntertainment = $canManage && auth()->user()?->canCompleteEntertainment();
                $costRows = collect($summary['expenses'] ?? [])
                    ->map(fn ($expense) => [
                        'type' => 'expense',
                        'model' => $expense,
                        'date' => $expense->expense_date,
                        'category_id' => $expense->category_id,
                        'category' => $expense->category?->name,
                        'pending' => false,
                        'amount' => (float) $expense->amount,
                    ])
                    ->concat(collect($summary['entertainments_pending'] ?? [])->map(fn ($item) => [
                        'type' => 'entertainment',
                        'model' => $item,
                        'date' => $item->entertainment_date ?? $item->created_at,
                        'category_id' => $item->category_id,
                        'category' => $item->category?->name,
                        'pending' => true,
                        'amount' => (float) $item->amount,
                    ]))
                    ->concat(collect($summary['entertainments'] ?? [])->map(fn ($item) => [
                        'type' => 'entertainment',
                        'model' => $item,
                        'date' => $item->entertainment_date ?? $item->completed_at ?? $item->created_at,
                        'category_id' => $item->category_id,
                        'category' => $item->category?->name,
                        'pending' => false,
                        'amount' => (float) $item->amount,
                    ]))
                    ->sortByDesc(fn ($row) => optional($row['date'])->timestamp ?? 0)
                    ->values();
                $costCategoryTabs = $costRows
                    ->map(fn ($row) => ['id' => (string) ($row['category_id'] ?? ''), 'name' => $row['category'] ?: 'Tanpa kategori'])
                    ->unique('id')
                    ->sortBy('name')
                    ->values();
                $costRowsJs = $costRows->map(fn ($row) => [
                    'cat' => (string) ($row['category_id'] ?? ''),
                    'amount' => $row['amount'],
                    'pending' => $row['pending'],
                ])->values();
                $costColspan = $canManage ? 9 : 8;
            @endphp
            <div x-data="{
                receiptOpen: false,
                receiptUrl: '',
                receiptName: '',
                receiptIsImage: false,
                filter: '',
                rows: @js($costRowsJs),
                visible(cat) { return this.filter === '' || this.filter === cat; },
                total(pending) {
                    return this.rows
                        .filter(r => this.visible(r.cat) && r.pending === pending)
                        .reduce((sum, r) => sum + (Number(r.amount) || 0), 0);
                },
                count() { return this.rows.filter(r => this.visible(r.cat)).length; },
                money(v) { return 'Rp ' + (Number(v) || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 }); },
                openReceipt(url, name, isImage) {
                    this.receiptUrl = url;
                    this.receiptName = name || 'Struk';
                    this.receiptIsImage = !!isImage;
                    this.receiptOpen = true;
                },
                closeReceipt() {
                    this.receiptOpen = false;
                    this.receiptUrl = '';
                }
            }">
            <x-card title="Biaya & Entertainment" :padding="false">
                @if (auth()->user()?->canManageExpenseCategories())
                    <x-slot:action>
                        <a href="{{ route('expense-categories.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                            <i class="bi bi-tags"></i> Kelola kategori
                        </a>
                    </x-slot:action>
                @endif
                <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
                    Semua biaya di {{ $quarter['label'] }} (berdasarkan tanggal biaya / entertainment).
                    Biaya yang diikat ke SO mengurangi margin SO, selain itu jadi potongan umum.
                    @if ($canApproveEntertainment)
                        Entertainment pending perlu di-approve agar mengurangi komisi.
                    @endif
                </p>
                @if ($costCategoryTabs->count() > 1)
                    <div class="flex flex-wrap gap-1.5 border-b border-slate-100 px-4 py-2">
                        <button type="button" @click="filter = ''"
                                :class="filter === '' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="rounded-full px-3 py-1 text-xs font-medium">Semua</button>
                        @foreach ($costCategoryTabs as $tab)
                            <button type="button" @click="filter = @js($tab['id'])"
                                    :class="filter === @js($tab['id']) ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                    class="rounded-full px-3 py-1 text-xs font-medium">{{ $tab['name'] }}</button>
                        @endforeach
                    </div>
                @endif
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Tanggal</th>
                                <th class="px-4 py-2">Kategori</th>
                                <th class="px-4 py-2">Nama</th>
                                <th class="px-4 py-2">Customer / Opportunity</th>
                                <th class="px-4 py-2">Kode SO</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Bukti</th>
                                <th class="px-4 py-2 text-right">Nominal</th>
                                @if ($canManage)
                                    <th class="px-4 py-2"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($costRows as $row)
                                @php
                                    $model = $row['model'];
                                    $isExpense = $row['type'] === 'expense';
                                @endphp
                                <tr x-show="visible(@js((string) ($row['category_id'] ?? '')))"
                                    class="{{ $row['pending'] ? 'bg-amber-50/40' : '' }}">
                                    <td class="px-4 py-2 text-slate-600 tabular-nums">
                                        {{ optional($row['date'])->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ $row['category'] ?: 'Tanpa kategori' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-slate-800">{{ $model->name }}</div>
                                        @php $note = $isExpense ? $model->note : $model->description; @endphp
                                        @if ($note)
                                            <div class="text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($note, 80) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">
                                        @if ($isExpense)
                                            @if ($model->account)
                                                <a href="{{ route('customers.show', $model->account_id) }}" class="hover:underline">{{ $model->account->name }}</a>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        @elseif ($model->opportunity)
                                            @if ($model->opportunity->account)
                                                <div>{{ $model->opportunity->account->name }}</div>
                                            @endif
                                            <a href="{{ route('opportunities.show', $model->opportunity).'#entertainments' }}"
                                               class="text-xs text-brand-600 hover:underline">{{ $model->opportunity->name }}</a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($isExpense && $model->sales_order_number)
                                            <span class="font-medium text-brand-600">{{ $model->sales_order_number }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($row['pending'])
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Pending</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700">Complete</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($isExpense)
                                            @if ($model->hasFile())
                                                <a href="{{ route('commissions.expenses.download', [$sales->id, $model]) }}"
                                                   class="text-xs font-medium text-brand-600 hover:underline">unduh</a>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        @elseif ($photoUrl = $model->photoUrl())
                                            <button type="button"
                                                    @click="openReceipt(@js($photoUrl), @js($model->name), @js($model->photoIsImage()))"
                                                    class="text-xs font-medium text-brand-600 hover:underline">
                                                lihat struk
                                            </button>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums font-semibold text-amber-700">{{ money($row['amount']) }}</td>
                                    @if ($canManage)
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            @if ($isExpense)
                                                <form method="POST" action="{{ route('commissions.expenses.destroy', [$sales->id, $model]) }}"
                                                      onsubmit="return confirm('Hapus biaya ini?')" class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="crm-icon-btn text-red-500 hover:bg-red-50" title="Hapus">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @elseif ($row['pending'] && $canApproveEntertainment)
                                                <form method="POST"
                                                      action="{{ route('commissions.entertainments.approve', [$sales->id, $model]) }}"
                                                      onsubmit="return confirm('Approve entertainment ini? Komisi di periode tanggalnya akan berkurang.')"
                                                      class="inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700"
                                                            title="Approve">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $costColspan }}" class="px-4 py-6 text-center text-slate-400">Belum ada biaya di periode ini.</td>
                                </tr>
                            @endforelse
                            @if ($costRows->isNotEmpty())
                                <tr x-show="count() === 0" x-cloak>
                                    <td colspan="{{ $costColspan }}" class="px-4 py-6 text-center text-slate-400">Tidak ada biaya di kategori ini.</td>
                                </tr>
                            @endif
                        </tbody>
                        @if ($costRows->isNotEmpty())
                            <tfoot class="bg-slate-50 font-semibold">
                                <tr x-show="total(true) > 0">
                                    <td colspan="7" class="px-4 py-2 text-amber-700">Pending (belum mengurangi komisi)</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-amber-700" x-text="money(total(true))"></td>
                                    @if ($canManage)<td></td>@endif
                                </tr>
                                <tr>
                                    <td colspan="7" class="px-4 py-2 text-slate-700">Total mengurangi komisi</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-amber-700" x-text="money(total(false))"></td>
                                    @if ($canManage)<td></td>@endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-card>

            <div x-show="receiptOpen" x-cloak
                 class="fixed inset-0 z-[70] flex items-center justify-center p-4"
                 role="dialog" aria-modal="true"
                 @keydown.escape.window="closeReceipt()">
                <div class="absolute inset-0 bg-slate-900/50" @click="closeReceipt()"></div>
                <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                        <h3 class="truncate text-sm font-semibold text-slate-800" x-text="receiptName"></h3>
                        <div class="flex shrink-0 items-center gap-2">
                            <a :href="receiptUrl" target="_blank" rel="noopener"
                               class="text-xs font-medium text-brand-600 hover:underline">Buka tab baru</a>
                            <button type="button" @click="closeReceipt()"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                    title="Tutup">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-auto bg-slate-50 p-4">
                        <template x-if="receiptIsImage">
                            <img :src="receiptUrl" :alt="receiptName" class="mx-auto max-h-[75vh] max-w-full rounded-lg object-contain shadow-sm">
                        </template>
                        <template x-if="!receiptIsImage">
                            <iframe :src="receiptUrl" class="h-[75vh] w-full rounded-lg border border-slate-200 bg-white" title="Struk"></iframe>
                        </template>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <div class="space-y-4">
            @if ($canManage)
            <x-card title="Tambah biaya">
                <form method="POST" action="{{ route('commissions.expenses.store', $sales->id) }}"
                      enctype="multipart/form-data"
                      class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Tanggal</label>
                        <input type="date" name="expense_date"
                               value="{{ old('expense_date', now()->format('Y-m-d')) }}" required
                               class="crm-field w-full @error('expense_date') border-red-300 @enderror">
                        <p class="mt-1 text-[11px] text-slate-400">Biaya dipotong di periode (Q) sesuai tanggal ini.</p>
                        @error('expense_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Kategori</label>
                        <select name="category_id" required
                                class="crm-field w-full @error('category_id') border-red-300 @enderror">
                            @foreach ($expenseCategories ?? [] as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id', $defaultCategoryId ?? '') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @if (auth()->user()?->canManageExpenseCategories())
                            <p class="mt-1 text-[11px] text-slate-400">
                                <a href="{{ route('expense-categories.index') }}" class="text-brand-600 hover:underline">Kelola kategori</a>
                            </p>
                        @endif
                        @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Nama biaya</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                               class="crm-field w-full @error('name') border-red-300 @enderror"
                               placeholder="Contoh: Makan siang klien">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Nominal</label>
                        <input type="text" inputmode="decimal" name="amount" value="{{ old('amount') }}" required
                               data-crm-number data-decimals="0"
                               class="crm-field w-full tabular-nums @error('amount') border-red-300 @enderror"
                               placeholder="0">
                        @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">
                            Customer <span class="font-normal text-slate-400">(opsional)</span>
                        </label>
                        <select name="account_id"
                                class="select2 select2-search w-full @error('account_id') border-red-300 @enderror"
                                data-placeholder="— Tidak terikat customer —">
                            <option value="">— Tidak terikat customer —</option>
                            @foreach ($accounts ?? [] as $acc)
                                <option value="{{ $acc->id }}" @selected(old('account_id') === $acc->id)>
                                    {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">
                            Kode SO <span class="font-normal text-slate-400">(opsional)</span>
                        </label>
                        @php
                            // Semua SO complete (per SO, tidak digabung per opportunity).
                            $soOptions = collect($summary['sales_orders'] ?? [])
                                ->map(fn ($r) => ['number' => $r['number']])
                                ->concat(
                                    collect($summary['sales_orders_all'] ?? [])
                                        ->filter(fn ($r) => ! empty($r['is_complete']))
                                        ->map(fn ($r) => ['number' => $r['number']])
                                )
                                ->unique('number')
                                ->sortBy('number')
                                ->values();
                        @endphp
                        <select name="so_code"
                                class="select2 select2-search w-full @error('so_code') border-red-300 @enderror"
                                data-placeholder="— Tidak terikat SO —">
                            <option value="">— Tidak terikat SO —</option>
                            @foreach ($soOptions as $soRow)
                                <option value="{{ $soRow['number'] }}" @selected(old('so_code') === $soRow['number'])>
                                    {{ $soRow['number'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Pilih dari Sales Order Complete. Jika dipilih, margin SO berkurang sesuai nominal.</p>
                        @error('so_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Catatan</label>
                        <textarea name="note" rows="2" class="crm-field w-full" maxlength="2000">{{ old('note') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">File bukti</label>
                        <input type="file" name="file" required
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 @error('file') border-red-300 @enderror">
                        <p class="mt-1 text-[11px] text-slate-400">Wajib diunggah · maks. 20 MB</p>
                        @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                        <i class="bi bi-plus-lg"></i> Tambah biaya
                    </button>
                </form>
            </x-card>
            @endif

            <x-card title="Ringkasan">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Target</dt><dd class="font-medium tabular-nums">{{ money($summary['target']) }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">
                            Gross Profit
                            @if (($summary['expense_linked_total'] ?? 0) > 0)
                                <span class="mt-0.5 block text-[11px] text-slate-400">
                                    Sudah dipotong biaya terikat SO {{ money($summary['expense_linked_total']) }}
                                </span>
                            @endif
                        </dt>
                        <dd class="font-medium tabular-nums">{{ money_signed($summary['gp_total']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">− Entertainment</dt><dd class="tabular-nums text-amber-700">{{ money($summary['entertainment_total']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">− Biaya lain (umum)</dt><dd class="tabular-nums text-amber-700">{{ money($summary['expense_unlinked_total'] ?? $summary['expense_total']) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">Nett Profit</dt><dd class="font-bold tabular-nums text-green-700">{{ money_signed($summary['net_gp']) }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Pencapaian target</dt>
                        <dd class="font-semibold tabular-nums {{ $targetMet ? 'text-green-700' : 'text-rose-600' }}">
                            {{ number_format($targetProgress, 1, ',', '.') }}%
                            · {{ $targetMet ? 'Tercapai' : 'Belum tercapai' }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $targetMet ? 'bg-green-500' : 'bg-brand-500' }}"
                         style="width: {{ min(100, max(0, $targetProgress)) }}%"></div>
                </div>
            </x-card>
        </div>
    </div>
@endif
@endsection
