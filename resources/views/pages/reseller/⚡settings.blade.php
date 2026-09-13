<?php

use App\Models\Store;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Store Settings')] class extends Component
{
    use WithFileUploads;

    #[Validate('nullable|image|max:2048')]
    public $logoUpload = null;

    public string $logoUrl = '';
    public string $logoMode = 'file';
    public string $currentLogoUrl = '';

    public function mount(): void
    {
        $store = auth()->user()->store;

        $this->currentLogoUrl = (string) ($store?->logo_url ?? '');
        $this->logoUrl = $store?->logo && str_starts_with($store->logo, 'http') ? $store->logo : '';
        $this->logoMode = $this->logoUrl ? 'url' : 'file';
    }

    public function saveLogo(): void
    {
        $this->validateOnly('logoUpload');

        $store = auth()->user()->store;

        if ($this->logoMode === 'file' && $this->logoUpload) {
            $value = $this->logoUpload->store('store-logos', 'public');
        } elseif ($this->logoMode === 'url' && $this->logoUrl) {
            $value = $this->logoUrl;
        } else {
            $value = null;
        }

        $store->update(['logo' => $value]);

        $this->currentLogoUrl = (string) ($store->fresh()->logo_url ?? '');
        $this->logoUpload = null;

        Flux::toast(variant: 'success', text: 'Logo saved successfully.');
    }

    public function removeLogo(): void
    {
        auth()->user()->store->update(['logo' => null]);

        $this->currentLogoUrl = '';
        $this->logoUrl = '';
        $this->logoUpload = null;

        Flux::toast(variant: 'success', text: 'Logo removed.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-8 p-6">
    <div>
        <flux:heading size="xl">Store Settings</flux:heading>
        <flux:text class="mt-1 text-zinc-400">Manage your store's branding.</flux:text>
    </div>

    {{-- ── Logo Section ── --}}
    <div class="rounded-xl border border-zinc-700/60 bg-zinc-900/50 p-6">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">Store Logo</flux:heading>
                <flux:text class="mt-1 text-zinc-400 text-sm">Shown on your storefront and here in your dashboard.</flux:text>
            </div>
            @if ($currentLogoUrl)
                <flux:button wire:click="removeLogo" variant="ghost" size="sm" class="text-red-400 hover:text-red-300 flex-shrink-0">
                    Remove
                </flux:button>
            @endif
        </div>

        {{-- Current preview --}}
        @if ($currentLogoUrl)
            <div class="mb-6 flex items-center gap-4">
                <div class="flex h-16 w-40 items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 px-3">
                    <img src="{{ $currentLogoUrl }}" alt="Current logo" class="max-h-12 max-w-full object-contain" />
                </div>
                <div>
                    <p class="text-xs font-semibold text-zinc-400">Current Logo</p>
                </div>
            </div>
        @else
            <div class="mb-6 flex h-16 w-40 items-center justify-center rounded-lg border border-dashed border-zinc-700 bg-zinc-800/50">
                <p class="text-xs text-zinc-600">No logo set</p>
            </div>
        @endif

        {{-- Mode tabs --}}
        <div class="flex gap-2 mb-4">
            <flux:button
                wire:click="$set('logoMode', 'file')"
                type="button"
                :variant="$logoMode === 'file' ? 'primary' : 'ghost'"
                size="sm"
            >
                Upload File
            </flux:button>
            <flux:button
                wire:click="$set('logoMode', 'url')"
                type="button"
                :variant="$logoMode === 'url' ? 'primary' : 'ghost'"
                size="sm"
            >
                Image URL
            </flux:button>
        </div>

        @if ($logoMode === 'file')
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <flux:label>Upload Logo</flux:label>
                    <div class="mt-2">
                        <input
                            wire:model="logoUpload"
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-zinc-400 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-zinc-200 hover:file:bg-zinc-600"
                        />
                        @error('logoUpload')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <flux:button wire:click="saveLogo" variant="primary">Save Logo</flux:button>
            </div>
            @if ($logoUpload)
                @php try { $logoPreviewUrl = $logoUpload->temporaryUrl(); } catch (\Throwable) { $logoPreviewUrl = null; } @endphp
                @if ($logoPreviewUrl)
                    <img src="{{ $logoPreviewUrl }}" alt="Preview" class="mt-3 h-12 max-w-[180px] object-contain rounded-lg border border-zinc-700 bg-zinc-800 p-2" />
                @else
                    <p class="mt-3 text-xs text-zinc-400">Selected: <span class="text-zinc-200">{{ $logoUpload->getClientOriginalName() }}</span></p>
                @endif
            @endif
        @else
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <flux:input
                        wire:model="logoUrl"
                        label="Logo URL"
                        placeholder="https://example.com/logo.png"
                        type="url"
                    />
                </div>
                <flux:button wire:click="saveLogo" variant="primary">Save Logo</flux:button>
            </div>
            @if ($logoUrl && $logoUrl !== $currentLogoUrl)
                <img src="{{ $logoUrl }}" alt="Preview" class="mt-3 h-12 max-w-[180px] object-contain rounded-lg border border-zinc-700 bg-zinc-800 p-2" onerror="this.style.display='none'" />
            @endif
        @endif
    </div>
</div>
