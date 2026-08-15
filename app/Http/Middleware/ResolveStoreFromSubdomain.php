<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\CurrentStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreFromSubdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $base = config('tenancy.base_domain');
        $host = $request->getHost();
        $slug = str($host)->before('.'.$base)->value();

        $store = $slug !== $host
            ? Store::where('slug', $slug)->where('is_active', true)->first()
            : null;

        abort_unless($store, 404);

        app(CurrentStore::class)->set($store);

        // Lets route('home')/route('checkout')/etc. resolve their {subdomain} segment
        // automatically for any URL generated within this request.
        URL::defaults(['subdomain' => $store->slug]);

        return $next($request);
    }
}
