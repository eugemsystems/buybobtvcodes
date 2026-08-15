<?php

namespace App\Models\Scopes;

use App\Models\Category;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TokenStoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = app(CurrentStore::class)->id();

        if ($storeId !== null) {
            $builder->whereIn(
                'category_id',
                Category::withoutGlobalScopes()->where('store_id', $storeId)->select('id'),
            );
        }
    }
}
