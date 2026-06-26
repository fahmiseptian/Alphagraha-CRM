@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)" x-transition
         class="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
        <i class="bi bi-check-circle-fill text-green-600"></i>
        <span class="flex-1">{{ session('success') }}</span>
        <button @click="show = false" class="text-green-600 hover:text-green-800"><i class="bi bi-x-lg"></i></button>
    </div>
@endif

@if (session('error'))
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill text-red-600"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
        <div class="flex items-center gap-2 font-medium"><i class="bi bi-exclamation-triangle-fill"></i> Please review the following:</div>
        <ul class="ml-6 mt-2 list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
