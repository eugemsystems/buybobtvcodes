<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets a reseller save a logo url for their store', function () {
    $store = Store::factory()->create(['logo' => null]);
    $reseller = User::factory()->reseller($store)->create();

    Livewire::actingAs($reseller)
        ->test('pages::reseller.settings')
        ->set('logoMode', 'url')
        ->set('logoUrl', 'https://example.com/my-logo.png')
        ->call('saveLogo')
        ->assertSet('currentLogoUrl', 'https://example.com/my-logo.png');

    expect($store->fresh()->logo)->toBe('https://example.com/my-logo.png');
});

it('lets a reseller upload a logo file for their store', function () {
    Storage::fake('public');

    $store = Store::factory()->create(['logo' => null]);
    $reseller = User::factory()->reseller($store)->create();

    Livewire::actingAs($reseller)
        ->test('pages::reseller.settings')
        ->set('logoMode', 'file')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
        ->call('saveLogo');

    $store->refresh();
    expect($store->logo)->not->toBeNull();
    Storage::disk('public')->assertExists($store->logo);
});

it('lets a reseller remove their store logo', function () {
    $store = Store::factory()->create(['logo' => 'store-logos/existing.png']);
    $reseller = User::factory()->reseller($store)->create();

    Livewire::actingAs($reseller)
        ->test('pages::reseller.settings')
        ->call('removeLogo')
        ->assertSet('currentLogoUrl', '');

    expect($store->fresh()->logo)->toBeNull();
});

it('shows the reseller\'s own store logo on their dashboard sidebar', function () {
    $store = Store::factory()->create(['logo' => 'https://example.com/store-logo.png']);
    $reseller = User::factory()->reseller($store)->create();

    $this->actingAs($reseller)
        ->get('/reseller')
        ->assertSee('https://example.com/store-logo.png', false);
});

it('shows the reseller\'s own store name on their dashboard, not the platform app name', function () {
    $store = Store::factory()->create(['name' => "Mike's Vouchers"]);
    $reseller = User::factory()->reseller($store)->create();

    $response = $this->actingAs($reseller)->get('/reseller');

    $response->assertSee("Mike's Vouchers");
    $response->assertDontSee(config('app.name'));
});
