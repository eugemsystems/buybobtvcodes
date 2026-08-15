<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('/analytics', 'pages::admin.analytics')->name('analytics');
    Route::livewire('/stores', 'pages::admin.stores')->name('stores');
    Route::livewire('/stores/{store}', 'pages::admin.store-report')->name('stores.show');
    Route::middleware('resolve-store-from-route')->group(function () {
        Route::livewire('/stores/{store}/categories', 'pages::admin.store-categories')->name('stores.categories');
        Route::livewire('/stores/{store}/tokens', 'pages::admin.store-tokens')->name('stores.tokens');
    });
    Route::middleware('resolve-default-store')->group(function () {
        Route::livewire('/categories', 'pages::admin.categories')->name('categories');
        Route::livewire('/tokens', 'pages::admin.tokens')->name('tokens');
        Route::livewire('/transactions', 'pages::admin.transactions')->name('transactions');
    });
    Route::livewire('/gateways', 'pages::admin.gateways')->name('gateways');
    Route::livewire('/admins', 'pages::admin.admins')->name('admins');
    Route::livewire('/settings', 'pages::admin.settings')->name('settings');
    Route::livewire('/webhook-logs', 'pages::admin.webhook-logs')->name('webhook-logs');
    Route::livewire('/pesepay-logs', 'pages::admin.pesepay-logs')->name('pesepay-logs');
    Route::livewire('/pesepay-status-checks', 'pages::admin.pesepay-status-checks')->name('pesepay-status-checks');
    Route::livewire('/queue', 'pages::admin.queue')->name('queue');
});
