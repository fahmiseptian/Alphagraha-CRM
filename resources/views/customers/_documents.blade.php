@php
    $canManageDocuments = auth()->user()->canManageCustomerDocuments();
    $openDocFormOnError = $errors->any() && $errors->hasAny(['name', 'file']);
@endphp

<x-card title="Dokumen">
    <div x-data="{ open: {{ $openDocFormOnError ? 'true' : 'false' }} }">
        @if (($documents ?? collect())->count())
            <ul class="space-y-3">
                @foreach ($documents as $document)
                    <li class="rounded-lg border border-slate-100 p-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                                <i class="bi bi-file-earmark-text"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-800">{{ $document->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $document->original_name ?: '—' }}
                                    <span class="text-slate-300">·</span>
                                    {{ $document->humanSize() }}
                                    @if ($document->created_at)
                                        <span class="text-slate-300">·</span>
                                        {{ $document->created_at->format('d/m/Y H:i') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('customers.documents.download', [$account->id, $document]) }}"
                                   class="crm-icon-btn" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                @if ($canManageDocuments)
                                    <form method="POST"
                                          action="{{ route('customers.documents.destroy', [$account->id, $document]) }}"
                                          onsubmit="return confirm('Hapus dokumen ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="crm-icon-btn text-red-500 hover:bg-red-50" title="Hapus dokumen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-400">Belum ada dokumen.</p>
        @endif

        @if ($canManageDocuments)
            <div class="mt-4">
                <button type="button"
                        @click="open = !open"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <i class="bi" :class="open ? 'bi-x-lg' : 'bi-upload'"></i>
                    <span x-text="open ? 'Tutup form' : 'Upload dokumen'"></span>
                </button>

                <form method="POST"
                      action="{{ route('customers.documents.store', $account->id) }}"
                      enctype="multipart/form-data"
                      x-show="open"
                      x-cloak
                      class="mt-3 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Nama</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                               placeholder="Contoh: NPWP, SIUP, Kontrak"
                               class="crm-field w-full @error('name') border-red-300 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">File</label>
                        <input type="file" name="file" required
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 @error('file') border-red-300 @enderror">
                        <p class="mt-1 text-[11px] text-slate-400">Maks. 20 MB</p>
                        @error('file')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                            <i class="bi bi-upload"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-card>
