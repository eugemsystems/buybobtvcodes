<?php

namespace App\Models\Scopes;

use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = app(CurrentStore::class)->id();

        if ($storeId !== null) {
            $builder->where($model->getTable().'.store_id', $storeId);
        }
    }
}
