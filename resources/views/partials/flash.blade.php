@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 8000)" x-transition
         class="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
        <i class="bi bi-check-circle-fill text-green-600"></i>
        <span class="flex-1">{{ session('success') }}</span>
        @if (session('google_calendar_url'))
            <a href="{{ session('google_calendar_url') }}" target="_blank" rel="noopener"
               class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-green-700 ring-1 ring-green-200 hover:bg-green-100">
                <i class="bi bi-calendar-plus"></i> Buka Google Calendar
            </a>
        @endif
        <button @click="show = false" class="text-green-600 hover:text-green-800"><i class="bi bi-x-lg"></i></button>
    </div>
@endif

@if (session('google_calendar_url'))
    <script>
        (function () {
            var url = @json(session('google_calendar_url'));
            if (!url) return;
            window.open(url, '_blank', 'noopener');
        })();
    </script>
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
