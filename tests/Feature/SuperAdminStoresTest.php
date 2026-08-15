<?php

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets a super-admin provision a store and its reseller owner', function () {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.stores')
        ->set('newStoreName', "Mike's Store")
        ->set('newSlug', 'mike')
        ->set('newOwnerName', 'Mike Smith')
        ->set('newOwnerEmail', 'mike@example.com')
        ->set('newOwnerPassword', 'password123')
        ->set('newOwnerPasswordConfirmation', 'password123')
        ->call('create')
        ->assertSet('showCreateModal', false);

    $store = Store::where('slug', 'mike')->first();
    expect($store)->not->toBeNull();
    expect($store->name)->toBe("Mike's Store");

    $owner = User::where('email', 'mike@example.com')->first();
    expect($owner)->not->toBeNull();
    expect($owner->role)->toBe(UserRole::Reseller);
    expect($owner->store_id)->toBe($store->id);
    expect($store->fresh()->owner_id)->toBe($owner->id);
});

it('rejects reserved slugs', function () {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.stores')
        ->set('newStoreName', 'Admin Store')
        ->set('newSlug', 'admin')
        ->set('newOwnerName', 'Someone')
        ->set('newOwnerEmail', 'someone@example.com')
        ->set('newOwnerPassword', 'password123')
        ->set('newOwnerPasswordConfirmation', 'password123')
        ->call('create')
        ->assertHasErrors(['newSlug']);
});

it('lets a super-admin drill into a specific store report', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.store-report', ['store' => $store])
        ->assertOk()
        ->assertSee($store->name);
});

it('denies resellers access to the stores page', function () {
    $reseller = User::factory()->reseller()->create();

    $this->actingAs($reseller)->get('/admin/stores')->assertForbidden();
});

it('sums revenue across every store on the admin rollup dashboard', function () {
    $admin = User::factory()->create();

    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    Transaction::factory()->completed()->create(['store_id' => $storeA->id, 'amount' => 100]);
    Transaction::factory()->completed()->create(['store_id' => $storeB->id, 'amount' => 250]);

    Livewire::actingAs($admin)
        ->test('pages::admin.dashboard')
        ->assertSee('R350')
        ->assertSee((string) Store::count());
});
