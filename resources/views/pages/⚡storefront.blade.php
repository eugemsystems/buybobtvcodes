<?php

use App\Actions\InitiateCheckout;
use App\Enums\TokenStatus;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Token;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Token Store')] #[Layout('layouts.public')] class extends Component
{
    public bool $showCheckout = false;
    public ?int $selectedCategoryId = null;
    public int $step = 1;

    #[Validate('required|email|max:254')]
    public string $customerEmail = '';

    #[Validate('required|string|max:20')]
    public string $customerPhone = '';

    public string $paymentUuid = '';
    public string $checkoutType = '';
    public string $qrUrl = '';
    public string $redirectUrl = '';
    public ?int $pendingTransactionId = null;
    public ?int $pendingTokenId = null;

    public bool $paymentSucceeded = false;
    public ?string $purchasedTokenCode = null;
    public ?int $purchasedTransactionId = null;
    public string $paymentError = '';
    public bool $pollingForItn = false;
    public int $pollAttempts = 0;
    private const MAX_POLL_ATTEMPTS = 20;

    #[Computed]
    public function categories(): Collection
    {
        return Category::withCount([
            'tokens as available_tokens_count' => fn ($q) => $q->where('status', TokenStatus::Available),
        ])->latest()->limit(6)->get();
    }

    #[Computed]
    public function selectedCategory(): ?Category
    {
        return $this->selectedCategoryId
            ? $this->categories->firstWhere('id', $this->selectedCategoryId)
            : null;
    }

    public function selectCategory(int $id): void
    {
        $this->selectedCategoryId = $id;
        $this->resetCheckout();
        $this->showCheckout = true;
    }

    public function goToPayment(): void
    {
        $this->validateOnly('customerEmail');
        $this->validateOnly('customerPhone');

        $category = $this->selectedCategory;

        if (! $category) {
            return;
        }

        $result = app(InitiateCheckout::class)->execute(
            category: $category,
            customerData: [
                'email' => $this->customerEmail,
                'phone' => $this->customerPhone,
            ],
        );

        if (! $result['success']) {
            $this->paymentError = $result['message'];
            $this->paymentSucceeded = false;
            $this->step = 3;

            return;
        }

        $this->checkoutType         = $result['checkout_type'];
        $this->pendingTransactionId = $result['transaction_id'];
        $this->pendingTokenId       = $result['token_id'];

        if ($result['checkout_type'] === 'onsite') {
            $this->paymentUuid = $result['data']['uuid'];
            $this->dispatch('payfast-uuid-ready', uuid: $result['data']['uuid']);
        } elseif ($result['checkout_type'] === 'qr') {
            $this->qrUrl = $result['data']['qr_url'];
            $this->pollingForItn = true;
            $this->pollAttempts  = 0;
        } elseif ($result['checkout_type'] === 'redirect') {
            $this->redirectUrl = $result['data']['redirect_url'];
        }

        $this->step = 2;
    }

    /** Called by Alpine when the PayFast overlay reports a successful payment. */
    public function finalizeOrder(): void
    {
        if (! $this->pendingTransactionId || ! $this->pendingTokenId) {
            return;
        }

        $transaction = Transaction::find($this->pendingTransactionId);
        $token       = Token::find($this->pendingTokenId);

        if (! $transaction || ! $token) {
            $this->paymentError     = 'Order not found. Please contact support.';
            $this->paymentSucceeded = false;
            $this->step             = 3;

            return;
        }

        if ($transaction->status === TransactionStatus::Completed) {
            $this->paymentSucceeded       = true;
            $this->purchasedTokenCode     = $token->token_code;
            $this->purchasedTransactionId = $transaction->id;
            $this->paymentError           = '';
            $this->step                   = 3;

            return;
        }

        // ITN not yet received — poll until gateway confirms via webhook.
        $this->pollingForItn = true;
        $this->pollAttempts  = 0;
        $this->step          = 3;
    }

    /** Polled every 2.5 s while waiting for a gateway webhook to confirm the transaction. */
    public function pollPaymentStatus(): void
    {
        if (! $this->pollingForItn) {
            return;
        }

        $this->pollAttempts++;

        $transaction = Transaction::find($this->pendingTransactionId);
        $token       = Token::find($this->pendingTokenId);

        if ($transaction && $transaction->status === TransactionStatus::Completed) {
            $this->paymentSucceeded       = true;
            $this->purchasedTokenCode     = $token?->token_code;
            $this->purchasedTransactionId = $transaction->id;
            $this->paymentError           = '';
            $this->pollingForItn          = false;

            return;
        }

        if ($transaction && $transaction->status === TransactionStatus::Failed) {
            $this->paymentError     = 'Your payment was not completed. Please try again.';
            $this->paymentSucceeded = false;
            $this->pollingForItn    = false;

            return;
        }

        if ($this->pollAttempts >= self::MAX_POLL_ATTEMPTS) {
            $this->paymentError     = 'Payment confirmation is taking longer than expected. Check your email or contact support with reference #'.$this->pendingTransactionId.'.';
            $this->paymentSucceeded = false;
            $this->pollingForItn    = false;
        }
    }

    /** Called by Alpine when the PayFast overlay is closed without payment. */
    public function paymentFailed(): void
    {
        DB::transaction(function (): void {
            if ($this->pendingTokenId) {
                Token::where('id', $this->pendingTokenId)
                    ->where('status', TokenStatus::Reserved)
                    ->update(['status' => TokenStatus::Available, 'transaction_id' => null]);
            }

            if ($this->pendingTransactionId) {
                Transaction::where('id', $this->pendingTransactionId)
                    ->where('status', TransactionStatus::Pending)
                    ->update(['status' => TransactionStatus::Failed]);
            }
        });

        $this->paymentError     = 'Payment was not completed. Please try again.';
        $this->paymentSucceeded = false;
        $this->reset(['paymentUuid', 'pendingTransactionId', 'pendingTokenId', 'qrUrl', 'redirectUrl', 'checkoutType']);
        $this->step = 3;
    }

    /** Back button in step 2 — releases the reservation and returns to step 1. */
    public function cancelPayment(): void
    {
        DB::transaction(function (): void {
            if ($this->pendingTokenId) {
                Token::where('id', $this->pendingTokenId)
                    ->where('status', TokenStatus::Reserved)
                    ->update(['status' => TokenStatus::Available, 'transaction_id' => null]);
            }

            if ($this->pendingTransactionId) {
                Transaction::where('id', $this->pendingTransactionId)
                    ->where('status', TransactionStatus::Pending)
                    ->update(['status' => TransactionStatus::Failed]);
            }
        });

        $this->reset(['paymentUuid', 'pendingTransactionId', 'pendingTokenId', 'qrUrl', 'redirectUrl', 'checkoutType', 'pollingForItn', 'pollAttempts']);
        $this->step = 1;
    }

    private function resetCheckout(): void
    {
        $this->reset([
            'step',
            'customerEmail',
            'customerPhone',
            'paymentSucceeded',
            'purchasedTokenCode',
            'purchasedTransactionId',
            'paymentError',
            'paymentUuid',
            'checkoutType',
            'qrUrl',
            'redirectUrl',
            'pendingTransactionId',
            'pendingTokenId',
            'pollingForItn',
            'pollAttempts',
        ]);

        $this->step = 1;
    }
}; ?>

<div style="font-family:'Manrope',sans-serif;">

    {{-- ── HERO ── --}}
    <section id="hero-section" style="position:relative;overflow:hidden;background:linear-gradient(180deg,#1a1a1a 0%,#111111 100%);padding:144px 0 108px;">
        {{-- Spider web canvas --}}
        <canvas id="hero-canvas" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"></canvas>
        {{-- Parallax depth layers --}}
        <div style="pointer-events:none;position:absolute;inset:0;overflow:hidden;">
            <div data-depth="0.04" style="position:absolute;top:-200px;left:50%;transform:translateX(-50%);width:900px;height:700px;background:radial-gradient(ellipse,rgba(221,242,71,0.07) 0%,transparent 70%);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="0.08" style="position:absolute;top:60px;right:-80px;width:500px;height:500px;background:radial-gradient(ellipse,rgba(221,242,71,0.06) 0%,transparent 70%);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="-0.06" style="position:absolute;bottom:40px;left:-80px;width:380px;height:380px;background:radial-gradient(ellipse,rgba(130,100,255,0.05) 0%,transparent 70%);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="0.14" style="position:absolute;top:22%;left:7%;width:10px;height:10px;border-radius:50%;background:rgba(221,242,71,0.45);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="-0.11" style="position:absolute;top:38%;right:10%;width:7px;height:7px;border-radius:50%;background:rgba(221,242,71,0.30);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="0.18" style="position:absolute;top:58%;left:5%;width:5px;height:5px;border-radius:50%;background:rgba(221,242,71,0.35);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="-0.09" style="position:absolute;top:18%;right:18%;width:90px;height:90px;border-radius:50%;border:1px solid rgba(221,242,71,0.10);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="0.07" style="position:absolute;top:65%;right:7%;width:56px;height:56px;border-radius:14px;border:1px solid rgba(221,242,71,0.08);transform:rotate(28deg);transition:transform 0.15s ease-out;will-change:transform;"></div>
            <div data-depth="-0.13" style="position:absolute;top:42%;left:14%;width:40px;height:40px;border-radius:10px;border:1px solid rgba(221,242,71,0.07);transform:rotate(-15deg);transition:transform 0.15s ease-out;will-change:transform;"></div>
        </div>

        <div style="position:relative;max-width:72rem;margin:0 auto;padding:0 24px;text-align:center;">
            <div class="mb-8 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-semibold" style="border-color:rgba(221,242,71,0.25);background:rgba(221,242,71,0.07);color:#DDF247;">
                <span class="size-2 animate-pulse rounded-full" style="background:#DDF247;"></span>
                Instant Digital Delivery
            </div>

            <h1 class="mb-7 text-5xl font-extrabold leading-tight tracking-tight text-white sm:text-6xl lg:text-7xl" style="font-family:'Manrope',sans-serif;">
                All Your Digital Tokens,
                <span class="gradient-text block">One Trusted Store</span>
            </h1>

            <p class="mx-auto mb-12 max-w-2xl leading-relaxed" style="color:rgba(255,255,255,0.55);font-family:'Azeret Mono',monospace;font-size:15px;line-height:27px;">
                Delivered to your inbox in seconds. No account needed.
            </p>

            <div style="padding-top: 40px;padding-bottom: 35px;" class="flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('shop') }}" wire:navigate class="btn-primary text-base">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3L4 14h7l-2 7 9-11h-7l2-7z"/></svg>
                    Shop All Tokens
                </a>
                <a href="#about" class="btn-ghost text-base">About Us</a>
            </div>

            <div class="mt-16 flex flex-wrap items-center justify-center gap-8" style="color:rgba(255,255,255,0.38);font-size:13px;font-family:'Azeret Mono',monospace;">
                <span class="flex items-center gap-2"><svg class="size-4" style="color:#4ade80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Secure payment</span>
                <span class="flex items-center gap-2"><svg class="size-4" style="color:#DDF247" fill="currentColor" viewBox="0 0 24 24"><path d="M13 3L4 14h7l-2 7 9-11h-7l2-7z"/></svg>Delivered in seconds</span>
                <span class="flex items-center gap-2"><svg class="size-4" style="color:#60a5fa" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>Email confirmation</span>
                <span class="flex items-center gap-2"><svg class="size-4" style="color:#a78bfa" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>Works on any device</span>
            </div>
        </div>
    </section>

    {{-- ── LIVE STORE ── --}}
    <section id="store" class="sec" style="background:#161616;">
        <div class="sec-inner">
            <div class="sec-head">
                <div class="sec-badge" style="border-color:rgba(34,197,94,0.3);color:#4ade80;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#4ade80;display:inline-block;animation:pulse 2s infinite;"></span>
                    Live Stock
                </div>
                <h2 class="sec-h2">Available Tokens Right Now</h2>
                <p class="sec-sub">In-stock and ready to deliver — buy in seconds, receive instantly</p>
            </div>

            @if ($this->categories->isEmpty())
                <div style="border-radius:24px;border:1px solid rgba(255,255,255,0.07);background:#1e1e1e;padding:80px 24px;text-align:center;">
                    <p style="color:rgba(255,255,255,0.30);font-size:14px;font-family:'Azeret Mono',monospace;">More tokens coming soon — check back shortly.</p>
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->categories as $category)
                        @php $isAvailable = $category->available_tokens_count > 0; @endphp
                        <div
                            class="token-card"
                            style="background:#1e1e1e;border-radius:24px;border:1px solid rgba(255,255,255,0.07);padding:32px;display:flex;flex-direction:column;gap:22px;"
                        >
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                <div>
                                    <h3 style="font-size:21px;font-weight:800;color:#fff;margin-bottom:6px;font-family:'Manrope',sans-serif;">{{ $category->name }}</h3>
                                    @if ($category->description)
                                        <p style="font-size:13px;color:rgba(255,255,255,0.45);font-family:'Azeret Mono',monospace;line-height:20px;">{{ $category->description }}</p>
                                    @endif
                                </div>
                                <span style="flex-shrink:0;font-size:11px;font-weight:700;padding:5px 11px;border-radius:999px;font-family:'Manrope',sans-serif;{{ $isAvailable ? 'background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.25);' : 'background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.25);' }}">
                                    {{ $isAvailable ? $category->available_tokens_count.' in stock' : 'Sold out' }}
                                </span>
                            </div>

                            <div style="border-top:1px solid rgba(255,255,255,0.07);padding-top:22px;display:flex;align-items:center;justify-content:space-between;">
                                <span style="font-size:30px;font-weight:800;color:#fff;font-family:'Manrope',sans-serif;">R{{ fmt_price($category->price) }}</span>
                                @if ($isAvailable)
                                    <a href="{{ route('shop', ['add' => $category->id]) }}" wire:navigate class="btn-primary" style="padding:12px 24px;font-size:14px;">Buy Now</a>
                                @else
                                    <button disabled style="display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.3);border-radius:12px;padding:12px 24px;font-size:14px;font-weight:700;font-family:'Manrope',sans-serif;border:none;">Sold Out</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ── HOW IT WORKS ── --}}
    <section id="how-it-works" class="sec" style="background:#111111;">
        <div class="sec-inner">
            <div class="sec-head">
                <div class="sec-badge" style="border-color:rgba(221,242,71,0.2);color:#DDF247;">Simple Process</div>
                <h2 class="sec-h2">How It Works</h2>
                <p class="sec-sub">Three steps · No account · No waiting</p>
            </div>

            <div id="hiw-grid">
                @foreach ([
                    ['num' => '01', 'emoji' => '🛍️', 'title' => 'Pick Your Token', 'desc' => 'Browse our live catalog and choose the gaming, streaming, or shopping token you need.', 'bar' => 'step-bar-1'],
                    ['num' => '02', 'emoji' => '💳', 'title' => 'Pay Securely',     'desc' => 'Enter your email and complete payment via our encrypted, PCI-DSS compliant checkout.', 'bar' => 'step-bar-3'],
                    ['num' => '03', 'emoji' => '⚡', 'title' => 'Receive Instantly','desc' => 'Your unique token code appears on screen and lands in your inbox within seconds.',     'bar' => 'step-bar-2'],
                ] as $i => $step)
                    @if ($i > 0)
                        <div class="hiw-arrow">
                            <div style="display:flex;align-items:center;">
                                <div style="width:32px;height:1px;background:linear-gradient(90deg,rgba(221,242,71,0.15),rgba(221,242,71,0.60));"></div>
                                <div style="width:0;height:0;border-top:5px solid transparent;border-bottom:5px solid transparent;border-left:8px solid rgba(221,242,71,0.60);"></div>
                            </div>
                        </div>
                    @endif
                    <div
                        class="relative overflow-hidden rounded-3xl text-center"
                        style="background:#1a1a1a;border:1px solid rgba(255,255,255,0.07);transition:all 0.3s ease;display:flex;flex-direction:column;"
                        onmouseenter="this.querySelector('.step-ico').style.transform='scale(1.15) rotate(-5deg)';this.style.borderColor='rgba(221,242,71,0.25)';this.style.transform='translateY(-7px)';this.style.boxShadow='0 24px 60px rgba(0,0,0,0.5)';"
                        onmouseleave="this.querySelector('.step-ico').style.transform='';this.style.borderColor='rgba(255,255,255,0.07)';this.style.transform='';this.style.boxShadow='';"
                    >
                        <div style="padding:36px 28px 28px;flex:1;">
                            <div style="font-size:11px;font-weight:900;letter-spacing:4px;text-transform:uppercase;color:rgba(221,242,71,0.45);margin-bottom:18px;font-family:'Azeret Mono',monospace;">Step {{ $step['num'] }}</div>
                            <div class="step-ico" style="width:80px;height:80px;border-radius:22px;background:#232323;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:1px solid rgba(255,255,255,0.08);font-size:36px;transition:transform 0.4s ease;">{{ $step['emoji'] }}</div>
                            <h4 style="font-size:20px;font-weight:800;color:#fff;margin-bottom:12px;font-family:'Manrope',sans-serif;">{{ $step['title'] }}</h4>
                            <p style="font-size:13px;line-height:23px;color:rgba(255,255,255,0.50);font-family:'Azeret Mono',monospace;">{{ $step['desc'] }}</p>
                        </div>
                        <div class="{{ $step['bar'] }}" style="height:5px;width:100%;"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── CHECKOUT FLYOUT ── --}}
    <flux:modal
        wire:model="showCheckout"
        flyout
        position="right"
        :dismissible="$step !== 2"
        class="md:w-[28rem]"
    >
        <div class="flex h-full flex-col gap-6 p-6">

            {{-- Header --}}
            <div>
                <flux:heading size="lg">
                    @if ($step === 1) Your Details
                    @elseif ($step === 2) Review & Pay
                    @else Order {{ $paymentSucceeded ? 'Confirmed' : 'Status' }}
                    @endif
                </flux:heading>

                @if ($this->selectedCategory && $step < 3)
                    <flux:text class="mt-1">
                        {{ $this->selectedCategory->name }} —
                        <strong>R{{ fmt_price($this->selectedCategory->price) }}</strong>
                    </flux:text>
                @endif

                @if ($step < 3)
                    <div class="mt-4 flex items-center gap-2">
                        <div @class(['h-1.5 flex-1 rounded-full', 'bg-violet-500' => $step >= 1, 'bg-zinc-700' => $step < 1])></div>
                        <div @class(['h-1.5 flex-1 rounded-full transition-all', 'bg-violet-500' => $step >= 2, 'bg-zinc-700' => $step < 2])></div>
                    </div>
                @endif
            </div>

            {{-- Step 1: Customer details --}}
            @if ($step === 1)
                <form wire:submit="goToPayment" class="flex flex-1 flex-col gap-5">
                    <flux:input
                        wire:model="customerEmail"
                        label="Email Address"
                        type="email"
                        placeholder="you@example.com"
                        description="Your token will be sent to this address."
                        icon="envelope"
                        required
                        autofocus
                    />

                    <flux:input
                        wire:model="customerPhone"
                        label="Phone Number"
                        type="tel"
                        placeholder="072 000 0000"
                        icon="phone"
                        required
                    />

                    <div class="mt-auto">
                        <flux:button
                            type="submit"
                            variant="primary"
                            class="w-full bg-violet-600 hover:bg-violet-500"
                        >
                            <span wire:loading.remove wire:target="goToPayment" class="flex items-center gap-2">
                                Continue to Payment
                                <flux:icon.arrow-right class="size-4" />
                            </span>
                            <span wire:loading wire:target="goToPayment" class="flex items-center gap-2">
                                <flux:icon.loading class="size-4 animate-spin" />
                                Preparing your order…
                            </span>
                        </flux:button>
                    </div>
                </form>
            @endif

            {{-- Step 2: Review & pay --}}
            @if ($step === 2)
                <div class="flex flex-1 flex-col gap-5">

                    {{-- Order summary --}}
                    <div class="space-y-3 rounded-xl border border-zinc-700 bg-zinc-800/60 p-5">
                        <div class="flex items-center justify-between text-sm">
                            <flux:text>Product</flux:text>
                            <span class="font-semibold text-zinc-100">{{ $this->selectedCategory?->name }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <flux:text>Email</flux:text>
                            <span class="max-w-[180px] truncate text-sm font-medium text-zinc-100">{{ $customerEmail }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-zinc-700 pt-3">
                            <flux:heading size="sm">Total</flux:heading>
                            <span class="text-xl font-bold text-violet-400">
                                R{{ fmt_price($this->selectedCategory?->price ?? 0) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 rounded-lg border border-green-800 bg-green-950/40 px-4 py-3 text-sm text-green-300">
                        <flux:icon.lock-closed class="size-4 shrink-0" />
                        Your payment is processed securely and encrypted end-to-end.
                    </div>

                    {{-- PayFast onsite overlay --}}
                    @if ($checkoutType === 'onsite')
                        <div
                            x-data="{ processing: false, pfUuid: '' }"
                            x-init="pfUuid = $wire.paymentUuid || ''"
                            x-on:payfast-uuid-ready.window="pfUuid = $event.detail.uuid"
                            class="mt-auto space-y-3"
                        >
                            <flux:button
                                x-bind:disabled="processing || !pfUuid"
                                x-on:click="processing = true; window.payfast_do_onsite_payment({ uuid: pfUuid }, (result) => { processing = false; result === true ? $wire.finalizeOrder() : $wire.paymentFailed(); });"
                                variant="primary"
                                class="w-full bg-violet-600 hover:bg-violet-500 whitespace-nowrap"
                            >
                                <span x-show="! processing" class="flex items-center justify-center gap-2 whitespace-nowrap">
                                    <flux:icon.lock-closed class="size-4 shrink-0" />
                                    Pay Securely — R{{ fmt_price($this->selectedCategory?->price ?? 0) }}
                                </span>
                                <span x-show="processing" class="flex items-center justify-center gap-2 whitespace-nowrap">
                                    <flux:icon.loading class="size-4 animate-spin shrink-0" />
                                    Opening secure payment…
                                </span>
                            </flux:button>

                            <flux:button
                                wire:click="cancelPayment"
                                variant="ghost"
                                class="w-full"
                                x-bind:disabled="processing"
                            >
                                Back
                            </flux:button>

                            <img src="/payments.png" alt="Accepted payment methods" class="mx-auto mt-2 w-full max-w-xs opacity-90" />
                        </div>
                    @endif

                    {{-- SnapScan QR code --}}
                    @if ($checkoutType === 'qr')
                        <div
                            class="mt-auto space-y-4"
                            wire:poll.2500ms="pollPaymentStatus"
                        >
                            <div class="flex flex-col items-center gap-3 rounded-xl border border-zinc-700 bg-zinc-800/60 p-5">
                                <flux:text class="text-sm text-zinc-400">Scan this QR code with your banking app to pay</flux:text>
                                <img
                                    src="{{ $qrUrl }}"
                                    alt="SnapScan QR code"
                                    class="size-48 rounded-lg"
                                />
                                <flux:text class="text-xs text-zinc-500">
                                    Once scanned, this page will update automatically.
                                </flux:text>
                            </div>

                            <flux:button
                                wire:click="cancelPayment"
                                variant="ghost"
                                class="w-full"
                            >
                                Cancel
                            </flux:button>
                        </div>
                    @endif

                    {{-- DPO redirect --}}
                    @if ($checkoutType === 'redirect')
                        <div class="mt-auto space-y-3">
                            <a href="{{ $redirectUrl }}" class="block w-full">
                                <flux:button
                                    variant="primary"
                                    class="w-full bg-violet-600 hover:bg-violet-500"
                                >
                                    <flux:icon.arrow-top-right-on-square class="size-4" />
                                    Proceed to Secure Checkout
                                </flux:button>
                            </a>

                            <flux:button
                                wire:click="cancelPayment"
                                variant="ghost"
                                class="w-full"
                            >
                                Back
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Step 3: Result --}}
            @if ($step === 3)
                <div
                    class="flex flex-1 flex-col items-center justify-center gap-6 text-center"
                    @if ($pollingForItn) wire:poll.2500ms="pollPaymentStatus" @endif
                >
                    @if ($pollingForItn)
                        <div class="flex size-20 items-center justify-center rounded-full bg-violet-500/10">
                            <flux:icon.loading class="size-10 animate-spin text-violet-400" />
                        </div>
                        <div>
                            <flux:heading size="lg">Confirming Payment…</flux:heading>
                            <flux:text class="mt-1 text-zinc-400">
                                Please wait while we confirm your payment.
                            </flux:text>
                        </div>
                    @elseif ($paymentSucceeded)
                        <div class="flex size-20 items-center justify-center rounded-full bg-green-500/10">
                            <flux:icon.check-circle class="size-10 text-green-500" />
                        </div>

                        <div>
                            <flux:heading size="lg">Payment Successful!</flux:heading>
                            <flux:text class="mt-1">
                                Your token has been delivered to
                                <strong>{{ $customerEmail }}</strong>.
                            </flux:text>
                        </div>

                        <div class="w-full rounded-xl border border-violet-500/40 bg-violet-500/10 px-6 py-5">
                            <flux:text class="mb-2 text-xs uppercase tracking-widest text-zinc-400">Your Token</flux:text>
                            <p class="font-mono text-2xl font-bold tracking-widest text-violet-300">
                                {{ $purchasedTokenCode }}
                            </p>
                        </div>

                        <flux:text class="text-sm text-zinc-400">
                            Screenshot or copy your token. A confirmation email is on its way.
                        </flux:text>
                    @else
                        <div class="flex size-20 items-center justify-center rounded-full bg-red-500/10">
                            <flux:icon.x-circle class="size-10 text-red-500" />
                        </div>

                        <div>
                            <flux:heading size="lg">Payment Failed</flux:heading>
                            <flux:text class="mt-2">{{ $paymentError }}</flux:text>
                        </div>

                        <flux:button
                            wire:click="$set('step', 1)"
                            variant="primary"
                            class="w-full bg-violet-600 hover:bg-violet-500"
                        >
                            Try Again
                        </flux:button>
                    @endif

                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full">Close</flux:button>
                    </flux:modal.close>
                </div>
            @endif

        </div>
    </flux:modal>

</div>
