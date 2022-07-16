<?php

namespace Pruvo\LaravelFirestoreConnection\SolutionProviders;

use Facade\IgnitionContracts\HasSolutionsForThrowable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Pruvo\LaravelFirestoreConnection\Solutions\QueryExceptionSolution;
use Throwable;

class QueryExceptionSolutionProvider implements HasSolutionsForThrowable
{
    public function canSolve(Throwable $throwable): bool
    {
        return $throwable instanceof QueryException
            && Str::of($throwable->getMessage())->contains('requires an index');
    }

    /** \Facade\IgnitionContracts\Solution[] */
    public function getSolutions(Throwable $throwable): array
    {
        return [
            new QueryExceptionSolution($throwable),
        ];
    }
}
