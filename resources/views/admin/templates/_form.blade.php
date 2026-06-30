<form method="POST" action="{{ $action }}" id="template-form">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <x-card title="Template Content">
                {{-- Insert placeholder toolbar --}}
                <div class="mb-3 flex flex-wrap gap-1.5">
                    <span class="mr-1 self-center text-xs text-slate-400">Insert:</span>
                    @foreach (['customer_name','company_name','customer_address','contact_person','quotation_number','quotation_ref','quotation_date','quotation_place_date','valid_until','items_table','items_table_idr','total_price','sales_name','sales_title','sales_signature','company_legal_name','company_address','company_phone','company_email','terms','notes'] as $ph)
                        @php $placeholderTag = '{'.'{ '.$ph.' }'.'}'; @endphp
                        <button type="button" onclick="insertPlaceholder('{{ $ph }}')"
                                class="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] text-brand-700 hover:bg-brand-50">
                            {{ $placeholderTag }}
                        </button>
                    @endforeach
                </div>

                <textarea id="body_html" name="body_html">{!! old('body_html', $template->body_html) !!}</textarea>
                <p class="mt-2 text-xs text-slate-400">
                    Gunakan <code class="rounded bg-slate-100 px-1">@{{ sales_signature }}</code> atau
                    <code class="rounded bg-slate-100 px-1">@{{ notes }}</code> — jangan pakai sintaks Blade
                    (<code class="rounded bg-slate-100 px-1">@@if</code>, <code class="rounded bg-slate-100 px-1">{&#123;!! !!&#125;}</code>).
                    Placeholder kosong otomatis tidak menampilkan apa-apa.
                </p>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Details">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Template Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
                        <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('description', $template->description) }}</textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Active
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Set as default template
                    </label>
                </div>
                <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700"><i class="bi bi-save"></i> Save Template</button>
                <a href="{{ route('templates.index') }}" class="mt-2 block w-full rounded-lg border border-slate-300 py-2.5 text-center text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            </x-card>
        </div>
    </div>
</form>

@push('styles')
@include('partials.quotation-fonts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
    /* Summernote + Tailwind: perbaiki tampilan agar selaras dengan UI CRM */
    .note-editor.note-frame {
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .note-toolbar {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 6px 8px;
    }
    .note-editable {
        min-height: 480px;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        line-height: 1.6;
        background: #fff;
    }
    .note-statusbar { display: none; }
    .note-btn { border-radius: 0.375rem; }
    .note-modal .modal-dialog { max-width: 600px; }
    .note-fontname .dropdown-menu {
        max-height: 280px;
        overflow-y: auto;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-en-US.min.js"></script>
<script>
    function insertPlaceholder(name) {
        const tag = '{' + '{ ' + name + ' }' + '}';
        $('#body_html').summernote('pasteHTML', tag);
    }

    $(function () {
        const quotationFonts = [
            'Inter',
            'MachineScript',
            'Arial',
            'Helvetica',
            'Times New Roman',
            'Georgia',
            'Verdana',
            'Tahoma',
            'Courier New',
            'Calibri',
            'Garamond',
            'Brush Script MT',
            'Segoe Script',
            'Lucida Handwriting',
            'Dancing Script',
            'Great Vibes',
            'Pacifico',
            'Caveat',
            'Roboto',
            'Merriweather',
            'Lora',
            'Libre Baskerville',
        ];

        $('#body_html').summernote({
            height: 480,
            lang: 'en-US',
            placeholder: 'Write quotation template content here...',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']],
            ],
            fontNames: quotationFonts,
            fontNamesIgnoreCheck: quotationFonts,
            addDefaultFonts: false,
            callbacks: {
                onInit: function () {
                    $('.note-editable').css('font-family', "'Inter', sans-serif");
                },
            },
        });

        // Pastikan HTML dari Summernote tersimpan ke textarea saat submit.
        $('#template-form').on('submit', function () {
            $('#body_html').val($('#body_html').summernote('code'));
        });
    });
</script>
@endpush
