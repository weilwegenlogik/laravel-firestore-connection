<?php

namespace Pruvo\LaravelFirestoreConnection\Casts;

use Google\Cloud\Core\Timestamp as GoogleTimestamp;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Support\Carbon;

class AsCarbon implements CastsAttributes
{
    /**
     * Cast the given value to carbon.
     *
     * @param  mixed  $value
     * @return null|\Illuminate\Support\Carbon
     */
    private function handleDatetime($value)
    {
        if (!$value) {
            return null;
        }

        // From firebase
        if ($value instanceof GoogleTimestamp) {
            return Carbon::parse($value->get());
        }

        return Carbon::parse($value);
    }
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return null|\Illuminate\Support\Carbon|DateTime
     */
    public function get($model, $key, $value, $attributes)
    {
        return $this->handleDatetime($value);
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
        return $this->handleDatetime($value);
    }

    /**
     * Get the serialized representation of the value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return null|string
     */
    public function serialize($model, string $key, $value, array $attributes)
    {
        return ($date = $this->handleDatetime($value))
            ? $date->format(Carbon::W3C)
            : null;
    }
}
