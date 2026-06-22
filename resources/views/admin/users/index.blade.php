@extends('layouts.app')
@section('title', 'Pengguna')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Manajemen Pengguna</h2>
        <p class="text-sm text-slate-500">Akun pada tabel user EspoCRM</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama / username"
                    class="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200 sm:w-56">
            </div>
            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cari</button>
        </form>
        <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><i class="bi bi-person-plus"></i> Pengguna Baru</a>
    </div>
</div>

<x-card :padding="false">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <th class="px-5 py-3 font-medium">Nama</th>
                    <th class="px-5 py-3 font-medium">Username</th>
                    <th class="px-5 py-3 font-medium">Email</th>
                    <th class="px-5 py-3 font-medium">Role</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ initials($user->display_name) }}</span>
                                <span class="font-medium text-slate-800">{{ $user->display_name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $user->user_name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $user->email ?? '—' }}</td>
                        <td class="px-5 py-3"><x-badge :color="$user->role === 'admin' ? 'purple' : 'blue'">{{ ucfirst($user->role) }}</x-badge></td>
                        <td class="px-5 py-3">
                            @if ($user->is_active)<x-badge color="green">Aktif</x-badge>@else<x-badge color="slate">Nonaktif</x-badge>@endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('users.edit', $user) }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Edit"><i class="bi bi-pencil"></i></a>
                                @if ($user->id !== auth()->id() && $user->user_name !== 'admin')
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?')">@csrf @method('DELETE')
                                        <button class="rounded-lg p-2 text-red-500 hover:bg-red-50" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Tidak ada pengguna ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-5 py-3">{{ $users->links() }}</div>
</x-card>
@endsection
