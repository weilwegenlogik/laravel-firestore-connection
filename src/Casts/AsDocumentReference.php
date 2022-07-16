<?php

namespace Pruvo\LaravelFirestoreConnection\Casts;

use Google\Cloud\Firestore\DocumentReference;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Support\Facades\DB;

class AsDocumentReference implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return null|\Google\Cloud\Firestore\DocumentReference
     */
    public function get($model, $key, $value, $attributes)
    {
        return is_string($value)
            ? DB::connection('firestore')->getClient()->document($value)
            : $value;
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
        return ($value instanceof DocumentReference) ? $value->path() : $value;
    }

    /**
     * Serialize the attribute when converting the model to an array.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function serialize($model, string $key, $value, array $attributes)
    {
        return ($castedValue = $this->get(...func_get_args()))
            ? $castedValue->path()
            : null;
    }
}
