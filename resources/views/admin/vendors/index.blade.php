@extends('layouts.app')
@section('title', 'Vendors')

@section('content')
<x-page-header title="Vendors" description="Master vendor untuk item opportunity. Dikelola superadmin, tim Product, dan Purchasing.">
    <x-slot:actions>
        <x-btn href="{{ route('vendor-stocks.index') }}" variant="secondary" icon="bi-boxes">Ketersediaan</x-btn>
        <x-btn href="{{ route('vendors.create') }}" icon="bi-plus-lg">New Vendor</x-btn>
    </x-slot:actions>
</x-page-header>

<div class="mb-4">
    @include('admin.catalog._excel-actions', [
        'exportRoute' => route('vendors.export'),
        'templateRoute' => route('vendors.template'),
        'importRoute' => route('vendors.import'),
        'label' => 'vendor',
        'hint' => 'Dikelompokkan per Nama Perusahaan. Baris PIC berikutnya boleh kosongkan No / Nama Perusahaan / Status / TOP — otomatis mengikuti grup di atasnya.',
    ])
</div>

<x-card :padding="false">
    @if ($vendors->count())
        <div class="crm-bstable">
            <table id="vendors-table"
                   class="crm-table"
                   data-search-placeholder="Cari vendor...">
                <thead>
                    <tr>
                        <th data-field="vendor_id" data-visible="false" data-searchable="false">ID</th>
                        <th data-field="pic_count" data-visible="false" data-searchable="false">Jumlah PIC</th>
                        <th data-field="pic_search" data-visible="false">PIC</th>
                        <th data-field="name" data-sortable="true">Nama Perusahaan</th>
                        <th data-field="company_status" data-sortable="true" data-width="160">Status Perusahaan</th>
                        <th data-field="brands" data-sortable="false">Brand</th>
                        <th data-field="top" data-sortable="true" data-width="140">TOP</th>
                        <th data-field="pkp" data-sortable="true" data-width="90">PKP</th>
                        <th data-field="pic_label" data-sortable="false" data-width="110">PIC</th>
                        <th data-field="sort_order" data-sortable="true" data-align="right" data-width="90">Urutan</th>
                        <th data-field="status" data-sortable="true" data-width="110">Status</th>
                        <th data-field="actions" data-searchable="false" data-sortable="false" data-align="right" data-width="110"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendors as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->pics->count() }}</td>
                            <td>
                                @foreach ($item->pics as $pic)
                                    {{ $pic->name }} {{ $pic->job_role }} {{ $pic->phone }} {{ $pic->email }}
                                @endforeach
                            </td>
                            <td class="font-medium text-slate-800">{{ $item->name }}</td>
                            <td class="text-slate-600">{{ $item->company_status ?: '—' }}</td>
                            <td class="text-slate-600">
                                @if ($item->brands->isEmpty())
                                    <span class="text-slate-400">—</span>
                                @else
                                    {{ $item->brands->pluck('name')->join(', ') }}
                                @endif
                            </td>
                            <td class="text-slate-600">{{ $item->topLabel() }}</td>
                            <td>
                                @if ($item->is_pkp)
                                    <x-badge color="blue">PKP</x-badge>
                                @else
                                    <x-badge color="slate">Non-PKP</x-badge>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="js-vendor-detail inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 hover:bg-slate-200">
                                    {{ $item->pics->count() }} PIC
                                    <i class="bi bi-chevron-down text-[10px] opacity-70"></i>
                                </button>
                            </td>
                            <td class="tabular-nums text-slate-600">{{ $item->sort_order }}</td>
                            <td>
                                @if ($item->is_active)
                                    <x-badge color="green">Aktif</x-badge>
                                @else
                                    <x-badge color="slate">Nonaktif</x-badge>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('vendors.edit', $item) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('vendors.destroy', $item) }}" class="inline" onsubmit="return confirm('Hapus vendor ini beserta PIC-nya?')">
                                    @csrf @method('DELETE')
                                    <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @foreach ($vendors as $item)
                <template id="vendor-pics-{{ $item->id }}">
                    <div class="vendor-pic-detail">
                        @if ($item->pics->isEmpty())
                            <p class="vendor-pic-empty">Belum ada PIC. Edit vendor ini atau import Excel yang berisi kolom Nama PIC.</p>
                        @else
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama PIC</th>
                                        <th>Job Role</th>
                                        <th>No Telp</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item->pics as $pic)
                                        <tr>
                                            <td class="font-medium text-slate-800">{{ $pic->name }}</td>
                                            <td>{{ $pic->job_role ?: '—' }}</td>
                                            <td>
                                                @if ($pic->phone)
                                                    <a href="tel:{{ preg_replace('/\s+/', '', $pic->phone) }}">{{ $pic->phone }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if ($pic->email)
                                                    <a href="mailto:{{ $pic->email }}">{{ $pic->email }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </template>
            @endforeach
        </div>
    @else
        <x-empty-state icon="bi-truck" title="Belum ada vendor" message="Tambah vendor agar muncul di form opportunity.">
            <x-slot:action>
                <x-btn href="{{ route('vendors.create') }}" icon="bi-plus-lg">New Vendor</x-btn>
            </x-slot:action>
        </x-empty-state>
    @endif
</x-card>
@endsection

@once
    @push('styles')
        @include('partials.bootstrap-table-assets')
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.6/dist/bootstrap-table.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.6/dist/locale/bootstrap-table-id-ID.min.js"></script>
        <script>
            (function ($) {
                $(function () {
                    $('.crm-bstable > table').each(function () {
                        var $table = $(this);
                        if ($table.data('bstable-ready') || typeof $table.bootstrapTable !== 'function') return;
                        $table.data('bstable-ready', true);
                        var placeholder = $table.data('search-placeholder') || 'Search';
                        var isVendors = $table.attr('id') === 'vendors-table';
                        var options = {
                            classes: 'crm-table',
                            search: true,
                            searchAlign: 'right',
                            pagination: true,
                            pageSize: 10,
                            pageList: [10, 25, 50, 100],
                            sortable: true,
                            locale: 'id-ID',
                            paginationVAlign: 'bottom',
                            paginationHAlign: 'right',
                            paginationDetailHAlign: 'left',
                            formatSearch: function () { return placeholder; },
                        };
                        if (isVendors) {
                            options.iconsPrefix = 'bi';
                            options.icons = $.extend({}, $.fn.bootstrapTable.defaults.icons || {}, {
                                detailOpen: 'bi-chevron-right',
                                detailClose: 'bi-chevron-down',
                            });
                            options.detailView = true;
                            options.detailViewIcon = true;
                            options.detailViewByClick = true;
                            options.detailFormatter = function (index, row) {
                                var id = String(row.vendor_id || row._id || '').replace(/<[^>]+>/g, '').trim();
                                var tpl = document.getElementById('vendor-pics-' + id);
                                if (!tpl) {
                                    return '<p class="vendor-pic-empty">Belum ada PIC</p>';
                                }
                                return tpl.innerHTML;
                            };
                        }
                        $table.bootstrapTable(options);
                        if (isVendors) {
                            $table.on('click', 'a:not(.detail-icon), button:not(.js-vendor-detail), form', function (e) {
                                e.stopPropagation();
                            });
                            $table.on('click', '.js-vendor-detail', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                var index = $(this).closest('tr').data('index');
                                if (typeof index === 'undefined') return;
                                var $detail = $table.find('tr[data-index="' + index + '"]').next('.detail-view');
                                if ($detail.length) {
                                    $table.bootstrapTable('collapseRow', index);
                                } else {
                                    $table.bootstrapTable('expandRow', index);
                                }
                            });
                        }
                    });

                    $(document).on('click', '.bootstrap-table .dropdown-toggle', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var $group = $(this).closest('.btn-group, .dropdown');
                        $('.bootstrap-table .btn-group, .bootstrap-table .dropdown').not($group).removeClass('open show');
                        $group.toggleClass('open show');
                    });
                    $(document).on('click', function () {
                        $('.bootstrap-table .btn-group, .bootstrap-table .dropdown').removeClass('open show');
                    });
                });
            })(window.jQuery);
        </script>
    @endpush
@endonce
