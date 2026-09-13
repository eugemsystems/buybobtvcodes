<?php

use App\Support\CurrentStore;

if (! function_exists('site_name')) {
    /**
     * The public-facing brand name: the current reseller's store name when
     * viewing their storefront, falling back to the platform's app name.
     */
    function site_name(): string
    {
        return app(CurrentStore::class)->get()?->name
            ?: config('app.name');
    }
}

if (! function_exists('fmt_price')) {
    /**
     * Format a monetary amount — whole numbers show without decimals (R50),
     * amounts with cents keep two decimal places (R50.50).
     */
    function fmt_price(float|int|string $amount): string
    {
        $val = (float) $amount;

        return fmod($val, 1.0) === 0.0
            ? number_format($val, 0)
            : number_format($val, 2);
    }
}
