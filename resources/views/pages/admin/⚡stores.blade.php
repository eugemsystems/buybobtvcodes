<?php

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Stores')] class extends Component
{
    public bool $showCreateModal = false;

    public string $newStoreName = '';
    public string $newSlug = '';
    public string $newOwnerName = '';
    public string $newOwnerEmail = '';
    public string $newOwnerPassword = '';
    public string $newOwnerPasswordConfirmation = '';
    public string $newCommissionRate = '';

    public bool $showEditModal = false;
    public ?int $editingId = null;
    public string $editName = '';
    public string $editCommissionRate = '';
    public string $editOwnerEmail = '';
    public string $editOwnerPassword = '';
    public string $editOwnerPasswordConfirmation = '';

    public function updatedNewStoreName(string $value): void
    {
        $this->newSlug = Str::slug($value);
    }

    /** @return Collection<int, Store> */
    #[Computed]
    public function stores(): Collection
    {
        return Store::with('owner')
            ->withCount('categories')
            ->withSum(['transactions as revenue' => fn ($q) => $q->where('status', TransactionStatus::Completed)], 'amount')
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->reset([
            'newStoreName', 'newSlug', 'newOwnerName', 'newOwnerEmail',
            'newOwnerPassword', 'newOwnerPasswordConfirmation', 'newCommissionRate',
        ]);
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function create(): void
    {
        $this->validate([
            'newStoreName' => 'required|string|max:255',
            'newSlug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:stores,slug', Rule::notIn(['www', 'admin', 'api', 'app', 'reseller', 'default'])],
            'newOwnerName' => 'required|string|max:255',
            'newOwnerEmail' => 'required|email|max:255|unique:users,email',
            'newOwnerPassword' => 'required|string|min:8|same:newOwnerPasswordConfirmation',
            'newCommissionRate' => 'nullable|numeric|min:0|max:100',
        ]);

        $store = Store::create([
            'name' => $this->newStoreName,
            'slug' => $this->newSlug,
            'commission_rate' => $this->newCommissionRate !== '' ? $this->newCommissionRate : null,
        ]);

        $owner = User::create([
            'name' => $this->newOwnerName,
            'email' => $this->newOwnerEmail,
            'password' => $this->newOwnerPassword,
        ]);
        $owner->forceFill([
            'role' => UserRole::Reseller,
            'store_id' => $store->id,
        ])->save();

        $store->forceFill(['owner_id' => $owner->id])->save();

        $this->showCreateModal = false;
        unset($this->stores);
        Flux::toast(variant: 'success', text: 'Store created.');
    }

    public function toggleActive(int $storeId): void
    {
        $store = Store::findOrFail($storeId);
        $store->update(['is_active' => ! $store->is_active]);
        unset($this->stores);
    }

    public function openEdit(int $storeId): void
    {
        $store = Store::with('owner')->findOrFail($storeId);
        $this->editingId = $store->id;
        $this->editName = $store->name;
        $this->editCommissionRate = $store->commission_rate !== null ? (string) $store->commission_rate : '';
        $this->editOwnerEmail = $store->owner?->email ?? '';
        $this->editOwnerPassword = '';
        $this->editOwnerPasswordConfirmation = '';
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        $store = Store::with('owner')->findOrFail($this->editingId);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editCommissionRate' => 'nullable|numeric|min:0|max:100',
            'editOwnerEmail' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($store->owner_id),
            ],
            'editOwnerPassword' => ['nullable', 'string', 'min:8', 'same:editOwnerPasswordConfirmation'],
        ]);

        $store->update([
            'name' => $this->editName,
            'commission_rate' => $this->editCommissionRate !== '' ? $this->editCommissionRate : null,
        ]);

        if ($store->owner) {
            $store->owner->update([
                'email' => $this->editOwnerEmail,
                ...($this->editOwnerPassword !== '' ? ['password' => $this->editOwnerPassword] : []),
            ]);
        }

        $this->showEditModal = false;
        unset($this->stores);
        Flux::toast(variant: 'success', text: 'Store updated.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Stores</flux:heading>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            New Store
        </flux:button>
    </div>

    <div class="mt-6">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Store</flux:table.column>
                <flux:table.column>Owner</flux:table.column>
                <flux:table.column align="center">Categories</flux:table.column>
                <flux:table.column align="end">Revenue</flux:table.column>
                <flux:table.column align="center">Commission</flux:table.column>
                <flux:table.column align="center">Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->stores as $store)
                    <flux:table.row :key="$store->id">
                        <flux:table.cell>
                            <p class="font-medium">{{ $store->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $store->slug }}.{{ config('tenancy.base_domain') }}</p>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($store->owner)
                                <p class="text-sm">{{ $store->owner->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $store->owner->email }}</p>
                            @else
                                <flux:text class="text-sm text-zinc-500">No owner</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="center">{{ $store->categories_count }}</flux:table.cell>

                        <flux:table.cell align="end" variant="strong">
                            R{{ fmt_price($store->revenue ?? 0) }}
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            @if ($store->commission_rate !== null)
                                {{ rtrim(rtrim(number_format((float) $store->commission_rate, 2), '0'), '.') }}%
                            @else
                                <flux:text class="text-xs text-zinc-500">Default</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="center">
                            <flux:badge :color="$store->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $store->is_active ? 'Active' : 'Disabled' }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button :href="route('admin.stores.show', $store)" variant="ghost" size="sm" icon="chart-bar" wire:navigate>
                                    Report
                                </flux:button>
                                <flux:button
                                    wire:click="openEdit({{ $store->id }})"
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                />
                                <flux:button
                                    wire:click="toggleActive({{ $store->id }})"
                                    variant="ghost"
                                    size="sm"
                                    :icon="$store->is_active ? 'pause' : 'play'"
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center text-zinc-500">
                            No stores yet.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- ── Create Store Modal ── --}}
    <flux:modal wire:model="showCreateModal" flyout class="md:w-96">
        <flux:heading size="lg">New Store</flux:heading>
        <flux:text class="mt-1">Creates the store and its reseller login in one step.</flux:text>

        <form wire:submit="create" class="mt-6 space-y-5">
            <flux:input
                wire:model.live.debounce.400ms="newStoreName"
                label="Store Name"
                placeholder="Mike's Store"
                required
                autofocus
            />

            <flux:input
                wire:model="newSlug"
                label="Subdomain"
                placeholder="mike"
                :description="'https://' . ($newSlug ?: '{slug}') . '.' . config('tenancy.base_domain')"
                required
            />

            <flux:input
                wire:model="newCommissionRate"
                label="Commission Rate (%)"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="Leave blank to use the platform default"
            />

            <flux:separator text="Reseller Login" />

            <flux:input
                wire:model="newOwnerName"
                label="Owner Name"
                placeholder="Mike Smith"
                required
            />

            <flux:input
                wire:model="newOwnerEmail"
                label="Owner Email"
                type="email"
                placeholder="mike@example.com"
                required
            />

            <flux:input
                wire:model="newOwnerPassword"
                label="Password"
                type="password"
                placeholder="Min. 8 characters"
                viewable
                required
            />

            <flux:input
                wire:model="newOwnerPasswordConfirmation"
                label="Confirm Password"
                type="password"
                placeholder="Repeat password"
                viewable
                required
            />

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Create Store</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ── Edit Store Modal ── --}}
    <flux:modal wire:model="showEditModal" flyout class="md:w-96">
        <flux:heading size="lg">Edit Store</flux:heading>

        <form wire:submit="saveEdit" class="mt-6 space-y-5">
            <flux:input
                wire:model="editName"
                label="Store Name"
                required
                autofocus
            />

            <flux:input
                wire:model="editCommissionRate"
                label="Commission Rate (%)"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="Leave blank to use the platform default"
            />

            <flux:separator text="Reseller Login" />

            <flux:input
                wire:model="editOwnerEmail"
                label="Owner Email"
                type="email"
                placeholder="mike@example.com"
                required
            />

            <flux:input
                wire:model="editOwnerPassword"
                label="New Password"
                type="password"
                placeholder="Leave blank to keep current password"
                viewable
            />

            <flux:input
                wire:model="editOwnerPasswordConfirmation"
                label="Confirm New Password"
                type="password"
                placeholder="Repeat new password"
                viewable
            />

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
