<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk &middot; {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: {
            50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',
            500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a'
        } } } } };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full bg-slate-100">
<div class="flex min-h-full">
    {{-- Panel kiri (branding) --}}
    <div class="relative hidden w-1/2 flex-col justify-between bg-gradient-to-br from-brand-700 to-brand-900 p-12 text-white lg:flex"
         style="background-image: linear-gradient(135deg, #1d4ed8, #1e3a8a);">
        <div class="flex items-center gap-2 text-lg font-semibold">
            <i class="bi bi-bezier2"></i> AGC CRM
        </div>
        <div>
            <h2 class="text-3xl font-bold leading-tight">Kelola pelanggan & penawaran<br>dalam satu tempat.</h2>
            <p class="mt-4 max-w-md text-blue-100">CRM modern untuk tim sales — pantau prospek, atur follow-up, dan buat penawaran profesional yang terstandarisasi.</p>
        </div>
        <div class="text-sm text-blue-200">&copy; {{ date('Y') }} PT Alpha Graha Computindo</div>
    </div>

    {{-- Form login --}}
    <div class="flex w-full items-center justify-center p-6 lg:w-1/2">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center lg:text-left">
                <div class="mb-2 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-600 text-white lg:hidden">
                    <i class="bi bi-bezier2 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800">Selamat datang kembali</h1>
                <p class="mt-1 text-sm text-slate-500">Masuk untuk melanjutkan ke dashboard Anda.</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Email atau Username</label>
                    <div class="relative">
                        <i class="bi bi-person absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="login" value="{{ old('login') }}" required autofocus
                               class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                               placeholder="nama@perusahaan.com atau username">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Kata Sandi</label>
                    <div class="relative">
                        <i class="bi bi-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" name="password" required
                               class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                               placeholder="••••••••">
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Ingat saya
                    </label>
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    Masuk
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
