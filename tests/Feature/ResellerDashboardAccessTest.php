<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets a reseller see only their own store dashboard numbers', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $categoryA = Category::factory()->create(['store_id' => $storeA->id]);
    $categoryB = Category::factory()->create(['store_id' => $storeB->id]);

    Transaction::factory()->completed()->create(['store_id' => $storeA->id, 'category_id' => $categoryA->id, 'amount' => 100]);
    Transaction::factory()->completed()->create(['store_id' => $storeB->id, 'category_id' => $categoryB->id, 'amount' => 999]);

    $resellerA = User::factory()->reseller($storeA)->create();

    Livewire::actingAs($resellerA)
        ->test('pages::reseller.dashboard')
        ->assertSee('R100')
        ->assertDontSee('R999');
});

it('denies a reseller access to any /admin route', function () {
    $reseller = User::factory()->reseller()->create();

    $this->actingAs($reseller)->get('/admin')->assertForbidden();
    $this->actingAs($reseller)->get('/admin/stores')->assertForbidden();
});

it('denies a super-admin access to /reseller routes', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->get('/reseller')->assertForbidden();
});

it('fails closed for a reseller with no store assigned', function () {
    $reseller = User::factory()->create();
    $reseller->forceFill(['role' => UserRole::Reseller, 'store_id' => null])->save();

    $this->actingAs($reseller)->get('/reseller')->assertForbidden();
});

it('redirects /dashboard to the right home based on role', function () {
    $admin = User::factory()->create();
    $reseller = User::factory()->reseller()->create();

    $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin');
    $this->actingAs($reseller)->get('/dashboard')->assertRedirect('/reseller');
});
