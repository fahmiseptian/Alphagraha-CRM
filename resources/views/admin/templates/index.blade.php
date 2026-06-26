@extends('layouts.app')
@section('title', 'Quotation Templates')

@section('content')
<x-page-header title="Quotation Templates" description="Standardize company quotation documents">
    <x-slot:actions>
        <x-btn href="{{ route('templates.create') }}" icon="bi-plus-lg">New Template</x-btn>
    </x-slot:actions>
</x-page-header>

<x-card :padding="false">
    @if ($templates->count())
        <ul class="divide-y divide-slate-100">
            @foreach ($templates as $template)
                <li class="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><i class="bi bi-file-earmark-richtext text-lg"></i></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-800">{{ $template->name }}</p>
                            @if ($template->is_default)<x-badge color="green">Default</x-badge>@endif
                            @if (!$template->is_active)<x-badge color="slate">Inactive</x-badge>@endif
                        </div>
                        <p class="truncate text-xs text-slate-400">{{ $template->description ?: 'No description' }}</p>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <a href="{{ route('templates.edit', $template) }}" class="crm-icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('templates.destroy', $template) }}" class="inline" onsubmit="return confirm('Delete this template?')">@csrf @method('DELETE')
                            <button class="crm-icon-btn text-red-500 hover:bg-red-50" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state icon="bi-file-earmark-richtext" title="No templates found" message="Create your first HTML template." />
    @endif
</x-card>
@endsection
