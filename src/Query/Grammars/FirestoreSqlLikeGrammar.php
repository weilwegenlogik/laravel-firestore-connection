<?php

namespace Pruvo\LaravelFirestoreConnection\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class FirestoreSqlLikeGrammar extends Grammar
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
        'columns', 'from', 'wheres',
        'orders',
        'startAt', 'endAt',
        'startAfter', 'endBefore',
        'offset', 'limit', 'limitToLast',
    ];

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        return $value;
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        $compiledColumns = parent::compileColumns($query, $columns);

        if(!$columns){
            $compiledColumns .= '1';
        }
        
        return $compiledColumns;
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        return 'from '
            . ($query->collectionGroup ? 'all ' : '')
            . $this->wrapTable($table)
            . ($query->collectionGroup && $query->collectionGroupParent ? " in ($query->collectionGroupParent) " : '');
    }

    /**
     * Compile the "limit to last" portions of the query.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimitToLast(Builder $query, $limit)
    {
        return 'limit to last ' . (int) $limit;
    }

    /**
     * Compile the "start at" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileStartAt(Builder $query, $startAt)
    {
        return $startAt ? 'start at ' . $this->parameter($startAt) : '';
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
        return $endAt ? 'and end at ' . $this->parameter($endAt) : '';
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
        return $startAfter ? 'start after ' . $this->parameter($startAfter) : '';
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
        return $endBefore ? 'and end before ' . $this->parameter($endBefore) : '';
    }
}
