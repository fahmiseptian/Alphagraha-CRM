@props(['color' => 'slate'])

@php
    $palette = [
        'gray' => 'bg-slate-100 text-slate-700',
        'slate' => 'bg-slate-100 text-slate-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'brand' => 'bg-brand-100 text-brand-700',
        'green' => 'bg-green-100 text-green-700',
        'red' => 'bg-red-100 text-red-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'purple' => 'bg-purple-100 text-purple-700',
        'rose' => 'bg-rose-100 text-rose-700',
    ][$color] ?? 'bg-slate-100 text-slate-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium $palette"]) }}>
    {{ $slot }}
</span>
