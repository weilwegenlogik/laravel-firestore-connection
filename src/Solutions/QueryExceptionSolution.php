<?php

namespace Pruvo\LaravelFirestoreConnection\Solutions;

use Facade\IgnitionContracts\Solution;
use Illuminate\Support\Str;
use Throwable;

class QueryExceptionSolution implements Solution
{
    /**
     * @var Throwable
     */
    private $throwable;

    public function __construct(Throwable $throwable = null)
    {
        $this->throwable = $throwable;
    }

    public function getSolutionTitle(): string
    {
        return 'This query requires an index which was not created.';
    }

    public function getSolutionDescription(): string
    {
        return 'If you attempt a compound query with a range clause that doesn\'t map to an existing index, you receive an error.';
    }

    public function getDocumentationLinks(): array
    {
        return [
            'Create this missing index' => (string) Str::of($this->throwable->getMessage())->match('/https:\/\/console.firebase.google.com[\w.,@?^=%&:\/~+#-]*[\w@?^=%&\/~+#-]/'),
            'Manually create a new index' => 'https://firebase.google.com/docs/firestore/query-data/indexing#use_the_firebase_console',
        ];
    }
}
