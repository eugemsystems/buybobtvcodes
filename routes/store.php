<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::domain('{subdomain}.'.config('tenancy.base_domain'))
    ->middleware('resolve-store-from-subdomain')
    ->group(function () {
        Route::livewire('/', 'pages::storefront')->name('home');
        Route::livewire('/store', 'pages::shop')->name('shop');
        Route::livewire('/checkout', 'pages::checkout')->name('checkout');
        Route::livewire('/order/{transactionId}', 'pages::order')->name('order');

        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    });
