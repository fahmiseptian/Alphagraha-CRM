@props(['opportunity', 'openOnError' => false])

@php
    $lostStage = \App\Models\Espo\Opportunity::LOST_STAGE;
@endphp

<div
    x-data="{ lostModalOpen: {{ ($openOnError && $errors->has('lost_reason')) ? 'true' : 'false' }} }"
    class="inline-flex"
>
    <div @click.stop="lostModalOpen = true">
        {{ $trigger }}
    </div>

    <div
        x-show="lostModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="closed-lost-title-{{ $opportunity->id }}"
    >
        <div x-show="lostModalOpen" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="lostModalOpen = false"></div>

        <div
            x-show="lostModalOpen"
            x-transition
            class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
            @click.stop
        >
            <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="stage" value="{{ $lostStage }}">

                <div class="border-b border-red-100 bg-red-50 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 id="closed-lost-title-{{ $opportunity->id }}" class="font-semibold text-slate-800">
                                Closed Lost
                            </h3>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Jelaskan alasan kekalahan deal ini sebelum menutup.
                            </p>
                        </div>
                        <button type="button" @click="lostModalOpen = false" class="text-slate-400 hover:text-slate-600">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="px-5 py-4">
                    <label class="crm-label" for="lost-reason-{{ $opportunity->id }}">
                        Catatan kekalahan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="lost-reason-{{ $opportunity->id }}"
                        name="lost_reason"
                        rows="4"
                        required
                        minlength="10"
                        maxlength="5000"
                        class="crm-field"
                        placeholder="Contoh: harga kalah dengan kompetitor, customer pilih vendor lain, anggaran dibatalkan..."
                    >{{ old('lost_reason') }}</textarea>
                    @error('lost_reason')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-slate-400">Minimal 10 karakter.</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" @click="lostModalOpen = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Batal
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        <i class="bi bi-x-circle mr-1"></i> Konfirmasi Closed Lost
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
