<?php

use App\Enums\TransactionStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('snapshots commission using the store\'s own rate when the transaction completes', function () {
    $store = Store::factory()->create(['commission_rate' => 20]);
    $transaction = Transaction::factory()->create(['store_id' => $store->id, 'amount' => 100, 'status' => TransactionStatus::Pending]);

    $transaction->update(['status' => TransactionStatus::Completed]);

    $transaction->refresh();
    expect((float) $transaction->commission_rate_applied)->toBe(20.0);
    expect((float) $transaction->commission_amount)->toBe(20.0);
});

it('falls back to the platform default rate when the store has no override', function () {
    Setting::set('commission_rate_default', '15');
    $store = Store::factory()->create(['commission_rate' => null]);
    $transaction = Transaction::factory()->create(['store_id' => $store->id, 'amount' => 200, 'status' => TransactionStatus::Pending]);

    $transaction->update(['status' => TransactionStatus::Completed]);

    $transaction->refresh();
    expect((float) $transaction->commission_rate_applied)->toBe(15.0);
    expect((float) $transaction->commission_amount)->toBe(30.0);
});

it('does not rewrite already-completed commission when the rate changes afterward', function () {
    $store = Store::factory()->create(['commission_rate' => 10]);
    $transaction = Transaction::factory()->create(['store_id' => $store->id, 'amount' => 100, 'status' => TransactionStatus::Pending]);
    $transaction->update(['status' => TransactionStatus::Completed]);

    expect((float) $transaction->fresh()->commission_amount)->toBe(10.0);

    // Rate changes after the fact — historical commission must stay exactly as earned.
    $store->update(['commission_rate' => 50]);

    expect((float) $transaction->fresh()->commission_amount)->toBe(10.0);
    expect((float) $transaction->fresh()->commission_rate_applied)->toBe(10.0);
});

it('does not compute commission for a pending or failed transaction', function () {
    $store = Store::factory()->create(['commission_rate' => 10]);
    $transaction = Transaction::factory()->create(['store_id' => $store->id, 'amount' => 100, 'status' => TransactionStatus::Pending]);

    expect($transaction->commission_amount)->toBeNull();

    $transaction->update(['status' => TransactionStatus::Failed]);
    expect($transaction->fresh()->commission_amount)->toBeNull();
});
