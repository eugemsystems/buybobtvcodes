<?php

use App\Actions\InitiateCartCheckout;
use App\Contracts\PaymentGateway;
use App\Enums\TokenStatus;
use App\Models\Category;
use App\Models\Store;
use App\Models\Token;
use App\Models\Transaction;
use App\Services\GatewayManager;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mockScopingGateway(): void
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('getKey')->andReturn('payfast');
    $gateway->shouldReceive('getCheckoutType')->andReturn('onsite');
    $gateway->shouldReceive('initiate')->andReturn([
        'success' => true, 'checkout_type' => 'onsite', 'data' => ['uuid' => 'x'], 'message' => '',
    ]);

    $manager = Mockery::mock(GatewayManager::class);
    $manager->shouldReceive('active')->andReturn($gateway);
    app()->instance(GatewayManager::class, $manager);
}

it('stamps a new transaction with the currently resolved store', function () {
    $store = Store::factory()->create();
    $category = Category::factory()->create(['store_id' => $store->id, 'price' => 99.00]);
    Token::factory()->for($category)->create(['status' => TokenStatus::Available]);

    mockScopingGateway();
    app(CurrentStore::class)->set($store);

    $result = app(InitiateCartCheckout::class)->execute(
        cart: [$category->id => 1],
        customerData: ['email' => 'buyer@example.com', 'phone' => '0720000000'],
    );

    expect($result['success'])->toBeTrue();
    $transaction = Transaction::find($result['transaction_id']);
    expect($transaction->store_id)->toBe($store->id);
});

it('refuses to check out a category belonging to a different store', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $categoryB = Category::factory()->create(['store_id' => $storeB->id, 'price' => 99.00]);
    $tokenB = Token::factory()->for($categoryB)->create(['status' => TokenStatus::Available]);

    mockScopingGateway();
    app(CurrentStore::class)->set($storeA);

    // Category::findOrFail() throws ModelNotFoundException (a RuntimeException) once scoped
    // to store A, which InitiateCartCheckout's catch block turns into a clean failure result.
    $result = app(InitiateCartCheckout::class)->execute(
        cart: [$categoryB->id => 1],
        customerData: ['email' => 'buyer@example.com', 'phone' => '0720000000'],
    );

    expect($result['success'])->toBeFalse();
    expect($tokenB->fresh()->status)->toBe(TokenStatus::Available);
    expect(Transaction::count())->toBe(0);
});

it('blocks viewing another store\'s order confirmation page', function () {
    $storeA = Store::factory()->create(['slug' => 'store-a']);
    $storeB = Store::factory()->create(['slug' => 'store-b']);
    $categoryB = Category::factory()->create(['store_id' => $storeB->id]);
    $transaction = Transaction::factory()->completed()->create(['store_id' => $storeB->id]);
    Token::factory()->for($categoryB)->create([
        'status' => TokenStatus::Sold,
        'transaction_id' => $transaction->id,
    ]);

    $response = $this->get('http://store-a.'.config('tenancy.base_domain').'/order/'.$transaction->id);

    $response->assertOk();
    $response->assertDontSee($transaction->customer_email);
});
