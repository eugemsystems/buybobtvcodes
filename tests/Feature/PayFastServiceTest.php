<?php

use App\Services\PayFastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

it('sends return_url and cancel_url derived from the current request, not the static app URL', function () {
    // Simulate a customer checking out on a reseller's subdomain — the current
    // request's host, not config('app.url'), is what PayFast must receive.
    app()->instance('request', Request::create('https://mike.user-tokenguy.com/checkout', 'POST'));

    Http::fake([
        '*/onsite/process' => Http::response(['uuid' => 'test-uuid'], 200),
    ]);

    app(PayFastService::class)->initiatePayment([
        'amount' => 99.00,
        'item_name' => 'Test Product',
        'customer_email' => 'buyer@example.com',
        'reference' => 'ref-123',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'return_url='.urlencode('https://mike.user-tokenguy.com'))
            && str_contains($body, 'cancel_url='.urlencode('https://mike.user-tokenguy.com'));
    });
});

it('produces a well-formed absolute return_url even when APP_URL is misconfigured', function () {
    config(['app.url' => 'not-a-valid-url']);
    app()->instance('request', Request::create('https://demo.user-tokenguy.com/checkout', 'POST'));

    Http::fake([
        '*/onsite/process' => Http::response(['uuid' => 'test-uuid'], 200),
    ]);

    app(PayFastService::class)->initiatePayment([
        'amount' => 49.00,
        'item_name' => 'Test Product',
        'customer_email' => 'buyer@example.com',
        'reference' => 'ref-456',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->body(), 'return_url='.urlencode('https://demo.user-tokenguy.com')));
});
