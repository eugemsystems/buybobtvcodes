<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'reseller', 'resolve-store-from-user'])->prefix('reseller')->name('reseller.')->group(function () {
    Route::livewire('/', 'pages::reseller.dashboard')->name('dashboard');
    Route::livewire('/analytics', 'pages::reseller.analytics')->name('analytics');
    Route::livewire('/categories', 'pages::reseller.categories')->name('categories');
    Route::livewire('/tokens', 'pages::reseller.tokens')->name('tokens');
    Route::livewire('/transactions', 'pages::reseller.transactions')->name('transactions');
    Route::livewire('/wallet', 'pages::reseller.wallet')->name('wallet');
});
