<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->bound('tenant')) {
                $tenant = app('tenant');
                $builder->where(
                    $builder->getModel()->getTable() . '.restaurant_id',
                    $tenant->id
                );
            }
        });

        static::creating(function ($model) {
            if (app()->bound('tenant') && empty($model->restaurant_id)) {
                $model->restaurant_id = app('tenant')->id;
            }
        });
    }
}
