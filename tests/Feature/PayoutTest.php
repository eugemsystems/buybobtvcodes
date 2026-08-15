<?php

use App\Enums\TransactionStatus;
use App\Models\Payout;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets a super-admin record a payout that reduces the store wallet balance', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create(['commission_rate' => 10]);
    Transaction::factory()->create(['store_id' => $store->id, 'amount' => 1000, 'status' => TransactionStatus::Pending])
        ->update(['status' => TransactionStatus::Completed]);

    Livewire::actingAs($admin)
        ->test('pages::admin.store-report', ['store' => $store])
        ->assertSet('walletBalance', 100.0)
        ->set('payoutAmount', '40')
        ->set('payoutNote', 'First payout')
        ->call('recordPayout')
        ->assertSet('showPayoutModal', false)
        ->assertSet('walletBalance', 60.0);

    $payout = Payout::where('store_id', $store->id)->first();
    expect($payout)->not->toBeNull();
    expect((float) $payout->amount)->toBe(40.0);
    expect($payout->paid_by_id)->toBe($admin->id);
});

it('sums wallet balance correctly across multiple commission entries and payouts', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create(['commission_rate' => 10]);

    foreach ([100, 200, 300] as $amount) {
        Transaction::factory()->create(['store_id' => $store->id, 'amount' => $amount, 'status' => TransactionStatus::Pending])
            ->update(['status' => TransactionStatus::Completed]);
    }
    // Total commission = 10% of (100+200+300) = 60

    Payout::factory()->create(['store_id' => $store->id, 'amount' => 15]);
    Payout::factory()->create(['store_id' => $store->id, 'amount' => 10]);

    Livewire::actingAs($admin)
        ->test('pages::admin.store-report', ['store' => $store])
        ->assertSet('commissionEarned', 60.0)
        ->assertSet('totalPaidOut', 25.0)
        ->assertSet('walletBalance', 35.0);
});

it('never shows another store\'s payouts or commission on a reseller\'s wallet page', function () {
    $storeA = Store::factory()->create(['commission_rate' => 10]);
    $storeB = Store::factory()->create(['commission_rate' => 10]);

    Transaction::factory()->create(['store_id' => $storeA->id, 'amount' => 100, 'status' => TransactionStatus::Pending])
        ->update(['status' => TransactionStatus::Completed]);
    Transaction::factory()->create(['store_id' => $storeB->id, 'amount' => 900, 'status' => TransactionStatus::Pending])
        ->update(['status' => TransactionStatus::Completed]);

    Payout::factory()->create(['store_id' => $storeA->id, 'amount' => 5, 'note' => 'Store A payout note']);
    Payout::factory()->create(['store_id' => $storeB->id, 'amount' => 500, 'note' => 'Store B payout note']);

    $resellerA = User::factory()->reseller($storeA)->create();

    $response = test()->actingAs($resellerA)->get('/reseller/wallet');

    $response->assertOk();
    $response->assertSee('R10'); // storeA's commission earned (10% of 100)
    $response->assertDontSee('R90'); // storeB's commission earned would be 10% of 900
    $response->assertSee('Store A payout note');
    $response->assertDontSee('Store B payout note');
});

it('denies a reseller from accessing another store\'s admin store-report wallet data', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();
    $resellerA = User::factory()->reseller($storeA)->create();

    test()->actingAs($resellerA)->get("/admin/stores/{$storeB->id}")->assertForbidden();
});
