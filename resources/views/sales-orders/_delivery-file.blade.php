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
        <label class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-slate-600 hover:text-brand-600">
            <i class="bi bi-upload"></i> {{ $hasFile ? 'Ganti' : 'Upload' }}
            <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp">
        </label>
    @endunless
    <p class="so-delivery-msg hidden text-[11px] text-slate-400"></p>
</div>
