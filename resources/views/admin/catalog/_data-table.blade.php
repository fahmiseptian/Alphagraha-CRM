@php
    $items = $items ?? collect();
    $searchPlaceholder = $searchPlaceholder ?? 'Search';
    $editRoute = $editRoute ?? '';
    $destroyRoute = $destroyRoute ?? '';
    $confirmMessage = $confirmMessage ?? 'Hapus data ini?';
@endphp

<div class="crm-bstable">
    <table id="{{ $tableId }}"
           class="crm-table"
           data-search-placeholder="{{ $searchPlaceholder }}">
        <thead>
            <tr>
                <th data-field="name" data-sortable="true">Nama</th>
                <th data-field="sort_order" data-sortable="true" data-align="right" data-width="100">Urutan</th>
                <th data-field="status" data-sortable="true" data-width="120">Status</th>
                <th data-field="actions" data-searchable="false" data-sortable="false" data-align="right" data-width="110"></th>
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
                    <td class="text-right">
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
