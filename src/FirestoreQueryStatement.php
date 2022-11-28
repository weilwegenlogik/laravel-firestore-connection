<?php

namespace Pruvo\LaravelFirestoreConnection;

use Illuminate\Support\Arr;
use Pruvo\LaravelFirestoreConnection\Query\Grammars\FirestoreSqlLikeGrammar;

class FirestoreQueryStatement
{
    const SELECT = 1;
    const INSERT = 2;
    const UPDATE = 3;
    const DELETE = 4;

    /**
     * @var FirestoreQueryBuilder
     */
    public $query;
    /**
     * @var int
     */
    public $type;

    /**
     * @var array
     */
    public $bindings;

    public function __construct(FirestoreQueryBuilder $query, $type = self::SELECT, $bindings = [])
    {
        $this->query = $query;
        $this->type = $type;
        $this->bindings = Arr::except($bindings, '__name__');
    }

    public static function from(FirestoreQueryBuilder $query, $type = self::SELECT, $bindings = [])
    {
        return new self($query, $type, $bindings);
    }

    /**
     * Firestore query
     *
     * @return \Google\Cloud\Firestore\CollectionReference
     */
    public function toFirestore(int $type = null)
    {
        if (!$type) {
            $type = $this->type;
        }

        $this->query->applyBeforeQueryCallbacks();

        if ($type == self::SELECT) {
            return $this->query->grammar->compileSelect($this->query);
        } elseif ($type == self::INSERT) {
            return $this->query->grammar->compileInsert($this->query, $this->bindings);
        }
        elseif ($type == self::UPDATE) {
            return $this->query->grammar->compileSelect($this->query);
        } 
        elseif ($type == self::DELETE) {
            return $this->query->grammar->compileSelect($this->query);
        }
    }

    /**
     * SQL like query
     *
     * @param int $type
     * @return string|null
     */
    public function toSql(int $type = null): ?string
    {
        if (!$type) {
            $type = $this->type;
        }

        $sqlGrammar = new FirestoreSqlLikeGrammar;

        if ($type == self::SELECT) {
            return $sqlGrammar->compileSelect($this->query);
        } elseif ($type == self::INSERT) {
            return $sqlGrammar->compileInsert($this->query, $this->bindings);
        }
        elseif ($type == self::UPDATE) {
            return $sqlGrammar->compileUpdate($this->query, $this->bindings);
        } 
        elseif ($type == self::DELETE) {
            return $sqlGrammar->compileDelete($this->query, $this->bindings);
        }
    }
}
