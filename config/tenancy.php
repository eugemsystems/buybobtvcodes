<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Base Domain
    |--------------------------------------------------------------------------
    |
    | Reseller storefronts are served from subdomains of this domain, e.g.
    | "mike.{base_domain}". Locally this matches the Herd-served site domain.
    |
    */

    'base_domain' => env('TENANT_BASE_DOMAIN', 'user-tokenguy.test'),

];
