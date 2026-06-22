@php
    $initialItems = old('items', $quotation->exists
        ? $quotation->items->map(fn ($i) => [
            'name' => $i->name, 'description' => $i->description,
            'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'unit_price' => (float) $i->unit_price,
          ])->values()->all()
        : []);
    if (empty($initialItems)) {
        $initialItems = [['name' => '', 'description' => '', 'quantity' => 1, 'unit' => '', 'unit_price' => 0]];
    }
    $accountMap = $accounts->mapWithKeys(fn ($a) => [$a->id => [
        'name' => $a->name,
        'address' => collect([$a->billing_address_street, $a->billing_address_city, $a->billing_address_state, $a->billing_address_postal_code, $a->billing_address_country])->filter()->implode(', '),
    ]]);
    $config = [
        'items' => $initialItems,
        'discount' => (float) old('discount', $quotation->discount ?? 0),
        'taxPercent' => (float) old('tax_percent', $quotation->tax_percent ?? 0),
        'currency' => old('currency', $quotation->currency ?? 'IDR'),
        'accounts' => $accountMap,
        'accountId' => (string) old('account_id', $quotation->account_id),
        'customerName' => old('customer_name', $quotation->customer_name) ?? '',
        'companyName' => old('company_name', $quotation->company_name) ?? '',
        'customerAddress' => old('customer_address', $quotation->customer_address) ?? '',
    ];
@endphp

<form method="POST" action="{{ $action }}"
      x-data="quotationForm({{ \Illuminate\Support\Js::from($config) }})">
    @csrf
    @if (($method ?? 'POST') === 'PUT')@method('PUT')@endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Data pelanggan --}}
            <x-card title="Data Pelanggan">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Pilih Pelanggan (EspoCRM)</label>
                        <select name="account_id" x-model="accountId" @change="fillFromAccount()"
                                class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                            <option value="">— Input manual / tanpa pelanggan —</option>
                            @foreach ($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected(old('account_id', $quotation->account_id) === $acc->id)>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Memilih pelanggan akan mengisi otomatis nama & alamat. Anda tetap bisa mengeditnya.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Pelanggan <span class="text-red-500">*</span></label>
                            <input type="text" name="customer_name" x-model="customerName" required
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Perusahaan</label>
                            <input type="text" name="company_name" x-model="companyName"
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                            <input type="email" name="customer_email" value="{{ old('customer_email', $quotation->customer_email) }}"
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Telepon</label>
                            <input type="text" name="customer_phone" value="{{ old('customer_phone', $quotation->customer_phone) }}"
                                   class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Alamat</label>
                        <textarea name="customer_address" x-model="customerAddress" rows="2"
                                  class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></textarea>
                    </div>
                </div>
            </x-card>

            {{-- Item penawaran --}}
            <x-card>
                <x-slot:title>Item Penawaran</x-slot:title>
                <x-slot:action>
                    <button type="button" @click="addItem()" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-100">
                        <i class="bi bi-plus-lg"></i> Tambah Item
                    </button>
                </x-slot:action>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-12 sm:col-span-5">
                                    <input type="text" :name="`items[${index}][name]`" x-model="item.name" placeholder="Nama produk/layanan" required
                                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-4 sm:col-span-2">
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][quantity]`" x-model.number="item.quantity" placeholder="Qty"
                                           class="w-full rounded-lg border border-slate-300 py-2 px-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <input type="text" :name="`items[${index}][unit]`" x-model="item.unit" placeholder="unit"
                                           class="w-full rounded-lg border border-slate-300 py-2 px-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-5 sm:col-span-3">
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" placeholder="Harga satuan"
                                           class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                </div>
                                <div class="col-span-12 flex items-center justify-between sm:col-span-1 sm:justify-center">
                                    <span class="text-sm font-medium text-slate-700 sm:hidden" x-text="formatMoney(item.quantity * item.unit_price)"></span>
                                    <button type="button" @click="removeItem(index)" class="rounded-lg p-2 text-red-500 hover:bg-red-50"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="col-span-12">
                                    <input type="text" :name="`items[${index}][description]`" x-model="item.description" placeholder="Deskripsi (opsional)"
                                           class="w-full rounded-lg border border-slate-200 py-1.5 px-3 text-xs text-slate-500 focus:border-brand-500 focus:ring-1 focus:ring-brand-200">
                                </div>
                            </div>
                            <div class="mt-2 hidden text-right text-sm text-slate-500 sm:block">
                                Subtotal: <span class="font-semibold text-slate-700" x-text="formatMoney(item.quantity * item.unit_price)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </x-card>

            {{-- Catatan --}}
            <x-card title="Catatan & Syarat">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Catatan Tambahan</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('notes', $quotation->notes) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Syarat & Ketentuan</label>
                        <textarea name="terms" rows="3" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">{{ old('terms', $quotation->terms ?? "1. Harga belum termasuk PPN jika berlaku.\n2. Penawaran berlaku selama masa yang tercantum.") }}</textarea>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Sidebar ringkasan --}}
        <div class="space-y-4">
            <x-card title="Pengaturan Penawaran">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal <span class="text-red-500">*</span></label>
                        <input type="date" name="quotation_date" value="{{ old('quotation_date', optional($quotation->quotation_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required
                               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Berlaku Hingga</label>
                        <input type="date" name="valid_until" value="{{ old('valid_until', optional($quotation->valid_until)->format('Y-m-d')) }}"
                               class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Mata Uang</label>
                            <select name="currency" x-model="currency" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="SGD">SGD</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', $quotation->status ?? 'draft') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Template</label>
                        <select name="template_id" class="w-full rounded-lg border border-slate-300 py-2 px-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200">
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}" @selected(old('template_id', $quotation->template_id ?? optional($templates->firstWhere('is_default', true))->id) === $tpl->id)>
                                    {{ $tpl->name }} @if($tpl->is_default) (default) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-card>

            <x-card title="Ringkasan Biaya">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd class="font-medium text-slate-800" x-text="formatMoney(subtotal)"></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Diskon</dt>
                        <dd><input type="number" step="0.01" min="0" name="discount" x-model.number="discount" class="w-28 rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Pajak (%)</dt>
                        <dd><input type="number" step="0.01" min="0" max="100" name="tax_percent" x-model.number="taxPercent" class="w-28 rounded-lg border border-slate-300 py-1.5 px-2 text-right text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"></dd>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <dt>Nilai Pajak</dt>
                        <dd x-text="formatMoney(taxAmount)"></dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-bold text-slate-900">
                        <dt>Total</dt>
                        <dd x-text="formatMoney(total)"></dd>
                    </div>
                </dl>
                <button type="submit" class="mt-5 w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    <i class="bi bi-save"></i> Simpan Penawaran
                </button>
                <a href="{{ url()->previous() }}" class="mt-2 block w-full rounded-lg border border-slate-300 py-2.5 text-center text-sm text-slate-600 hover:bg-slate-50">Batal</a>
            </x-card>
        </div>
    </div>
</form>

<script>
    function quotationForm(config) {
        return {
            items: config.items,
            discount: config.discount,
            taxPercent: config.taxPercent,
            currency: config.currency,
            accounts: config.accounts,
            accountId: config.accountId || '',
            customerName: config.customerName || '',
            companyName: config.companyName || '',
            customerAddress: config.customerAddress || '',

            get subtotal() {
                return this.items.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0), 0);
            },
            get taxAmount() {
                const base = Math.max(this.subtotal - (Number(this.discount) || 0), 0);
                return base * ((Number(this.taxPercent) || 0) / 100);
            },
            get total() {
                return Math.max(this.subtotal - (Number(this.discount) || 0), 0) + this.taxAmount;
            },
            addItem() {
                this.items.push({ name: '', description: '', quantity: 1, unit: '', unit_price: 0 });
            },
            removeItem(index) {
                this.items.splice(index, 1);
                if (this.items.length === 0) this.addItem();
            },
            fillFromAccount() {
                const acc = this.accounts[this.accountId];
                if (acc) {
                    this.customerName = acc.name || this.customerName;
                    this.companyName = acc.name || this.companyName;
                    if (acc.address) this.customerAddress = acc.address;
                }
            },
            formatMoney(value) {
                value = Number(value) || 0;
                if (this.currency === 'IDR') {
                    return 'Rp ' + value.toLocaleString('id-ID', { maximumFractionDigits: 0 });
                }
                return this.currency + ' ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>
