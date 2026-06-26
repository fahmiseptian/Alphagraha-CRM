@props(['title' => null, 'action' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm']) }}>
    @if (filled($title) || filled($action))
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h3 class="text-sm font-semibold text-slate-800">{{ $title }}</h3>
            @if ($action)<div class="shrink-0">{{ $action }}</div>@endif
        </div>
    @endif
    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>
</div>
