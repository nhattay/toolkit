<?php

namespace Ollieread\Toolkit\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Base Repository
 *
 * @package Ollieread\Toolkit\Repositories
 */
abstract class BaseRepository
{
    /**
     * @var string
     */
    protected $model;

    /**
     * @return Model
     */
    protected function make(): Model
    {
        return new $this->model;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function query(): Builder
    {
        return $this->make()->newQuery();
    }

    /**
     * Accepts either the id or model. It's a safety method so that you can just pass arguments in
     * and receive the id back.
     *
     * @param $model
     *
     * @return mixed
     */
    protected function getId($model): int
    {
        return $model instanceof Model ? $model->getKey() : $model;
    }

    /**
     * Accepts either the id or model. It's a safety method so that you can just pass arguments in
     * and receive the model back.
     *
     * @param $model
     *
     * @return \Illuminate\Database\Eloquent\Model|mixed|null
     */
    public function getOneById($model)
    {
        return $model instanceof Model ? $model : $this->getOneBy('id', $model);
    }

    /**
     * Persist the model data.
     *
     * Pass in an array of input, and either an existing model or an id. Passing null to the
     * second argument will create a new instance.
     *
     * @param array $input
     * @param null  $model
     *
     * @return \Illuminate\Database\Eloquent\Model|mixed|null
     */
    public function persist(array $input, $model = null)
    {
        if ($model) {
            $model = $this->getOneById($model);
        } else {
            $model = $this->make();
        }

        if ($model instanceof $this->model) {
            $model->fill($input);

            if ($model->save()) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Delete the model.
     *
     * @param $model
     *
     * @return bool|null
     * @throws \Exception
     */
    public function delete($model): ?bool
    {
        if ($model instanceof Model) {
            return $model->delete();
        }

        $id    = $model;
        $model = $this->make();

        return $model->newQuery()
            ->where($model->getKeyName(), $id)
            ->delete();
    }

    /**
     * Helper method for retrieving models by a column or array of columns.
     *
     * @return mixed
     */
    public function getBy(): ?Collection
    {
        $model = $this->query();

        if (\func_num_args() === 2) {
            list($column, $value) = \func_get_args();
            $method = \is_array($value) ? 'whereIn' : 'where';
            $model  = $model->$method($column, $value);
        } elseif (\func_num_args() === 1) {
            $columns = func_get_arg(0);

            if (\is_array($columns)) {
                foreach ($columns as $column => $value) {
                    $method = \is_array($value) ? 'whereIn' : 'where';
                    $model  = $model->$method($column, $value);
                }
            }
        }

        return $model->get();
    }

    /**
     * Helper method for retrieving a model by a column or array of columns.
     *
     * @return mixed
     */
    public function getOneBy(): ?Model
    {
        $model = $this->query();

        if (\func_num_args() === 2) {
            list($column, $value) = \func_get_args();
            $method = \is_array($value) ? 'whereIn' : 'where';
            $model  = $model->$method($column, $value);
        } elseif (\func_num_args() === 1) {
            $columns = \func_get_args();

            if (\is_array($columns)) {
                foreach ($columns as $column => $value) {
                    $method = \is_array($value) ? 'whereIn' : 'where';
                    $model  = $model->$method($column, $value);
                }
            }
        }

        return $model->first();
    }

    /**
     * Magic method handling for dynamic functions such as getByAddress() or getOneById().
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed|null
     */
    public function __call(string $name, array $arguments = [])
    {
        if (\count($arguments) > 1) {
            // TODO: Should probably throw an exception here
            return null;
        }

        if (0 === strpos($name, 'getBy')) {
            return $this->getBy(snake_case(substr($name, 5)), $arguments[0]);
        }

        if (0 === strpos($name, 'getOneBy')) {
            $column = snake_case(substr($name, 8));

            return \call_user_func([$this->make(), 'where'], $column, $arguments[0])->first();
        }
    }

    /**
     * Perform a transaction.
     *
     * @param \Closure    $callback
     * @param int         $attempts
     * @param string|null $connection
     *
     * @return mixed
     * @throws \Exception|\Throwable
     */
    public static function transaction(\Closure $callback, int $attempts = 1, string $connection = null)
    {
        if ($connection) {
            return DB::connection($connection)->transaction($callback, $attempts);
        }

        return DB::transaction($callback, $attempts);
    }
}