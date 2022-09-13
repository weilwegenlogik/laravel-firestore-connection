<?php

namespace Pruvo\LaravelFirestoreConnection\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\ArrayObject;

class AsArrayObject implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        return isset($attributes[$key]) ? new ArrayObject($attributes[$key]) : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        return [$key => (array) $value];
    }

    public function serialize($model, string $key, $value, array $attributes)
    {
        return $value->getArrayCopy();
    }

    public function toFirestore($model, string $key, $value, array $attributes)
    {
        return $value->getArrayCopy();
    }
}
