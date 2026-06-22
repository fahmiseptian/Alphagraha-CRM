@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="mb-4 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <i class="bi bi-check-circle-fill"></i>
        <span>{{ session('success') }}</span>
        <button @click="show = false" class="ml-auto text-green-600 hover:text-green-800"><i class="bi bi-x-lg"></i></button>
    </div>
@endif

@if (session('error'))
    <div class="mb-4 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <div class="flex items-center gap-2 font-medium"><i class="bi bi-exclamation-triangle-fill"></i> Periksa kembali isian berikut:</div>
        <ul class="ml-6 mt-1 list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
