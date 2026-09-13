<?php

use App\Models\Category;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function storeUrl(string $slug, string $path = '/'): string
{
    return 'http://'.$slug.'.'.config('tenancy.base_domain').$path;
}

it('resolves a known subdomain to its own store catalog only', function () {
    $storeA = Store::factory()->create(['slug' => 'alpha']);
    $storeB = Store::factory()->create(['slug' => 'beta']);

    Category::factory()->create(['store_id' => $storeA->id, 'name' => 'Alpha Product']);
    Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Beta Product']);

    $response = $this->get(storeUrl('alpha'));

    $response->assertOk();
    $response->assertSee('Alpha Product');
    $response->assertDontSee('Beta Product');
});

it('404s for an unknown subdomain', function () {
    $this->get(storeUrl('nonexistent'))->assertNotFound();
});

it('404s for a disabled store subdomain', function () {
    Store::factory()->create(['slug' => 'paused', 'is_active' => false]);

    $this->get(storeUrl('paused'))->assertNotFound();
});

it('redirects the bare domain to login instead of serving a storefront', function () {
    $this->get('http://'.config('tenancy.base_domain').'/')
        ->assertRedirect(route('login'));
});

it('shows the WhatsApp chat widget on the storefront', function () {
    Store::factory()->create(['slug' => 'alpha']);

    $this->get(storeUrl('alpha'))
        ->assertOk()
        ->assertSee('https://wa.me/27787965339', false);
});
