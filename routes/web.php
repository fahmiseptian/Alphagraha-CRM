<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Autentikasi
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Aplikasi (butuh login)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Pelanggan (data EspoCRM, read-only)
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');

    // Lead
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/{id}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{id}', [LeadController::class, 'update'])->name('leads.update');

    // Aktivitas & Task
    Route::resource('activities', ActivityController::class)->except(['show']);
    Route::patch('/activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');

    // Penawaran
    Route::get('/quotations/{quotation}/preview', [QuotationController::class, 'preview'])->name('quotations.preview');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.status');
    Route::post('/quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::resource('quotations', QuotationController::class);

    // Profil pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Area administrator
    Route::middleware('role:admin')->group(function () {
        Route::resource('templates', TemplateController::class);
        Route::resource('users', UserController::class)->except(['show']);
    });
});


Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return "Cache, config, route, and view cleared!";
});

Route::get('/optimize', function () {
    Artisan::call('optimize:clear');
    Artisan::call('optimize');
    return "Application optimized!";
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return "Storage linked!";
});
