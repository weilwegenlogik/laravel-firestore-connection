<?php

namespace Pruvo\LaravelFirestoreConnection\Query\Grammars;

use Google\Cloud\Firestore\V1\StructuredQuery\FieldFilter\Operator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class FirestoreGrammar extends Grammar
{
    /**
     * The grammar specific operators.
     *
     * @var string[]
     */
    protected $operators = [
        '=', '==', '===', '!=',
        '<', '<=', '>', '>=',
        'array-contains', 'array-contains-any', 'in',
    ];

    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $selectComponents = [
        'from', 'columns', 'wheres',
        'orders',
        'startAt', 'endAt',
        'startAfter', 'endBefore',
        'offset', 'limit', 'limitToLast',
    ];

    /**
     * Compiled Firestore query
     *
     * @var  \Google\Cloud\Firestore\Query
     */
    public $firestoreQuery;

    /**
     * Compile a select query into SQL.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        $firestoreQuery = $query->getConnection()->getClient();

        foreach ($this->compileComponents($query) as $components) {
            foreach ($components as $subcomponents) {
                if (Arr::isAssoc($subcomponents)) {
                    $subcomponents = [$subcomponents];
                }
                foreach ($subcomponents as $component) {
                    if (array_filter($component['arguments'])) {
                        $firestoreQuery = $firestoreQuery->{$component['method']}(...$component['arguments']);
                    }
                }
            }
        }

        return $firestoreQuery;
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $components = [];
        foreach ($this->selectComponents as $component) {
            if (isset($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $components[$component] = $this->$method($query, $query->$component);
            }
        }

        return $components;
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        return [[
            'method' => 'select',
            'arguments' => count($columns) == 1 && $columns[0] == '*' ? [] : [$columns]
        ]];
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  string  $collection
     * @return array
     */
    protected function compileFrom(Builder $query, $collection)
    {
        return [[
            'method' => $query->fromCollectionGroup ? 'collectionGroup' : 'collection',
            'arguments' => $query->fromCollectionGroup && $query->fromInDocument
                ? [$collection, $query->fromInDocument]
                : [$collection]
        ]];
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @return string
     */
    public function compileWheres(Builder $query)
    {
        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (is_null($query->wheres)) {
            return [];
        }

        // Firebase does not support query with where when is quering a collection group in a path.
        // Suggestion is use `of()` to query all documents in a collection group 
        // where the document reference path starts with and ends with the given path.
        if($query->wheres && $query->fromInDocument){
            throw new InvalidArgumentException('Query collection group in document path does not support where clauses.');
        }

        // If we actually have some where clauses, we will strip off the first boolean
        // operator, which is added by the query builders for convenience so we can
        // avoid checking for the first clauses in each of the compilers methods.
        return $this->compileWheresToArray($query);
    }

    /**
     * Get an array of all the where clauses for the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @return array
     */
    protected function compileWheresToArray($query)
    {
        $wheres = [];
        foreach ($query->wheres as $where) {
            if ($where['boolean'] === 'or') {
                throw new InvalidArgumentException('Firestore does not support OR operator on WHERE clauses');
            }

            $wheres[] = $this->{"where{$where['type']}"}($query, $where);
        }
        return $wheres;
    }

    private function prepareArrayableClause($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if (empty($values)) {
            throw new InvalidArgumentException("Values must not be a empty array.");
        }

        if (count($values) > 10) {
            throw new InvalidArgumentException("Values must not be greater than 10 items.");
        }

        if (Arr::isAssoc($values)) {
            throw new InvalidArgumentException("Values must not be an associative array.");
        }

        return $values;
    }

    /**
     * Compile a basic where clause.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBasic(Builder $query, $where)
    {
        return [
            'method' => 'where',
            'arguments' => [
                $where['column'],
                in_array($where['operator'], ['<>', '!==']) ? '!=' : $where['operator'],
                $where['value']
            ]
        ];
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereIn(Builder $query, $where)
    {
        $values = $this->prepareArrayableClause($where['values']);

        return [
            'method' => 'where',
            'arguments' => [$where['column'], Operator::IN, $values]
        ];
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotIn(Builder $query, $where)
    {
        $values = $this->prepareArrayableClause($where['values']);

        return [
            'method' => 'where',
            'arguments' => [$where['column'], Operator::NOT_IN, $values]
        ];
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNull(Builder $query, $where)
    {
        return [
            'method' => 'where',
            'arguments' => [$where['column'], Operator::EQUAL, null]
        ];
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotNull(Builder $query, $where)
    {
        return [
            'method' => 'where',
            'arguments' => [$where['column'], Operator::NOT_EQUAL, null]
        ];
    }

    /**
     * Compile a nested where clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNested(Builder $query, $where)
    {
        return $this->compileWheres($where['query']);
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        if (!empty($orders)) {
            return $this->compileOrdersToArray($query, $orders);
        }

        return [];
    }

    /**
     * Compile the query orders to an array.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $orders
     * @return array
     */
    protected function compileOrdersToArray(Builder $query, $orders)
    {
        return array_map(function ($order) {
            return [
                'method' => 'orderBy',
                'arguments' => [$order['column'], $order['direction']]
            ];
        }, $orders);
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return [[
            'method' => 'limit',
            'arguments' => [$limit]
        ]];
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimitToLast(Builder $query, $limit)
    {
        return [[
            'method' => 'limitToLast',
            'arguments' => [$limit]
        ]];
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return [[
            'method' => 'offset',
            'arguments' => [$offset]
        ]];
    }

    /**
     * Compile the "start at" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileStartAt(Builder $query, $startAt)
    {
        return [[
            'method' => 'startAt',
            'arguments' => [$startAt]
        ]];
    }

    /**
     * Compile the "end at" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileEndAt(Builder $query, $endAt)
    {
        return [[
            'method' => 'endAt',
            'arguments' => [$endAt]
        ]];
    }

    /**
     * Compile the "start after" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileStartAfter(Builder $query, $startAfter)
    {
        return [[
            'method' => 'startAfter',
            'arguments' => [$startAfter]
        ]];
    }

    /**
     * Compile the "end before" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileEndBefore(Builder $query, $endBefore)
    {
        return [[
            'method' => 'endBefore',
            'arguments' => [$endBefore]
        ]];
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $values
     * @return \Google\Cloud\Firestore\CollectionReference
     */
    public function compileInsert(Builder $query, array $values)
    {
        if ($query->fromCollectionGroup) {
            throw new \Exception('Collection group is not supported for insert queries.');
        }

        $firestoreQuery = $query->getConnection()->getClient();

        return $firestoreQuery->collection($query->from);
    }
}
