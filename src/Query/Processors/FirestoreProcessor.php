<?php

namespace Pruvo\LaravelFirestoreConnection\Query\Processors;

use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\FieldPath;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FirestoreProcessor extends Processor
{
    /**
     * Process an  "insert get ID" query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  \Google\Cloud\Firestore\CollectionReference $collectionReference
     * @param  array  $binding
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $collectionReference, $binding, $sequence = null)
    {
        $documentIdFieldPath = FieldPath::documentId()->pathString();

        $documentId = Arr::get($binding, $documentIdFieldPath, Str::orderedUuid()->toString());

        $documentReference = $collectionReference->document($documentId);

        if ($sequence) {
            data_set($binding, $sequence, $documentId);
        }

        foreach(Arr::dot($binding) as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = new Timestamp(Carbon::instance($value)->toDateTime());
                data_set($binding, $key, $value);
            }
        }

        $batch = $query->getConnection()->getClient()->batch();

        $batch->create($documentReference, Arr::except($binding, $documentIdFieldPath));

        $batch->commit();

        return $documentReference;
    }
}
