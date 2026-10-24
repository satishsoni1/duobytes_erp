<?php

use App\Http\Controllers\Webhooks\LeadWebhookController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use App\Http\Controllers\Webhooks\TallyWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Middleware\VerifyWebhookToken;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:webhooks')->prefix('webhooks')->name('webhooks.')->group(function () {
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp');

    Route::get('meta', [MetaWebhookController::class, 'verify'])->name('meta.verify');
    Route::post('meta', [MetaWebhookController::class, 'receive'])->name('meta');

    Route::post('google-ads', [LeadWebhookController::class, 'googleAds'])->name('google-ads');

    Route::middleware(VerifyWebhookToken::class)->group(function () {
        Route::post('leads/{token?}', [LeadWebhookController::class, 'generic'])->name('leads');
        Route::post('email/{token?}', [LeadWebhookController::class, 'email'])->name('email');
        Route::post('indiamart/{token?}', [LeadWebhookController::class, 'indiamart'])->name('indiamart');
        Route::get('tally/request/{token?}', [TallyWebhookController::class, 'request'])->name('tally.request');
        Route::post('tally/{token?}', [TallyWebhookController::class, 'receive'])->name('tally');
    });
});
