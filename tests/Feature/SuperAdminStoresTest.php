<?php

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

it('lets a super-admin update a reseller\'s login email and password', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();
    $owner = User::factory()->reseller($store)->create(['email' => 'old@example.com']);
    $store->update(['owner_id' => $owner->id]);

    Livewire::actingAs($admin)
        ->test('pages::admin.stores')
        ->call('openEdit', $store->id)
        ->assertSet('editOwnerEmail', 'old@example.com')
        ->set('editOwnerEmail', 'new-login@example.com')
        ->set('editOwnerPassword', 'brand-new-password')
        ->set('editOwnerPasswordConfirmation', 'brand-new-password')
        ->call('saveEdit')
        ->assertSet('showEditModal', false);

    $owner->refresh();
    expect($owner->email)->toBe('new-login@example.com');
    expect(Hash::check('brand-new-password', $owner->password))->toBeTrue();
});

it('lets a super-admin update a reseller\'s login email without changing the password', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();
    $owner = User::factory()->reseller($store)->create(['email' => 'old@example.com']);
    $store->update(['owner_id' => $owner->id]);
    $originalPassword = $owner->password;

    Livewire::actingAs($admin)
        ->test('pages::admin.stores')
        ->call('openEdit', $store->id)
        ->set('editOwnerEmail', 'new-login@example.com')
        ->call('saveEdit');

    $owner->refresh();
    expect($owner->email)->toBe('new-login@example.com');
    expect($owner->password)->toBe($originalPassword);
});

it('rejects a reseller login email already used by another account', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();
    $owner = User::factory()->reseller($store)->create(['email' => 'old@example.com']);
    $store->update(['owner_id' => $owner->id]);
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::admin.stores')
        ->call('openEdit', $store->id)
        ->set('editOwnerEmail', 'taken@example.com')
        ->call('saveEdit')
        ->assertHasErrors(['editOwnerEmail']);

    expect($owner->fresh()->email)->toBe('old@example.com');
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
