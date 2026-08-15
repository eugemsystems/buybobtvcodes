<?php

use App\Enums\TokenStatus;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\Payout;
use App\Models\Store;
use App\Models\Token;
use App\Models\Transaction;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Store Report')] class extends Component
{
    #[Locked]
    public int $storeId;

    public bool $showPayoutModal = false;

    #[Validate('required|numeric|min:0.01')]
    public string $payoutAmount = '';

    #[Validate('nullable|string|max:1000')]
    public string $payoutNote = '';

    public function mount(Store $store): void
    {
        $this->storeId = $store->id;
    }

    #[Computed]
    public function store(): Store
    {
        return Store::with('owner')->findOrFail($this->storeId);
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return (float) Transaction::forStore($this->storeId)
            ->where('status', TransactionStatus::Completed)
            ->sum('amount');
    }

    #[Computed]
    public function thisMonthRevenue(): float
    {
        return (float) Transaction::forStore($this->storeId)
            ->where('status', TransactionStatus::Completed)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
    }

    #[Computed]
    public function pendingTransactions(): int
    {
        return Transaction::forStore($this->storeId)->where('status', TransactionStatus::Pending)->count();
    }

    #[Computed]
    public function tokensSold(): int
    {
        return Token::withoutGlobalScopes()
            ->whereIn('category_id', Category::withoutGlobalScopes()->where('store_id', $this->storeId)->select('id'))
            ->where('status', TokenStatus::Sold)
            ->count();
    }

    #[Computed]
    public function availableInventory(): int
    {
        return Token::withoutGlobalScopes()
            ->whereIn('category_id', Category::withoutGlobalScopes()->where('store_id', $this->storeId)->select('id'))
            ->where('status', TokenStatus::Available)
            ->count();
    }

    #[Computed]
    public function categoryCount(): int
    {
        return Category::withoutGlobalScopes()->where('store_id', $this->storeId)->count();
    }

    #[Computed]
    public function recentTransactions(): Collection
    {
        return Transaction::forStore($this->storeId)
            ->with('token.category')
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function commissionEarned(): float
    {
        return (float) Transaction::forStore($this->storeId)
            ->where('status', TransactionStatus::Completed)
            ->sum('commission_amount');
    }

    #[Computed]
    public function totalPaidOut(): float
    {
        return (float) Payout::where('store_id', $this->storeId)->sum('amount');
    }

    #[Computed]
    public function walletBalance(): float
    {
        return $this->commissionEarned - $this->totalPaidOut;
    }

    #[Computed]
    public function payouts(): Collection
    {
        return Payout::where('store_id', $this->storeId)
            ->with('paidBy')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function openPayoutModal(): void
    {
        $this->reset(['payoutAmount', 'payoutNote']);
        $this->resetValidation();
        $this->showPayoutModal = true;
    }

    public function recordPayout(): void
    {
        $this->validate();

        Payout::create([
            'store_id' => $this->storeId,
            'amount' => $this->payoutAmount,
            'note' => $this->payoutNote ?: null,
            'paid_by_id' => auth()->id(),
        ]);

        $this->showPayoutModal = false;
        unset($this->totalPaidOut, $this->walletBalance, $this->payouts);
        Flux::toast(variant: 'success', text: 'Payout recorded.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:button :href="route('admin.stores')" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
                Stores
            </flux:button>
            <flux:heading size="xl" class="mt-2">{{ $this->store->name }}</flux:heading>
            <flux:text class="text-zinc-500">
                {{ $this->store->slug }}.{{ config('tenancy.base_domain') }}
                @if ($this->store->owner)
                    &middot; owned by {{ $this->store->owner->name }} ({{ $this->store->owner->email }})
                @endif
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$this->store->is_active ? 'green' : 'zinc'">
                {{ $this->store->is_active ? 'Active' : 'Disabled' }}
            </flux:badge>
            <flux:button :href="route('admin.stores.categories', $this->storeId)" variant="ghost" size="sm" icon="tag" wire:navigate>
                Categories
            </flux:button>
            <flux:button :href="route('admin.stores.tokens', $this->storeId)" variant="ghost" size="sm" icon="key" wire:navigate>
                Tokens
            </flux:button>
            <flux:button wire:click="openPayoutModal" variant="primary" size="sm" icon="banknotes">
                Record Payout
            </flux:button>
        </div>
    </div>

    {{-- ── Wallet ── --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Commission Earned</flux:text>
            <p class="text-2xl font-bold">R{{ fmt_price($this->commissionEarned) }}</p>
            <flux:text class="text-xs text-zinc-500">
                {{ $this->store->commission_rate !== null ? rtrim(rtrim(number_format((float) $this->store->commission_rate, 2), '0'), '.').'% rate (override)' : $this->store->effectiveCommissionRate().'% rate (default)' }}
            </flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Total Paid Out</flux:text>
            <p class="text-2xl font-bold">R{{ fmt_price($this->totalPaidOut) }}</p>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Balance Remaining</flux:text>
            <p class="text-2xl font-bold {{ $this->walletBalance < 0 ? 'text-red-500' : '' }}">
                R{{ fmt_price($this->walletBalance) }}
            </p>
            @if ($this->walletBalance < 0)
                <flux:text class="text-xs text-red-500">Paid out more than earned</flux:text>
            @endif
        </flux:card>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Total Revenue</flux:text>
            <p class="text-2xl font-bold">R{{ fmt_price($this->totalRevenue) }}</p>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">This Month</flux:text>
            <p class="text-2xl font-bold">R{{ fmt_price($this->thisMonthRevenue) }}</p>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Pending Transactions</flux:text>
            <p class="text-2xl font-bold {{ $this->pendingTransactions > 0 ? 'text-yellow-500' : '' }}">
                {{ $this->pendingTransactions }}
            </p>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Categories</flux:text>
            <p class="text-2xl font-bold">{{ $this->categoryCount }}</p>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Tokens Sold</flux:text>
            <p class="text-2xl font-bold">{{ number_format($this->tokensSold) }}</p>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Available Stock</flux:text>
            <p class="text-2xl font-bold">{{ number_format($this->availableInventory) }}</p>
        </flux:card>
    </div>

    <flux:card class="mt-6 p-5">
        <flux:heading class="mb-4">Recent Transactions</flux:heading>

        @if ($this->recentTransactions->isEmpty())
            <flux:text class="text-sm text-zinc-500">No transactions yet.</flux:text>
        @else
            <div class="space-y-3">
                @foreach ($this->recentTransactions as $tx)
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $tx->customer_email }}</p>
                            <p class="text-xs text-zinc-500">{{ $tx->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            @if ($tx->commission_amount !== null)
                                <span class="text-xs text-zinc-500">+R{{ fmt_price($tx->commission_amount) }} commission</span>
                            @endif
                            <span class="text-sm font-medium">R{{ fmt_price($tx->amount) }}</span>
                            <flux:badge
                                size="sm"
                                :color="match($tx->status->value) {
                                    'completed' => 'green',
                                    'failed' => 'red',
                                    default => 'yellow',
                                }"
                            >
                                {{ $tx->status->value }}
                            </flux:badge>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- ── Payouts ── --}}
    <flux:card class="mt-6 p-5">
        <flux:heading class="mb-4">Payouts</flux:heading>

        @if ($this->payouts->isEmpty())
            <flux:text class="text-sm text-zinc-500">No payouts recorded yet.</flux:text>
        @else
            <div class="space-y-3">
                @foreach ($this->payouts as $payout)
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ $payout->note ?: 'Payout' }}
                            </p>
                            <p class="text-xs text-zinc-500">
                                {{ $payout->created_at->format('d M Y') }}
                                @if ($payout->paidBy)
                                    &middot; recorded by {{ $payout->paidBy->name }}
                                @endif
                            </p>
                        </div>
                        <span class="shrink-0 text-sm font-medium">R{{ fmt_price($payout->amount) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- ── Record Payout Modal ── --}}
    <flux:modal wire:model="showPayoutModal" class="md:w-96">
        <flux:heading size="lg">Record Payout</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            Logs that you paid this reseller — no money is transferred through the app.
        </flux:text>

        <form wire:submit="recordPayout" class="mt-6 space-y-5">
            <flux:input
                wire:model="payoutAmount"
                label="Amount (R)"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="500.00"
                required
                autofocus
            />

            <flux:textarea
                wire:model="payoutNote"
                label="Note"
                placeholder="e.g. Bank transfer ref #12345"
                rows="3"
            />

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Record Payout</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
