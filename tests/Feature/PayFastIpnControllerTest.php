<?php

use App\Enums\TokenStatus;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Store;
use App\Models\Token;
use App\Models\Transaction;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes the webhook correctly regardless of CurrentStore binding, since Transaction is never globally scoped', function () {
    $ownStore = Store::factory()->create();
    $unrelatedStore = Store::factory()->create();

    $category = Category::factory()->create(['store_id' => $ownStore->id]);
    $transaction = Transaction::factory()->create([
        'store_id' => $ownStore->id,
        'status' => TransactionStatus::Pending,
        'amount' => 99.00,
    ]);
    $token = Token::factory()->for($category)->create([
        'status' => TokenStatus::Reserved,
        'transaction_id' => $transaction->id,
    ]);

    // Simulate worst-case leakage: some other store happens to be bound when
    // the webhook fires. The lookup must still work — it's keyed by raw ID.
    app(CurrentStore::class)->set($unrelatedStore);

    $passphrase = config('payfast.passphrase', '');
    $data = [
        'merchant_id' => '10004002',
        'm_payment_id' => (string) $transaction->id,
        'pf_payment_id' => 'pf-regression-001',
        'payment_status' => 'COMPLETE',
        'amount_gross' => '99.00',
    ];

    ksort($data);
    $qs = collect($data)->map(fn ($v, $k) => $k.'='.urlencode((string) $v))->implode('&');
    if ($passphrase !== '') {
        $qs .= '&passphrase='.urlencode($passphrase);
    }
    $data['signature'] = md5($qs);

    $this->post(route('payfast.notify'), $data)->assertStatus(200);

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
    expect($token->fresh()->status)->toBe(TokenStatus::Sold);
});
