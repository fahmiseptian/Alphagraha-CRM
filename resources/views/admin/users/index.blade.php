@extends('layouts.app')
@section('title', $roleLabel.' Users')

@section('content')
<x-page-header :title="$roleLabel" description="Kelola akun dengan role {{ $roleLabel }}">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="role" value="{{ $role }}">
            <div class="crm-search w-full sm:w-56">
                <i class="bi bi-search"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search name / username" class="crm-field">
            </div>
            <x-btn type="submit" variant="secondary">Search</x-btn>
        </form>
        <x-btn href="{{ route('users.create', ['role' => $role]) }}" icon="bi-person-plus">New {{ $roleLabel }}</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card :padding="false">
    <div class="crm-table-wrap">
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    @if ($role === \App\Models\User::ROLE_SALES)
                        <th>Sales Code</th>
                        <th>Sales Target</th>
                        <th>Periode</th>
                        <th>Tenggat</th>
                    @endif
                    <th>Email</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="crm-avatar rounded-full">{{ initials($user->display_name) }}</span>
                                <span class="font-medium text-slate-800">{{ $user->display_name }}</span>
                            </div>
                        </td>
                        <td class="text-slate-600">{{ $user->user_name }}</td>
                        @if ($role === \App\Models\User::ROLE_SALES)
                            <td class="font-mono text-sm text-slate-700">{{ $user->profile?->sales_code ?: '—' }}</td>
                            <td class="text-sm text-slate-700">{{ money($user->profile?->resolvedSalesTarget() ?? 0) }}</td>
                            <td class="text-sm text-slate-700">{{ $user->profile?->salesTargetPeriodLabel() ?? '—' }}</td>
                            <td class="text-sm text-slate-700">
                                @php $autoDeadline = $user->profile?->resolvedSalesTargetDeadline(); @endphp
                                @if ($autoDeadline)
                                    {{ $autoDeadline->format('d M Y') }}
                                    @unless ($user->profile?->hasManualSalesTargetDeadline())
                                        <span class="block text-[10px] text-slate-400">otomatis</span>
                                    @endunless
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="text-slate-600">{{ $user->email ?? '—' }}</td>
                        <td>
                            @if ($user->is_active)<x-badge color="green">Active</x-badge>@else<x-badge color="slate">Inactive</x-badge>@endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('users.edit', $user) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                            @if ($user->id !== auth()->id() && $user->user_name !== 'admin')
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')
                                    <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $role === \App\Models\User::ROLE_SALES ? 9 : 5 }}" class="py-12 text-center text-slate-400">
                            Belum ada user dengan role {{ $roleLabel }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="crm-table-footer">{{ $users->links() }}</div>
</x-card>
@endsection
