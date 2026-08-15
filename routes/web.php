<?php

use App\Http\Controllers\DpoCallbackController;
use App\Http\Controllers\FlutterwaveCallbackController;
use App\Http\Controllers\PayFastIpnController;
use App\Http\Controllers\PaystackCallbackController;
use App\Http\Controllers\PeachCallbackController;
use App\Http\Controllers\PesepayResultController;
use App\Http\Controllers\SnapScanWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WhopWebhookController;
use Illuminate\Support\Facades\Route;

// Registered first: domain-scoped storefront routes must be tried before the
// bare-domain fallback below, since a route without ->domain() matches any host.
require __DIR__.'/store.php';

Route::get('/', fn () => redirect()->route('login'))->name('home.bare');

Route::view('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');
Route::view('/terms-of-service', 'pages.terms-of-service')->name('terms-of-service');
Route::view('/refund-policy', 'pages.refund-policy')->name('refund-policy');
Route::view('/cancellation-policy', 'pages.cancellation-policy')->name('cancellation-policy');

Route::post('/payfast/notify', PayFastIpnController::class)->name('payfast.notify');
Route::post('/snapscan/notify', SnapScanWebhookController::class)->name('snapscan.notify');
Route::get('/dpo/return', [DpoCallbackController::class, 'return'])->name('dpo.return');
Route::get('/dpo/cancel', [DpoCallbackController::class, 'cancel'])->name('dpo.cancel');

Route::post('/peach/return', [PeachCallbackController::class, 'return'])->name('peach.return');
Route::get('/peach/cancel', [PeachCallbackController::class, 'cancel'])->name('peach.cancel');

Route::get('/flutterwave/callback', [FlutterwaveCallbackController::class, 'callback'])->name('flutterwave.callback');
Route::get('/paystack/callback', [PaystackCallbackController::class, 'callback'])->name('paystack.callback');
Route::post('/whop/webhook', WhopWebhookController::class)->name('whop.webhook');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::post('/pesepay/result', [PesepayResultController::class, 'result'])->name('pesepay.result');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->to(auth()->user()->isSuperAdmin() ? '/admin' : '/reseller'))->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/reseller.php';
