@props(['icon' => 'bi-inbox', 'title' => 'No data', 'message' => null])

<div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <i class="bi {{ $icon }} text-2xl"></i>
    </div>
    <p class="mt-4 text-sm font-medium text-slate-700">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 text-sm text-slate-400">{{ $message }}</p>
    @endif
    @if (isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
