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

        Event::listen("eloquent.booting: *", function ($eventName, array $data) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $data[0];

            /**
             * @todo: check if the model is firebaseable with a contract instead trait exists
             */
            if ($model && in_array(Firebaseable::class, class_uses($model))) {
                $model->setIncrementing(false)->setKeyType('string')->setConnection('firestore');
            }
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
