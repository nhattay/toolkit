<?php

namespace Ollieread\Toolkit\Eloquent\Order;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Ollieread\Toolkit\Eloquent\Scope;

class OrderScope extends Scope
{
    protected $extensions = [
        'MoveUp', 'MoveDown', 'MoveToTop', 'MoveToBottom',
    ];

    public static function bootOrderScope(): void
    {
        /*
         * Set the order for all newly created rows.
         */
        static::creating(function (Model $model) {
            if ($model instanceof OrderContract) {
                $model->order = $model->newQuery()->withoutGlobalScopes()->count();
            }
        });
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @param  \Illuminate\Database\Eloquent\Model   $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof OrderContract) {
            $builder->getQuery()->unionOrders = [];
            $builder->orderBy($model->getOrderColumn(), 'ASC');
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     */
    protected function addMoveUp(Builder $builder): void
    {
        $builder::macro('moveUp', function (Builder $builder) {
            $orderColumn = $builder->getModel()->getOrderColumn();
            $newOrder    = max(0, $builder->getModel()->getAttribute($orderColumn) - 1);
            $builder->where($orderColumn, '=', $newOrder)->where('id', '!=', $builder->getModel()->id)->increment($orderColumn);
            $builder->getModel()->setAttribute($orderColumn, $newOrder)->save();
        });
    }

    protected function addMoveDown(Builder $builder): void
    {
        $builder::macro('moveDown', function (Builder $builder) {
            $orderColumn = $builder->getModel()->getOrderColumn();
            $maxOrder    = $builder->withoutGlobalScope($this)->count() - 1;
            $newOrder    = min($maxOrder, $builder->getModel()->getAttribute($orderColumn) + 1);
            $builder->where($orderColumn, '=', $newOrder)->where('id', '!=', $builder->getModel()->id)->decrement($orderColumn);
            $builder->getModel()->setAttribute($orderColumn, $newOrder)->save();
        });
    }

    protected function addMoveToTop(Builder $builder): void
    {
        $builder::macro('moveToTop', function (Builder $builder) {
            $orderColumn  = $builder->getModel()->getOrderColumn();
            $currentOrder = $builder->getModel()->getAttribute($orderColumn);
            $newOrder     = 0;
            $builder->where($orderColumn, '<=', $currentOrder)->where('id', '!=', $builder->getModel()->id)->increment($orderColumn);
            $builder->getModel()->setAttribute($orderColumn, $newOrder)->save();
        });
    }

    protected function addMoveToBottom(Builder $builder): void
    {
        $builder::macro('moveToBottom', function (Builder $builder) {
            $orderColumn  = $builder->getModel()->getOrderColumn();
            $currentOrder = $builder->getModel()->getAttribute($orderColumn);
            $newOrder     = $builder->withoutGlobalScope($this)->count() - 1;
            $builder->where($orderColumn, '>=', $currentOrder)->where('id', '!=', $builder->getModel()->id)->decrement($orderColumn);
            $builder->getModel()->setAttribute($orderColumn, $newOrder)->save();
        });
    }
}