<?php

use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Store;
use App\Models\Token;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// List-view isolation is exercised via real HTTP requests so the actual
// reseller/resolve-store-from-user middleware chain runs, not just the
// Livewire component in isolation.

it('never lists another store\'s categories on the reseller categories page', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    Category::factory()->create(['store_id' => $storeA->id, 'name' => 'Store A Product']);
    Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Store B Product']);

    $resellerA = User::factory()->reseller($storeA)->create();

    $response = $this->actingAs($resellerA)->get('/reseller/categories');

    $response->assertOk();
    $response->assertSee('Store A Product');
    $response->assertDontSee('Store B Product');
});

it('never lists another store\'s tokens on the reseller tokens page', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $categoryA = Category::factory()->create(['store_id' => $storeA->id, 'name' => 'Store A Category']);
    $categoryB = Category::factory()->create(['store_id' => $storeB->id, 'name' => 'Store B Category']);
    Token::factory()->for($categoryA)->create();
    Token::factory()->for($categoryB)->create();

    $resellerA = User::factory()->reseller($storeA)->create();

    $response = $this->actingAs($resellerA)->get('/reseller/tokens');

    $response->assertOk();
    $response->assertSee('Store A Category');
    $response->assertDontSee('Store B Category');
});

it('never shows another store\'s transactions on the reseller transactions page', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    Transaction::factory()->create(['store_id' => $storeA->id, 'customer_email' => 'a@storea.test']);
    Transaction::factory()->create(['store_id' => $storeB->id, 'customer_email' => 'b@storeb.test']);

    $resellerA = User::factory()->reseller($storeA)->create();

    $response = $this->actingAs($resellerA)->get('/reseller/transactions');

    $response->assertOk();
    $response->assertSee('a@storea.test');
    $response->assertDontSee('b@storeb.test');
});

// Action-level isolation calls Livewire component methods directly, so
// CurrentStore is bound manually to reproduce what the route middleware
// would have set up for a real request.

it('blocks a reseller from editing another store\'s category by id', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $categoryB = Category::factory()->create(['store_id' => $storeB->id]);

    $resellerA = User::factory()->reseller($storeA)->create();
    app(CurrentStore::class)->set($storeA);

    expect(fn () => Livewire::actingAs($resellerA)
        ->test('pages::reseller.categories')
        ->call('openEdit', $categoryB->id)
    )->toThrow(ModelNotFoundException::class);
});

it('blocks a reseller from deleting another store\'s category by id', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $categoryB = Category::factory()->create(['store_id' => $storeB->id]);

    $resellerA = User::factory()->reseller($storeA)->create();
    app(CurrentStore::class)->set($storeA);

    expect(fn () => Livewire::actingAs($resellerA)
        ->test('pages::reseller.categories')
        ->set('deletingId', $categoryB->id)
        ->call('destroy')
    )->toThrow(ModelNotFoundException::class);

    expect($categoryB->fresh())->not->toBeNull();
});

it('lets a reseller reprocess only their own pending transaction, never another store\'s', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $transactionB = Transaction::factory()->create(['store_id' => $storeB->id, 'status' => TransactionStatus::Pending]);

    $resellerA = User::factory()->reseller($storeA)->create();

    Livewire::actingAs($resellerA)
        ->test('pages::reseller.transactions')
        ->call('confirmReprocess', $transactionB->id)
        ->call('reprocess');

    expect($transactionB->fresh()->status)->toBe(TransactionStatus::Pending);
});
