<?php

namespace Pruvo\LaravelFirestoreConnection;

use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FieldPath;
use Google\Cloud\Firestore\FieldValue;
use Google\Cloud\Firestore\Query;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Pruvo\LaravelFirestoreConnection\Firebaseable;
use Pruvo\LaravelFirestoreConnection\Query\Grammars\FirestoreSqlLikeGrammar;

class FirestoreQueryBuilder extends QueryBuilder
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        "<", "<=", ">", ">=",
        "=", "!=", "==", "===", "<>", "!==",
        "array-contains", "array-contains-any",
        "in", "not-in",
    ];

    /**
     * Start at for the query.
     *
     * @var mixed[]
     */
    public $startAt = [];

    /**
     * End at for the query.
     *
     * @var mixed[]
     */
    public $endAt = [];

    /**
     * Start after for the query.
     *
     * @var mixed[]
     */
    public $startAfter = [];

    /**
     * End before for the query.
     *
     * @var mixed[]
     */
    public $endBefore = [];

    /**
     * Is limit query to last.
     *
     * @var boolean
     */
    public $limitToLast;

    /**
     * The collection which the query is targeting.
     *
     * @var string
     */
    public $from;

    /** 
     * Query in collection group.
     * @var bool
     */
    public $fromCollectionGroup = false;

    /** 
     * Query on collection group in document reference.
     * @var null|string
     */
    public $fromInDocument;

    /**
     * Collection name where quere is building. 
     * The array key `allDescendants` is only present with `true` value 
     * when the query is on the collection group.
     * 
     * Example
     * @return array
     * ```php
     * ["collectionId" => "collection_name_here", "allDescendants" => true]
     * ```
     */
    private function getFrom(Query $collection)
    {
        return $collection->queryKey('from')[0];
    }

    /**
     * Check if the query is on the collection group.
     *
     * @return boolean
     */
    private function isQueryOnCollectionGroup(Query $collection): bool
    {
        return data_get($this->getFrom($collection), 'allDescendants', false) === true;
    }

    /**
     * Collection name where quere is building.
     * 
     * @return string
     */
    private function getCollectionName(Query $collection): string
    {
        return data_get($this->getFrom($collection), 'collectionId');
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string|\Google\Cloud\Firestore\CollectionReference $collection
     * @param  bool|\Illuminate\Database\Eloquent\Model|\Google\Cloud\Firestore\DocumentReference|\Google\Cloud\Firestore\DocumentSnapshot|string|null  $on
     * @throws \InvalidArgumentException if an invalid collection or document path is provided.
     * @return $this
     */
    public function from($collection, $on = null)
    {
        // cast collection to instance (validate the collection path)
        if (is_string($collection)) {
            $collection = $this->getConnection()->getClient()->collection($collection);
        }

        if ($collection instanceof CollectionReference) {
            $this->from = $collection->path();
        }

        if (is_bool($on)) {
            $this->inCollectionGroup($on);
        } elseif($on) {
            $this->in($on);
        }

        return $this;
    }

    /**
     * Query in collection group.
     * @param bool $active 
     * - `true` to query in collection group
     * - `false` to query in collection path
     * 
     * @return static
     */
    public function inCollectionGroup(bool $active = true)
    {
        $this->fromCollectionGroup = $active;

        return $this;
    }

    /**
     * Query in collection group inside a path.
     * 
     * @param \Illuminate\Database\Eloquent\Model|\Google\Cloud\Firestore\DocumentReference|\Google\Cloud\Firestore\DocumentSnapshot|string|null $document
     * @return static
     */
    public function in($document = null)
    {
        if (Str::contains($this->from, '/')) {
            throw new InvalidArgumentException(sprintf("The collection [%s] is not compatible with collection group.", $this->from));
        }

        if (
            $document instanceof Model
            && in_array(Firebaseable::class, class_uses($document))
            && $document->exists
        ) {
            $document = $document->getDocumentReference();
        } elseif (is_string($document)) {
            $document = $this->getConnection()->getClient()->document($document);
        }

        if ($document instanceof DocumentReference || $document instanceof DocumentSnapshot) {
            $document = $document->path();
        } else {
            throw new InvalidArgumentException(sprintf("Invalid document reference [%s]", $document));
        }

        $this->fromInDocument = $document;
        $this->inCollectionGroup();

        return $this;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param Model|DocumentReference|DocumentSnapshot|string $of
     * @return $this
     */
    public function of($of)
    {
        if (is_string($of)) {
            $ref = $this->getConnection()->getClient()->document($of)->path();
        } elseif ($of instanceof DocumentReference || $of instanceof DocumentSnapshot) {
            $ref = $of->path();
        } elseif ($of instanceof Model) {
            $ref = $of->getDocumentReference()->path();
        } else {
            throw new \InvalidArgumentException(sprintf(
                "The argument must be valid string path or one of the following instaces: [%s], [%s], or [%s]",
                Model::class,
                DocumentReference::class,
                DocumentSnapshot::class
            ));
        }

        return $this
            ->inCollectionGroup()
            ->orderBy(FieldPath::documentId(), 'asc')
            ->startAt([$ref])
            // The `\uf8ff` character used in the query above is a very high code point in the Unicode range. 
            // Because it is after most regular characters in Unicode, the query matches all values that start with `$ref`.
            ->endAt([$ref . "\uf8ff"]);
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        return (new FirestoreSqlLikeGrammar)->compileSelect($this);
    }

    /**
     * Get the Firestore query instance.
     *
     * @return \Google\Cloud\Firestore\Query
     */
    public function toFirestoreQuery()
    {
        return parent::toSql();
    }

    /**
     * Get the database connection instance.
     *
     * @return \Pruvo\LaravelFirestoreConnection\FirestoreConnection
     */
    public function getConnection()
    {
        return parent::getConnection();
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    public function runSelect()
    {
        return $this->connection->select(
            $this,
            $this->getBindings(),
            !$this->useWritePdo
        );
    }

    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     *
     * @param  array  $columns
     * @param  callable  $callback
     * @return mixed
     */
    protected function onceWithColumns($columns, $callback)
    {
        $original = $this->columns;

        $this->columns = $columns;

        $result = $callback();

        $this->columns = $original;

        return $result;
    }


    /**
     * Set the "start at" value of the query.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function startAt($values)
    {
        $values = is_array($values) ? $values : func_get_args();
        foreach ($values as $value) {
            $this->startAt[] = $value;
        }

        return $this;
    }

    /**
     * Set the "end at" value of the query.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function endAt($values)
    {
        $values = is_array($values) ? $values : func_get_args();
        foreach ($values as $value) {
            $this->endAt[] = $value;
        }

        return $this;
    }

    /**
     * Set the "start after" value of the query.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function startAfter($values)
    {
        $values = is_array($values) ? $values : func_get_args();
        foreach ($values as $value) {
            $this->startAfter[] = $value;
        }

        return $this;
    }

    /**
     * Set the "end before" value of the query.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function endBefore($values)
    {
        $values = is_array($values) ? $values : func_get_args();
        foreach ($values as $value) {
            $this->endBefore[] = $value;
        }

        return $this;
    }

    /**
     * Set the "limit to last" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limitToLast($value)
    {
        if ($value >= 0) {
            $this->limitToLast = $value;
        }

        return $this;
    }

    /**
     * Add a "where array-contains" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereArrayContains($column, $values, $boolean = 'and', $not = false)
    {
        return $this->where($column, 'array-contains', $values, $boolean, $not);
    }

    /**
     * Add a "where array-contains-any" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereArrayContainsAny($column, $values, $boolean = 'and', $not = false)
    {
        return $this->where($column, 'array-contains-any', $values, $boolean, $not);
    }

    public function select($columns = ['*'])
    {
        if (is_null($columns) || $columns === []) {
            $columns = ['__name__'];
        }
        return parent::select($columns);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $columns
     * @return int
     */
    public function count($columns = '*')
    {
        return (int) $this->get([])->count();
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->get([$column])
            ->map(function (DocumentSnapshot $doc) use ($column) {
                try {
                    return $doc->get($column);
                } catch (\Throwable $th) {
                    return null;
                }
            })
            ->reject(function ($value) {
                return is_null($value);
            })
            ->min();
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->get([$column])
            ->map(function (DocumentSnapshot $doc) use ($column) {
                try {
                    return $doc->get($column);
                } catch (\Throwable $th) {
                    return null;
                }
            })
            ->reject(function ($value) {
                return is_null($value);
            })
            ->max();
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        return $this->get([$column])
            ->map(function (DocumentSnapshot $doc) use ($column) {
                try {
                    return $doc->get($column);
                } catch (\Throwable $th) {
                    return null;
                }
            })
            ->reject(function ($value) {
                return is_null($value);
            })
            ->sum();
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->get([$column])
            ->map(function (DocumentSnapshot $doc) use ($column) {
                try {
                    return $doc->get($column);
                } catch (\Throwable $th) {
                    return null;
                }
            })
            ->reject(function ($value) {
                return is_null($value);
            })
            ->avg();
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  string  $column
     * @return mixed
     */
    public function average($column)
    {
        return $this->avg($column);
    }

    /**
     * Insert new records into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        return $this->connection->insert(
            $this,
            $values
        );
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $this->applyBeforeQueryCallbacks();

        $collectionReference = $this->grammar->compileInsertGetId($this, $values, $sequence);

        return $this->processor->processInsertGetId($this, $collectionReference, $values, $sequence);
    }

    /**
     * Update records in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $this->applyBeforeQueryCallbacks();

        return $this->connection->update($this, $values);
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array  $extra
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $columns = array_merge([$column => FieldValue::increment($amount)], $extra);

        return $this->update($columns);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array  $extra
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $columns = array_merge([$column => FieldValue::increment($amount * -1)], $extra);

        return $this->update($columns);
    }

    /**
     * Delete records from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where('id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->delete($this, $this->bindings);
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array  $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        return $this->clone()->count($columns);
    }
}
