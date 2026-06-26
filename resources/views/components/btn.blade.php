@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50';
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus:ring-brand-300',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-200',
        'ghost' => 'text-slate-600 hover:bg-slate-100 focus:ring-slate-200',
    ];
    $class = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
        @if ($icon)<i class="bi {{ $icon }}"></i>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>
        @if ($icon)<i class="bi {{ $icon }}"></i>@endif
        {{ $slot }}
    </button>
@endif
