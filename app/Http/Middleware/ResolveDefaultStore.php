<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes the super-admin's own legacy catalog pages (/admin/categories, /admin/tokens,
 * /admin/transactions) to the "Default Store" created during the multi-tenancy migration,
 * so new writes there aren't left with a null store_id.
 */
class ResolveDefaultStore
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app(CurrentStore::class)->set(Store::where('slug', 'default')->first());

        return $next($request);
    }
}
