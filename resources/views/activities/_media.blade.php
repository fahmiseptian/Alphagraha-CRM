@php
    $accept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.zip';
@endphp

<x-card :padding="false" class="mt-6">
    <div class="border-b border-slate-100 px-5 py-4">
        <h3 class="text-sm font-semibold text-slate-800">Media Pendukung</h3>
        <p class="mt-0.5 text-xs text-slate-400">Unggah undangan dan bukti kegiatan (foto, dokumen, dll.)</p>
    </div>

    <div class="divide-y divide-slate-100">
        @foreach (App\Models\Activity::MEDIA_COLLECTIONS as $collection => $label)
            @php $items = $mediaByCollection[$collection] ?? collect(); @endphp
            <div class="px-5 py-4">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-medium text-slate-700">
                        <i class="bi {{ $collection === 'invitation' ? 'bi-envelope-paper' : 'bi-camera' }} mr-1 text-slate-400"></i>
                        {{ $label }}
                    </h4>
                    <form method="POST" action="{{ route('activities.media.store', $activity) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="collection" value="{{ $collection }}">
                        <input type="file" name="file" id="activity-media-{{ $collection }}" class="hidden" accept="{{ $accept }}" onchange="this.form.submit()">
                        <button type="button" onclick="document.getElementById('activity-media-{{ $collection }}').click()" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100" title="Unggah {{ strtolower($label) }}">
                            <i class="bi bi-plus-lg"></i>
                            <span class="hidden sm:inline">Unggah</span>
                        </button>
                    </form>
                </div>

                @if ($items->count())
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($items as $media)
                            @php $isImage = str_starts_with($media->mime_type ?? '', 'image/'); @endphp
                            <li class="flex gap-3 rounded-lg border border-slate-100 p-3 hover:bg-slate-50">
                                @if ($isImage)
                                    <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="shrink-0">
                                        <img src="{{ $media->getUrl() }}" alt="{{ $media->file_name }}" class="h-16 w-16 rounded-lg border border-slate-200 object-cover">
                                    </a>
                                @else
                                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                        <i class="bi bi-file-earmark text-2xl"></i>
                                    </span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-brand-600 hover:underline" title="{{ $media->file_name }}">{{ $media->file_name }}</a>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $media->created_at?->translatedFormat('d M Y H:i') }}</p>
                                </div>
                                <form method="POST" action="{{ route('activities.media.destroy', [$activity, $media]) }}" onsubmit="return confirm('Hapus media ini?')" class="shrink-0">
                                    @csrf @method('DELETE')
                                    <button class="rounded p-1 text-red-500 hover:bg-red-50" title="Hapus"><i class="bi bi-trash text-sm"></i></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="rounded-lg border border-dashed border-slate-200 py-6 text-center text-sm text-slate-400">
                        Belum ada {{ strtolower($label) }}. Klik <i class="bi bi-plus-lg"></i> untuk mengunggah.
                    </p>
                @endif
            </div>
        @endforeach
    </div>
</x-card>
