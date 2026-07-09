@props([
    'title' => '',
    'value' => '',
    'icon' => 'bi-graph-up',
    'color' => 'brand',
    'sub' => null,
    'href' => null,
])

@php
    $palette = [
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-green-50 text-green-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'purple' => 'bg-purple-50 text-purple-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ][$color] ?? 'bg-brand-50 text-brand-600';
@endphp

@php
    $classes = 'block rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md'
        . ($href ? ' cursor-pointer hover:border-slate-300' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $classes }}">
@else
    <div class="{{ $classes }}">
@endif
    <div class="flex items-start justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $title }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-800">{{ $value }}</p>
            @if ($sub)
                <p class="mt-1 text-xs text-slate-400">{{ $sub }}</p>
            @endif
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-lg {{ $palette }}">
            <i class="bi {{ $icon }} text-xl"></i>
        </span>
    </div>
@if ($href)
    </a>
@else
    </div>
@endif
