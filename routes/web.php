<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TallyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
});

// Customer-facing documents shared over WhatsApp/email via expiring signed links.
Route::middleware('signed')->group(function () {
    Route::get('q/{quotation}', [QuotationController::class, 'publicShow'])->name('public.quotation');
    Route::get('i/{order}', [SalesOrderController::class, 'publicInvoice'])->name('public.invoice');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::view('help', 'help.index')->name('help.index');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('search', [SearchController::class, 'global'])->name('search');
    Route::get('lookup/customers', [SearchController::class, 'customers'])->name('lookup.customers');
    Route::get('lookup/products', [SearchController::class, 'products'])->name('lookup.products');

    // Shared timeline features
    Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::post('follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::delete('follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');

    // Leads
    Route::middleware('access:leads')->group(function () {
        Route::get('leads/board', [LeadController::class, 'board'])->name('leads.board');
        Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
        Route::get('leads/import', [LeadController::class, 'importForm'])->name('leads.import');
        Route::post('leads/import', [LeadController::class, 'import'])->name('leads.import.store');
        Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
        Route::patch('leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
        Route::post('leads/bulk', [LeadController::class, 'bulk'])->name('leads.bulk');
        Route::resource('leads', LeadController::class);
    });

    // Customers
    Route::middleware('access:customers')->group(function () {
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::resource('customers', CustomerController::class);
    });

    // Sales
    Route::middleware('access:sales')->group(function () {
        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
        Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.status');
        Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
        Route::resource('quotations', QuotationController::class);

        Route::get('orders/{order}/invoice', [SalesOrderController::class, 'invoice'])->name('orders.invoice');
        Route::patch('orders/{order}/status', [SalesOrderController::class, 'updateStatus'])->name('orders.status');
        Route::resource('orders', SalesOrderController::class);

        Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('payments.store')->middleware('access:payments');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy')->middleware('access:payments');
    });

    // Inventory
    Route::middleware('access:inventory.view')->group(function () {
        Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    });
    Route::middleware('access:inventory')->group(function () {
        Route::resource('products', ProductController::class)->except(['index', 'show']);
        Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
        Route::get('stock/adjust', [StockController::class, 'adjustForm'])->name('stock.adjust');
        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust.store');
        Route::get('stock/transfer', [StockController::class, 'transferForm'])->name('stock.transfer');
        Route::post('stock/transfer', [StockController::class, 'transfer'])->name('stock.transfer.store');
        Route::resource('categories', CategoryController::class)->except(['show', 'create']);
        Route::resource('warehouses', WarehouseController::class)->except(['show', 'create']);
    });
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show')->middleware('access:inventory.view');

    Route::middleware('access:purchases')->group(function () {
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::get('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveForm'])->name('purchase-orders.receive');
        Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive.store');
        Route::patch('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])->name('purchase-orders.status');
        Route::resource('purchase-orders', PurchaseOrderController::class);
    });

    // Installations & after-sales service
    Route::middleware('access:installations')->group(function () {
        Route::get('installations/calendar', [InstallationController::class, 'calendar'])->name('installations.calendar');
        Route::patch('installations/{installation}/status', [InstallationController::class, 'updateStatus'])->name('installations.status');
        Route::resource('installations', InstallationController::class);
    });
    Route::middleware('access:service')->group(function () {
        Route::patch('tickets/{ticket}/status', [ServiceTicketController::class, 'updateStatus'])->name('tickets.status');
        Route::resource('tickets', ServiceTicketController::class);
    });

    // WhatsApp
    Route::middleware('access:whatsapp')->prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [WhatsAppController::class, 'index'])->name('index');
        Route::get('templates', [WhatsAppController::class, 'templates'])->name('templates');
        Route::post('templates/sync', [WhatsAppController::class, 'syncTemplates'])->name('templates.sync');
        Route::get('media/{message}', [WhatsAppController::class, 'media'])->name('media');
        Route::post('broadcast', [WhatsAppController::class, 'broadcast'])->name('broadcast');
        Route::get('chat/{phone}', [WhatsAppController::class, 'show'])->name('show');
        Route::post('chat/{phone}', [WhatsAppController::class, 'send'])->name('send');
    });

    Route::middleware('access:reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::middleware('access:tally')->prefix('tally')->name('tally.')->group(function () {
        Route::get('/', [TallyController::class, 'index'])->name('index');
        Route::put('/', [TallyController::class, 'update'])->name('update');
        Route::post('sync', [TallyController::class, 'sync'])->name('sync');
        Route::post('upload', [TallyController::class, 'upload'])->name('upload');
        Route::post('map-item', [TallyController::class, 'mapItem'])->name('map-item');
    });

    Route::middleware('access:users')->resource('users', UserController::class)->except(['show']);

    Route::middleware('access:settings')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/regenerate-token', [SettingController::class, 'regenerateToken'])->name('settings.token');
        Route::get('settings/webhook-logs', [SettingController::class, 'webhookLogs'])->name('settings.webhook-logs');
    });
});
