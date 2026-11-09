@php
    $hasFile = filled($url ?? null);
@endphp
<div class="so-delivery-file space-y-1" data-so-url="{{ $updateUrl }}" data-so-file-field="{{ $field }}">
    @if ($hasFile)
        <x-badge color="green">Ada {{ $label }}</x-badge>
        <div>
            <a href="{{ $url }}" target="_blank" class="text-xs font-medium text-brand-600 hover:underline">Lihat</a>
        </div>
    @else
        <x-badge color="slate">Belum ada</x-badge>
    @endif
    @unless ($locked || ($updateUrl ?? '') === '')
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
            <label class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-slate-600 hover:text-brand-600">
                <i class="bi bi-upload"></i> {{ $hasFile ? 'Ganti' : 'Upload' }}
                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp">
            </label>
            @if (! empty($camera))
                <button type="button" class="so-delivery-camera inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-brand-600">
                    <i class="bi bi-camera"></i> Kamera
                </button>
            @endif
        </div>
    @endunless
    @if (! empty($camera))
        @include('partials.camera-capture')
    @endif
    <p class="so-delivery-msg hidden text-[11px] text-slate-400"></p>
</div>
