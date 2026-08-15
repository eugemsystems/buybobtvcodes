<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds CurrentStore from a {store} route-model-bound parameter, for admin pages
 * that manage a specific reseller's catalog on their behalf. Must be in Livewire's
 * persistent middleware list (see AppServiceProvider) so it also reruns on every
 * wire:click action, not just the initial page load.
 */
class ResolveStoreFromRoute
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->route('store');

        if ($store instanceof Store) {
            app(CurrentStore::class)->set($store);
        }

        return $next($request);
    }
}
