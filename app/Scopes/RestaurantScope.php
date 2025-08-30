<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class RestaurantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $restaurant_id = session('restaurant_id', null);
        if ($restaurant_id) {
            $builder->where('restaurant_id', $restaurant_id);
        }
        \App\Services\ConfChanger::switchCurrencyDirectly(session('restaurant_currency', ''), session('restaurant_convertion', 1));

    }
}
