<?php

use App\Models\Category;
use App\Models\Store;
use App\Models\Token;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Drawer\Utils;

uses(RefreshDatabase::class);

/**
 * Livewire::test() bypasses the real HTTP kernel and route middleware entirely
 * (it disables middleware and dispatches through a fake route), so it can't catch
 * a bug where CurrentStore only gets bound on the initial page load and never
 * again on the AJAX "update" request behind every wire:click. This test drives
 * the real two-phase flow instead: a genuine GET for the initial render, then a
 * genuine POST to Livewire's real update endpoint carrying that snapshot forward
 * — the same request shape the browser actually sends.
 *
 * CurrentStore is explicitly reset between the two calls: Laravel's test harness
 * reuses one application container across $this->get()/postJson() within a test,
 * so the singleton would otherwise silently carry over from the first call and
 * mask the exact bug this test exists to catch (real PHP-FPM requests never share
 * that state — each one gets a fresh container).
 */
function callLivewireAction(string $snapshotJson, string $method): TestResponse
{
    app(CurrentStore::class)->set(null);

    $uri = app('livewire')->getUpdateUri();

    return test()->postJson($uri, [
        'components' => [[
            'snapshot' => $snapshotJson,
            'calls' => [['method' => $method, 'params' => [], 'path' => '']],
            'updates' => [],
        ]],
    ], ['X-Livewire' => true]);
}

it('keeps the reseller categories list scoped after a real wire:click action, not just on initial load', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    Category::factory()->create(['store_id' => $storeA->id, 'name' => 'Store A Product']);
    Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Store B Product']);

    $resellerA = User::factory()->reseller($storeA)->create();

    $initial = test()->actingAs($resellerA)->get('/reseller/categories');
    $initial->assertOk()->assertSee('Store A Product')->assertDontSee('Store B Product');

    $snapshot = Utils::extractAttributeDataFromHtml($initial->getContent(), 'wire:snapshot');

    $response = callLivewireAction(json_encode($snapshot), 'openCreate');

    $response->assertOk();
    $html = collect($response->json('components'))->first()['effects']['html'] ?? '';

    expect($html)->toContain('Store A Product');
    expect($html)->not->toContain('Store B Product');
});

it('keeps the reseller tokens list scoped after a real wire:click action', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $categoryA = Category::factory()->create(['store_id' => $storeA->id, 'name' => 'Store A Category']);
    $categoryB = Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Store B Category']);
    Token::factory()->for($categoryA)->create();
    Token::factory()->for($categoryB)->create();

    $resellerA = User::factory()->reseller($storeA)->create();

    $initial = test()->actingAs($resellerA)->get('/reseller/tokens');
    $initial->assertOk()->assertSee('Store A Category')->assertDontSee('Store B Category');

    $snapshot = Utils::extractAttributeDataFromHtml($initial->getContent(), 'wire:snapshot');

    $response = callLivewireAction(json_encode($snapshot), 'openImport');

    $response->assertOk();
    $html = collect($response->json('components'))->first()['effects']['html'] ?? '';

    expect($html)->toContain('Store A Category');
    expect($html)->not->toContain('Store B Category');
});

it('keeps the storefront shop page scoped to its subdomain\'s store after a real wire:click action', function () {
    $storeA = Store::factory()->create(['slug' => 'alpha']);
    $storeB = Store::factory()->create(['slug' => 'beta']);
    Category::factory()->create(['store_id' => $storeA->id, 'name' => 'Alpha Product']);
    Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Beta Product']);

    $url = 'http://alpha.'.config('tenancy.base_domain').'/store';

    $initial = test()->get($url);
    $initial->assertOk()->assertSee('Alpha Product')->assertDontSee('Beta Product');

    $snapshot = Utils::extractAttributeDataFromHtml($initial->getContent(), 'wire:snapshot');

    $response = callLivewireAction(json_encode($snapshot), 'clearCart');

    $response->assertOk();
    $html = collect($response->json('components'))->first()['effects']['html'] ?? '';

    expect($html)->toContain('Alpha Product');
    expect($html)->not->toContain('Beta Product');
});
