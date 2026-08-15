<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'logo',
        'accent_color',
        'is_active',
        'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function effectiveCommissionRate(): float
    {
        return (float) ($this->commission_rate ?? Setting::get('commission_rate_default', 0));
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->logo) {
                    return null;
                }
                if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')) {
                    return $this->logo;
                }

                return Storage::url($this->logo);
            }
        );
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }
}
