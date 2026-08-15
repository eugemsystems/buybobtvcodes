<?php

namespace App\Http\Middleware;

use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreFromUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->store;

        abort_unless($store, 403);

        app(CurrentStore::class)->set($store);

        return $next($request);
    }
}
