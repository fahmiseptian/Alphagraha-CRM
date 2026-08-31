@props([
    'exportRoute',
    'templateRoute',
    'importRoute',
    'label' => 'data',
    'hint' => 'Kolom: nama, aktif (ya/tidak), urutan. Nama yang sama akan diperbarui.',
])

<div class="flex flex-wrap items-center gap-2">
    <x-btn href="{{ $templateRoute }}" variant="secondary" icon="bi-file-earmark-arrow-down">Template</x-btn>
    <x-btn href="{{ $exportRoute }}" variant="secondary" icon="bi-download">Export Excel</x-btn>
    <form method="POST" action="{{ $importRoute }}" enctype="multipart/form-data" class="inline-flex items-center">
        @csrf
        <input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
               class="hidden" onchange="this.form.submit()" id="catalog-import-{{ md5($importRoute) }}">
        <label for="catalog-import-{{ md5($importRoute) }}"
               class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200">
            <i class="bi bi-upload"></i>
            Import Excel
        </label>
    </form>
</div>
<p class="mt-1 text-[11px] text-slate-400">{{ $hint }}</p>
