<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityMediaController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WilayahController;
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
use App\Http\Controllers\OpportunityPurchaseOrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\WilayahLookupController;
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

    // Lookup wilayah (DB; kecamatan lazy-sync dari API bila kosong)
    Route::get('/wilayah/provinces', [WilayahLookupController::class, 'provinces'])->name('wilayah.provinces');
    Route::get('/wilayah/regencies', [WilayahLookupController::class, 'regencies'])->name('wilayah.regencies');
    Route::get('/wilayah/districts', [WilayahLookupController::class, 'districts'])->name('wilayah.districts');

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
    Route::post('/opportunities/{opportunity}/discount/revert', [OpportunityController::class, 'revertDiscount'])->name('opportunities.discount.revert');
    Route::post('/opportunities/{opportunity}/margin/approve', [OpportunityController::class, 'approveMargin'])->name('opportunities.margin.approve');
    Route::post('/opportunities/{opportunity}/margin/reject', [OpportunityController::class, 'rejectMargin'])->name('opportunities.margin.reject');
    Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy');
    Route::post('/opportunities/{opportunity}/documents', [OpportunityDocumentController::class, 'store'])->name('opportunities.documents.store');
    Route::delete('/opportunities/{opportunity}/documents/{media}', [OpportunityDocumentController::class, 'destroy'])->name('opportunities.documents.destroy');
    Route::post('/opportunities/{opportunity}/notes', [OpportunityNoteController::class, 'store'])->name('opportunities.notes.store');
    Route::delete('/opportunities/{opportunity}/notes/{note}', [OpportunityNoteController::class, 'destroy'])->name('opportunities.notes.destroy');
    Route::get('/opportunities/{opportunity}/purchase-orders/preview', [OpportunityPurchaseOrderController::class, 'preview'])->name('opportunities.purchase-orders.preview');
    Route::get('/opportunities/{opportunity}/purchase-orders/pdf', [OpportunityPurchaseOrderController::class, 'pdf'])->name('opportunities.purchase-orders.pdf');
    Route::post('/opportunities/{opportunity}/purchase-orders', [OpportunityPurchaseOrderController::class, 'store'])->name('opportunities.purchase-orders.store');
    Route::put('/opportunities/{opportunity}/purchase-orders/{purchaseOrder}', [OpportunityPurchaseOrderController::class, 'update'])->name('opportunities.purchase-orders.update');
    Route::delete('/opportunities/{opportunity}/purchase-orders/{purchaseOrder}', [OpportunityPurchaseOrderController::class, 'destroy'])->name('opportunities.purchase-orders.destroy');
    Route::put('/opportunities/{opportunity}/shipping-cost', [OpportunityPurchaseOrderController::class, 'updateShipping'])->name('opportunities.shipping-cost.update');

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
    Route::post('/quotations/{quotation}/margin/approve', [QuotationController::class, 'approveMargin'])->name('quotations.margin.approve');
    Route::post('/quotations/{quotation}/margin/reject', [QuotationController::class, 'rejectMargin'])->name('quotations.margin.reject');
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
        Route::get('/settings/margin', [SettingController::class, 'editMargin'])->name('settings.margin.edit');
        Route::put('/settings/margin', [SettingController::class, 'updateMargin'])->name('settings.margin.update');
        Route::get('/settings/po', [SettingController::class, 'editPo'])->name('settings.po.edit');
        Route::put('/settings/po', [SettingController::class, 'updatePo'])->name('settings.po.update');
        Route::get('/settings/terms', [SettingController::class, 'editTerms'])->name('settings.terms.edit');
        Route::put('/settings/terms', [SettingController::class, 'updateTerms'])->name('settings.terms.update');
        Route::get('/settings/shipping', [SettingController::class, 'editShipping'])->name('settings.shipping.edit');
        Route::put('/settings/shipping', [SettingController::class, 'updateShipping'])->name('settings.shipping.update');
        Route::get('/settings/wilayah', [WilayahController::class, 'index'])->name('settings.wilayah.index');
        Route::post('/settings/wilayah/sync', [WilayahController::class, 'sync'])->name('settings.wilayah.sync');
        Route::post('/settings/wilayah/districts/sync', [WilayahController::class, 'syncDistricts'])->name('settings.wilayah.districts.sync');
        Route::post('/settings/wilayah/provinces', [WilayahController::class, 'storeProvince'])->name('settings.wilayah.provinces.store');
        Route::post('/settings/wilayah/regencies', [WilayahController::class, 'storeRegency'])->name('settings.wilayah.regencies.store');
        Route::post('/settings/wilayah/districts', [WilayahController::class, 'storeDistrict'])->name('settings.wilayah.districts.store');
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
