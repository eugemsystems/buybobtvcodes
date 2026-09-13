<?php

use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Store;
use App\Models\Token;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Transactions ─────────────────────────────────────────────────────────────

it('masks customer email and phone on the reseller transactions page', function () {
    $store = Store::factory()->create();
    $reseller = User::factory()->reseller($store)->create();

    Transaction::factory()->completed()->create([
        'store_id' => $store->id,
        'customer_email' => 'chisangolaird@gmail.com',
        'customer_phone' => '0821234567',
    ]);

    Livewire::actingAs($reseller)
        ->test('pages::reseller.transactions')
        ->assertSee('ch***@gmail.com')
        ->assertSee('*******567')
        ->assertDontSee('chisangolaird@gmail.com')
        ->assertDontSee('0821234567');
});

it('does not mask customer email and phone on the admin transactions page', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();

    Transaction::factory()->completed()->create([
        'store_id' => $store->id,
        'customer_email' => 'chisangolaird@gmail.com',
        'customer_phone' => '0821234567',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.transactions')
        ->assertSee('chisangolaird@gmail.com')
        ->assertSee('0821234567');
});

it('masks the customer email in the reseller reprocess confirmation modal', function () {
    $store = Store::factory()->create();
    $reseller = User::factory()->reseller($store)->create();

    $transaction = Transaction::factory()->create([
        'store_id' => $store->id,
        'status' => TransactionStatus::Pending,
        'customer_email' => 'chisangolaird@gmail.com',
    ]);

    Livewire::actingAs($reseller)
        ->test('pages::reseller.transactions')
        ->call('confirmReprocess', $transaction->id)
        ->assertSee('ch***@gmail.com')
        ->assertDontSee('chisangolaird@gmail.com');
});

// ── Dashboard & wallet ───────────────────────────────────────────────────────

it('masks customer email in the reseller dashboard recent transactions widget', function () {
    $store = Store::factory()->create();
    $reseller = User::factory()->reseller($store)->create();

    Transaction::factory()->completed()->create([
        'store_id' => $store->id,
        'customer_email' => 'chisangolaird@gmail.com',
    ]);

    Livewire::actingAs($reseller)
        ->test('pages::reseller.dashboard')
        ->assertSee('ch***@gmail.com')
        ->assertDontSee('chisangolaird@gmail.com');
});

it('masks customer email in the reseller wallet commission list', function () {
    $store = Store::factory()->create(['commission_rate' => 10]);
    $reseller = User::factory()->reseller($store)->create();

    $transaction = Transaction::factory()->create([
        'store_id' => $store->id,
        'status' => TransactionStatus::Pending,
        'customer_email' => 'chisangolaird@gmail.com',
        'amount' => 100,
    ]);
    $transaction->update(['status' => TransactionStatus::Completed]);

    Livewire::actingAs($reseller)
        ->test('pages::reseller.wallet')
        ->assertSee('ch***@gmail.com')
        ->assertDontSee('chisangolaird@gmail.com');
});

// ── Tokens ───────────────────────────────────────────────────────────────────

it('never renders the full token code, or a reveal control, on the reseller tokens page', function () {
    $store = Store::factory()->create();
    $reseller = User::factory()->reseller($store)->create();
    $category = Category::factory()->create(['store_id' => $store->id]);
    Token::factory()->for($category)->create(['token_code' => 'ZQXK-9931-LPWM-4420']);

    Livewire::actingAs($reseller)
        ->test('pages::reseller.tokens')
        ->assertDontSee('ZQXK-9931-LPWM-4420')
        ->assertSee('ZQXK')
        ->assertSee('4420')
        ->assertDontSeeHtml('x-data="{ revealed: false }"');
});

it('still lets the admin reveal the full token code on the admin tokens page', function () {
    $admin = User::factory()->create();
    $category = Category::factory()->create();
    Token::factory()->for($category)->create(['token_code' => 'ZQXK-9931-LPWM-4420']);

    Livewire::actingAs($admin)
        ->test('pages::admin.tokens')
        ->assertSee('ZQXK-9931-LPWM-4420');
});

// ── Bulk import category select ──────────────────────────────────────────────

it('renders a blank, selectable default option in the bulk import category select so the first real choice registers', function () {
    $admin = User::factory()->create();
    Category::factory()->create(['name' => 'Netflix']);

    Livewire::actingAs($admin)
        ->test('pages::admin.tokens')
        ->call('openImport')
        ->assertSee('Select a category…')
        ->assertSee('Netflix')
        ->assertDontSeeHtml('class="placeholder">Select a category');
});
