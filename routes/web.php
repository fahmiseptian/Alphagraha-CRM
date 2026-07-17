<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityMediaController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\OpportunityDocumentController;
use App\Http\Controllers\OpportunityNoteController;
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

    // Notifikasi
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/dismiss-popups', [NotificationController::class, 'dismissPopups'])->name('notifications.dismiss-popups');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Pelanggan (data EspoCRM)
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{id}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{account}/contacts', [CustomerContactController::class, 'store'])->name('customers.contacts.store');

    // Contact persons (superadmin)
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('/contacts/{id}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::put('/contacts/{id}', [ContactController::class, 'update'])->name('contacts.update');
    });

    // Lead
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{id}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('/leads/{id}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('/leads/{id}', [LeadController::class, 'update'])->name('leads.update');
    Route::patch('/leads/{id}/quick', [LeadController::class, 'quickUpdate'])->name('leads.quick-update');

    // Opportunity / Deal (EspoCRM, dapat diedit)
    Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
    Route::get('/opportunities/create', [OpportunityController::class, 'create'])->name('opportunities.create');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
    Route::get('/opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show');
    Route::get('/opportunities/{opportunity}/edit', [OpportunityController::class, 'edit'])->name('opportunities.edit');
    Route::put('/opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');
    Route::patch('/opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->name('opportunities.stage');
    Route::post('/opportunities/{opportunity}/discount/approve', [OpportunityController::class, 'approveDiscount'])->name('opportunities.discount.approve');
    Route::post('/opportunities/{opportunity}/discount/reject', [OpportunityController::class, 'rejectDiscount'])->name('opportunities.discount.reject');
    Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy');
    Route::post('/opportunities/{opportunity}/documents', [OpportunityDocumentController::class, 'store'])->name('opportunities.documents.store');
    Route::delete('/opportunities/{opportunity}/documents/{media}', [OpportunityDocumentController::class, 'destroy'])->name('opportunities.documents.destroy');
    Route::post('/opportunities/{opportunity}/notes', [OpportunityNoteController::class, 'store'])->name('opportunities.notes.store');
    Route::delete('/opportunities/{opportunity}/notes/{note}', [OpportunityNoteController::class, 'destroy'])->name('opportunities.notes.destroy');

    // Aktivitas & Task
    Route::resource('activities', ActivityController::class)->except(['show']);
    Route::patch('/activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');
    Route::post('/activities/{activity}/media', [ActivityMediaController::class, 'store'])->name('activities.media.store');
    Route::delete('/activities/{activity}/media/{media}', [ActivityMediaController::class, 'destroy'])->name('activities.media.destroy');

    // Penawaran
    Route::get('/quotations/{quotation}/preview', [QuotationController::class, 'preview'])->name('quotations.preview');
    Route::get('/quotations/{quotation}/revisions/{revision}/preview', [QuotationController::class, 'previewRevision'])->name('quotations.revisions.preview');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.status');
    Route::post('/quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::resource('quotations', QuotationController::class);

    // Profil pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/signature', [ProfileController::class, 'uploadSignature'])->name('profile.signature.store');
    Route::delete('/profile/signature', [ProfileController::class, 'destroySignature'])->name('profile.signature.destroy');

    // Area administrator (Superadmin only)
    Route::middleware('role:superadmin')->group(function () {
        Route::resource('templates', TemplateController::class);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
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
