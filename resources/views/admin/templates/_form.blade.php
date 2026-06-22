<form method="POST" action="{{ $action }}">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <x-card title="Konten Template (HTML)">
                <textarea name="body_html" rows="22" spellcheck="false"
                          class="w-full rounded-lg border border-slate-300 py-2 px-3 font-mono text-xs leading-relaxed focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('body_html', $template->body_html) }}</textarea>
                <p class="mt-2 text-xs text-slate-400">Tip: gunakan placeholder seperti <code class="rounded bg-slate-100 px-1">@{{ customer_name }}</code> dan <code class="rounded bg-slate-100 px-1">@{{ items_table }}</code>.</p>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Detail">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Template <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Deskripsi</label>
                        <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('description', $template->description) }}</textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Aktif
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Jadikan template default
                    </label>
                </div>
                <button class="mt-5 w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700"><i class="bi bi-save"></i> Simpan Template</button>
                <a href="{{ route('templates.index') }}" class="mt-2 block w-full rounded-lg border border-slate-300 py-2.5 text-center text-sm text-slate-600 hover:bg-slate-50">Batal</a>
            </x-card>
        </div>
    </div>
</form>
