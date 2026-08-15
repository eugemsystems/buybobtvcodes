<?php

use App\Models\Category;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Drawer\Utils;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets a super-admin view a specific store\'s categories regardless of the catalog setting', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();
    Category::factory()->create(['store_id' => $store->id, 'name' => 'Managed Product']);

    Livewire::actingAs($admin)
        ->test('pages::admin.store-categories', ['store' => $store])
        ->assertOk()
        ->assertSee('Managed Product');
});

it('lets a super-admin create a category for a specific store that persists correctly on a real wire:click action', function () {
    $admin = User::factory()->create();
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Store B Existing']);

    $url = "/admin/stores/{$storeA->id}/categories";
    $initial = test()->actingAs($admin)->get($url);
    $initial->assertOk()->assertDontSee('Store B Existing');

    $snapshot = Utils::extractAttributeDataFromHtml($initial->getContent(), 'wire:snapshot');

    $updateUri = app('livewire')->getUpdateUri();
    $response = test()->postJson($updateUri, [
        'components' => [[
            'snapshot' => json_encode($snapshot),
            'calls' => [[
                'method' => 'save',
                'params' => [],
                'path' => '',
            ]],
            'updates' => [
                'name' => 'Store A New Product',
                'price' => '49.99',
            ],
        ]],
    ], ['X-Livewire' => true]);

    $response->assertOk();

    $created = Category::where('name', 'Store A New Product')->first();
    expect($created)->not->toBeNull();
    expect($created->store_id)->toBe($storeA->id);
});

it('denies a reseller access to the admin per-store catalog pages', function () {
    $store = Store::factory()->create();
    $reseller = User::factory()->reseller($store)->create();

    test()->actingAs($reseller)->get("/admin/stores/{$store->id}/categories")->assertForbidden();
    test()->actingAs($reseller)->get("/admin/stores/{$store->id}/tokens")->assertForbidden();
});

it('makes the reseller catalog pages read-only and the admin pages fully usable when catalog_managed_by_admin is on', function () {
    Setting::set('catalog_managed_by_admin', '1');

    $admin = User::factory()->create();
    $store = Store::factory()->create();
    $reseller = User::factory()->reseller($store)->create();
    Category::factory()->create(['store_id' => $store->id, 'name' => 'Shared Product']);

    // Reseller: read-only, no create/edit/delete buttons, mutating actions blocked server-side.
    $resellerResponse = test()->actingAs($reseller)->get('/reseller/categories');
    $resellerResponse->assertOk();
    $resellerResponse->assertSee('managed by the platform admin');
    $resellerResponse->assertDontSee('wire:click="openCreate"', false);

    Livewire::actingAs($reseller)
        ->test('pages::reseller.categories')
        ->call('openCreate')
        ->assertForbidden();

    // Admin: fully usable regardless of the setting.
    Livewire::actingAs($admin)
        ->test('pages::admin.store-categories', ['store' => $store])
        ->assertOk()
        ->assertSee('Shared Product')
        ->call('openCreate')
        ->assertSet('showModal', true);
});
