@php
    $logs = $activityLogs ?? collect();
@endphp

<x-card id="activity-log" class="mt-4 scroll-mt-24" :padding="false">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">
                <i class="bi bi-clock-history mr-1 text-slate-400"></i>
                Riwayat Opportunity
            </h3>
            <p class="mt-0.5 text-xs text-slate-400">Semua perubahan, siapa yang mengedit, dan keputusan approval.</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @if (method_exists($logs, 'total'))
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $logs->total() }} entri</span>
            @endif
            @if (auth()->user()?->canViewOpportunityLogs())
                <a href="{{ route('opportunity-logs.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Semua log</a>
            @endif
        </div>
    </div>

    @if ($logs->count())
        <ol class="divide-y divide-slate-50">
            @foreach ($logs as $log)
                @php
                    $fieldChanges = $log->fieldChanges();
                    $productChanges = $log->productChanges();
                    $hasDetails = $fieldChanges !== [] || $log->hasProductChanges() || filled(data_get($log->changes, 'note'));
                @endphp
                <li class="px-5 py-4" x-data="{ open: {{ $hasDetails && $loop->first ? 'true' : 'false' }} }">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-500">
                            <i class="bi {{ $log->actionIcon() }}"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :color="$log->actionBadgeColor()">{{ $log->actionLabel() }}</x-badge>
                                <span class="text-xs text-slate-400">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</span>
                            </div>
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ $log->summary ?: $log->actionLabel() }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                oleh <span class="font-medium text-slate-700">{{ $log->actor?->display_name ?: ($log->actor_name ?: 'Sistem') }}</span>
                            </p>

                            @if ($hasDetails)
                                <button type="button" @click="open = !open"
                                        class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                    <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                    <span x-text="open ? 'Sembunyikan detail' : 'Lihat perubahan'"></span>
                                </button>

                                <div x-show="open" x-cloak class="mt-3 space-y-3">
                                    @if (filled(data_get($log->changes, 'note')))
                                        <p class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                            <span class="font-semibold text-slate-500">Catatan:</span>
                                            {{ data_get($log->changes, 'note') }}
                                        </p>
                                    @endif

                                    @if ($fieldChanges !== [])
                                        <div class="overflow-x-auto rounded-lg border border-slate-100">
                                            <table class="min-w-full text-xs">
                                                <thead class="bg-slate-50 text-slate-500">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left font-medium">Field</th>
                                                        <th class="px-3 py-2 text-left font-medium">Sebelum</th>
                                                        <th class="px-3 py-2 text-left font-medium">Sesudah</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-50">
                                                    @foreach ($fieldChanges as $change)
                                                        <tr>
                                                            <td class="px-3 py-2 font-medium text-slate-700">{{ $change['label'] ?? $change['field'] ?? '—' }}</td>
                                                            <td class="px-3 py-2 text-slate-500">{{ $change['from_display'] ?? '—' }}</td>
                                                            <td class="px-3 py-2 text-slate-800">{{ $change['to_display'] ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if ($productChanges['added'] !== [])
                                        <div>
                                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-green-700">Barang ditambah</p>
                                            <ul class="space-y-1">
                                                @foreach ($productChanges['added'] as $item)
                                                    <li class="rounded-lg border border-green-100 bg-green-50 px-3 py-2 text-xs text-green-900">
                                                        <span class="font-medium">{{ $item['name'] ?? 'Item' }}</span>
                                                        @if (! empty($item['sku']))
                                                            <span class="text-green-700">· SKU {{ $item['sku'] }}</span>
                                                        @endif
                                                        @if (isset($item['quantity']))
                                                            <span class="text-green-700">· qty {{ rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',') }}</span>
                                                        @endif
                                                        @if (isset($item['sell_exclude']))
                                                            <span class="text-green-700">· jual {{ money($item['sell_exclude'], $opportunity->amount_currency ?: 'IDR') }}</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if ($productChanges['removed'] !== [])
                                        <div>
                                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-red-700">Barang dihapus</p>
                                            <ul class="space-y-1">
                                                @foreach ($productChanges['removed'] as $item)
                                                    <li class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-900">
                                                        <span class="font-medium">{{ $item['name'] ?? 'Item' }}</span>
                                                        @if (! empty($item['sku']))
                                                            <span class="text-red-700">· SKU {{ $item['sku'] }}</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if ($productChanges['changed'] !== [])
                                        <div>
                                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-blue-700">Barang diubah</p>
                                            <div class="space-y-2">
                                                @foreach ($productChanges['changed'] as $item)
                                                    <div class="rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2 text-xs">
                                                        <p class="font-medium text-slate-800">{{ $item['name'] ?? 'Item' }}</p>
                                                        <ul class="mt-1 space-y-0.5 text-slate-600">
                                                            @foreach (($item['changes'] ?? []) as $change)
                                                                <li>
                                                                    {{ $change['label'] ?? $change['field'] ?? 'Field' }}:
                                                                    <span class="text-slate-400">{{ $change['from_display'] ?? '—' }}</span>
                                                                    →
                                                                    <span class="font-medium text-slate-800">{{ $change['to_display'] ?? '—' }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
        @if (method_exists($logs, 'hasPages') && $logs->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">
                {{ $logs->links() }}
            </div>
        @endif
    @else
        <div class="px-5 py-10 text-center text-sm text-slate-400">
            Belum ada riwayat. Perubahan berikutnya (buat, edit harga, tambah barang, approval) akan tercatat di sini.
        </div>
    @endif
</x-card>
