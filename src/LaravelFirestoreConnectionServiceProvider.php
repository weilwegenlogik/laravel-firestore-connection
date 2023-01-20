<?php

namespace Pruvo\LaravelFirestoreConnection;

use Facade\Ignition\SolutionProviders\SolutionProviderRepository;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Pruvo\LaravelFirestoreConnection\Firebaseable;
use Pruvo\LaravelFirestoreConnection\FirestoreConnection;
use Pruvo\LaravelFirestoreConnection\SolutionProviders\QueryExceptionSolutionProvider;

class LaravelFirestoreConnectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        Connection::resolverFor('firestore', function ($connection, $database, $prefix, $config) {
            return new FirestoreConnection($connection, $database, $prefix, $config);
        });

        $this->app->make(SolutionProviderRepository::class)->registerSolutionProviders([
            QueryExceptionSolutionProvider::class,
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
