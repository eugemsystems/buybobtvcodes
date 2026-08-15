<?php

use App\Enums\TransactionStatus;
use App\Models\Payout;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Wallet')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function storeId(): int
    {
        return auth()->user()->store_id;
    }

    #[Computed]
    public function store(): Store
    {
        return Store::findOrFail($this->storeId);
    }

    #[Computed]
    public function commissionEarned(): float
    {
        return (float) Transaction::forStore($this->storeId)
            ->where('status', TransactionStatus::Completed)
            ->sum('commission_amount');
    }

    #[Computed]
    public function totalPaid(): float
    {
        return (float) Payout::where('store_id', $this->storeId)->sum('amount');
    }

    #[Computed]
    public function walletBalance(): float
    {
        return $this->commissionEarned - $this->totalPaid;
    }

    #[Computed]
    public function payouts(): LengthAwarePaginator
    {
        return Payout::where('store_id', $this->storeId)->latest()->paginate(15);
    }

    #[Computed]
    public function commissionEntries(): LengthAwarePaginator
    {
        return Transaction::forStore($this->storeId)
            ->where('status', TransactionStatus::Completed)
            ->where('commission_amount', '>', 0)
            ->latest()
            ->paginate(15, pageName: 'salesPage');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl">Wallet</flux:heading>
        <flux:text class="text-zinc-500">
            {{ $this->store->commission_rate !== null ? rtrim(rtrim(number_format((float) $this->store->commission_rate, 2), '0'), '.').'% commission rate' : $this->store->effectiveCommissionRate().'% commission rate (platform default)' }}
        </flux:text>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Commission Earned</flux:text>
            <p class="text-2xl font-bold">R{{ fmt_price($this->commissionEarned) }}</p>
            <flux:text class="text-xs text-zinc-500">All time</flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Total Paid</flux:text>
            <p class="text-2xl font-bold">R{{ fmt_price($this->totalPaid) }}</p>
            <flux:text class="text-xs text-zinc-500">All time</flux:text>
        </flux:card>

        <flux:card class="space-y-1 p-5">
            <flux:text class="text-sm text-zinc-500">Balance Remaining</flux:text>
            <p class="text-2xl font-bold {{ $this->walletBalance < 0 ? 'text-red-500' : 'text-green-500' }}">
                R{{ fmt_price($this->walletBalance) }}
            </p>
        </flux:card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- ── Payouts Received ── --}}
        <flux:card class="p-5">
            <flux:heading class="mb-4">Payouts Received</flux:heading>

            @if ($this->payouts->isEmpty())
                <flux:text class="text-sm text-zinc-500">No payouts recorded yet.</flux:text>
            @else
                <div class="space-y-3">
                    @foreach ($this->payouts as $payout)
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $payout->note ?: 'Payout' }}</p>
                                <p class="text-xs text-zinc-500">{{ $payout->created_at->format('d M Y') }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-medium">R{{ fmt_price($payout->amount) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $this->payouts->links() }}
                </div>
            @endif
        </flux:card>

        {{-- ── Commission by Sale ── --}}
        <flux:card class="p-5">
            <flux:heading class="mb-4">Commission by Sale</flux:heading>

            @if ($this->commissionEntries->isEmpty())
                <flux:text class="text-sm text-zinc-500">No commission earned yet.</flux:text>
            @else
                <div class="space-y-3">
                    @foreach ($this->commissionEntries as $tx)
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $tx->customer_email }}</p>
                                <p class="text-xs text-zinc-500">{{ $tx->created_at->format('d M Y') }} &middot; sale R{{ fmt_price($tx->amount) }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-medium text-green-500">+R{{ fmt_price($tx->commission_amount) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    {{ $this->commissionEntries->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
