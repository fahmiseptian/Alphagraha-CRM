@extends('layouts.app')
@section('title', 'Kategori Biaya')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-800">Kategori Biaya</h2>
    <p class="text-sm text-slate-500">
        Kategori dipakai di form tambah biaya komisi dan entertainment opportunity.
        Kategori yang sudah dipakai tidak dihapus permanen, tetapi dinonaktifkan agar data lama tetap utuh.
    </p>
</div>

<div class="grid items-start gap-4 lg:grid-cols-3">
    <x-card class="lg:col-span-2" :padding="false" title="Daftar kategori">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Nama</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Dipakai</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $category)
                        <tr x-data="{ editing: false }">
                            <template x-if="!editing">
                                <td class="px-4 py-2 font-medium text-slate-800">{{ $category->name }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-2">
                                    @if ($category->is_active)
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700">Aktif</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">Nonaktif</span>
                                    @endif
                                </td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ number_format($usage[$category->id] ?? 0) }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="editing = true" class="crm-icon-btn" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('expense-categories.destroy', $category) }}" class="inline"
                                          onsubmit="return confirm('Hapus kategori {{ addslashes($category->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="crm-icon-btn text-red-500 hover:bg-red-50" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </template>
                            <template x-if="editing">
                                <td colspan="4" class="px-4 py-2">
                                    <form method="POST" action="{{ route('expense-categories.update', $category) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="name" value="{{ $category->name }}" required maxlength="100" class="crm-field min-w-[12rem] flex-1">
                                        <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-slate-300">
                                            Aktif
                                        </label>
                                        <x-btn type="submit" variant="primary" icon="bi-check-lg">Simpan</x-btn>
                                        <button type="button" @click="editing = false" class="text-sm text-slate-500 hover:text-slate-700">Batal</button>
                                    </form>
                                </td>
                            </template>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-400">Belum ada kategori.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Tambah kategori">
        <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Nama kategori</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                       class="crm-field w-full @error('name') border-red-300 @enderror"
                       placeholder="Contoh: Biaya Parkir">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <i class="bi bi-plus-lg"></i> Tambah kategori
            </button>
        </form>
    </x-card>
</div>
@endsection
