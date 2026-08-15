<?php

use App\Http\Middleware\EnsureReseller;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveDefaultStore;
use App\Http\Middleware\ResolveStoreFromRoute;
use App\Http\Middleware\ResolveStoreFromSubdomain;
use App\Http\Middleware\ResolveStoreFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super-admin' => EnsureSuperAdmin::class,
            'resolve-default-store' => ResolveDefaultStore::class,
            'reseller' => EnsureReseller::class,
            'resolve-store-from-user' => ResolveStoreFromUser::class,
            'resolve-store-from-subdomain' => ResolveStoreFromSubdomain::class,
            'resolve-store-from-route' => ResolveStoreFromRoute::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/payfast/notify',
            '/snapscan/notify',
            '/peach/return',
            '/whop/webhook',
            '/stripe/webhook',
            '/pesepay/result',
        ]);
        // PayFast IPN signature is computed against ALL posted fields including empty ones.
        // Prevent the middleware from converting empty strings to null on these routes.
        $middleware->convertEmptyStringsToNull([
            fn (Request $request) => $request->is('payfast/notify', 'snapscan/notify'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
