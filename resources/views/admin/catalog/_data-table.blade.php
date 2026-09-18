@php
    $items = $items ?? collect();
    $searchPlaceholder = $searchPlaceholder ?? 'Search';
    $editRoute = $editRoute ?? '';
    $destroyRoute = $destroyRoute ?? '';
    $confirmMessage = $confirmMessage ?? 'Hapus data ini?';
@endphp

<div class="crm-bstable crm-bstable--catalog">
    <table id="{{ $tableId }}"
           class="crm-table"
           data-search-placeholder="{{ $searchPlaceholder }}">
        <thead>
            <tr>
                <th data-field="name" data-sortable="true">Nama</th>
                <th data-field="sort_order" data-sortable="true" data-align="center" data-halign="center" data-width="140">Urutan</th>
                <th data-field="status" data-sortable="true" data-align="center" data-halign="center" data-width="160">Status</th>
                <th data-field="actions" data-searchable="false" data-sortable="false" data-align="right" data-halign="right" data-width="120"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td class="font-medium text-slate-800">{{ $item->name }}</td>
                    <td class="tabular-nums text-slate-600">{{ $item->sort_order }}</td>
                    <td>
                        @if ($item->is_active)
                            <x-badge color="green">Aktif</x-badge>
                        @else
                            <x-badge color="slate">Nonaktif</x-badge>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route($editRoute, $item) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route($destroyRoute, $item) }}" class="inline" onsubmit="return confirm(@json($confirmMessage))">
                            @csrf @method('DELETE')
                            <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@once
    @push('styles')
        @include('partials.bootstrap-table-assets')
        <style>
            /* Paksa rata tengah — override default .crm-table th { text-align:left } */
            .crm-bstable--catalog th[data-field="sort_order"],
            .crm-bstable--catalog th[data-field="sort_order"] .th-inner,
            .crm-bstable--catalog td[data-field="sort_order"],
            .crm-bstable--catalog th[data-field="status"],
            .crm-bstable--catalog th[data-field="status"] .th-inner,
            .crm-bstable--catalog td[data-field="status"] {
                text-align: center !important;
            }
            .crm-bstable--catalog th[data-field="actions"],
            .crm-bstable--catalog th[data-field="actions"] .th-inner,
            .crm-bstable--catalog td[data-field="actions"] {
                text-align: right !important;
            }
        </style>
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
                        $table.bootstrapTable({
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
                            onPostHeader: function () {
                                $table.find('th[data-field="sort_order"], th[data-field="status"]').each(function () {
                                    this.style.setProperty('text-align', 'center', 'important');
                                    var inner = this.querySelector('.th-inner');
                                    if (inner) inner.style.setProperty('text-align', 'center', 'important');
                                });
                                $table.find('th[data-field="actions"]').each(function () {
                                    this.style.setProperty('text-align', 'right', 'important');
                                    var inner = this.querySelector('.th-inner');
                                    if (inner) inner.style.setProperty('text-align', 'right', 'important');
                                });
                            },
                            onPostBody: function () {
                                $table.find('td').each(function () {
                                    var field = $(this).attr('data-field')
                                        || $(this).closest('table').find('thead th').eq($(this).index()).data('field');
                                    if (field === 'sort_order' || field === 'status') {
                                        this.style.setProperty('text-align', 'center', 'important');
                                    }
                                    if (field === 'actions') {
                                        this.style.setProperty('text-align', 'right', 'important');
                                    }
                                });
                            },
                        });
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
