@props(['title', 'description' => null, 'back' => null, 'backLabel' => 'Back'])

@if ($back)
    <a href="{{ $back }}" class="crm-back"><i class="bi bi-arrow-left"></i> {{ $backLabel }}</a>
@endif

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="min-w-0">
        <h2 class="crm-page-title">{{ $title }}</h2>
        @if ($description)
            <p class="crm-page-desc">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
