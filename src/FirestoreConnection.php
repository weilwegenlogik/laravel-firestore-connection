<?php

namespace Pruvo\LaravelFirestoreConnection;

use Pruvo\LaravelFirestoreConnection\FirestoreClient;
use Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder;
use Pruvo\LaravelFirestoreConnection\Query\Grammars\FirestoreGrammar as QueryGrammar;
use Pruvo\LaravelFirestoreConnection\Query\Processors\FirestoreProcessor;
use Pruvo\LaravelFirestoreConnection\Schema\FirestoreBuilder;
use Pruvo\LaravelFirestoreConnection\Schema\FirestoreSchemaState;
use Pruvo\LaravelFirestoreConnection\Schema\Grammars\FirestoreGrammar as SchemaGrammar;
use Closure;
use Exception;
use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Firestore\FieldPath;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PDO;

class FirestoreConnection extends Connection
{
    private $client;

    /**
     * Create a new database connection instance.
     *
     * @param  \PDO|\Closure  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     * @param  array  $config
     * @return void
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct(...func_get_args());

        $config = Arr::except($config, ['driver', 'name', 'prefix']);

        if(
            array_key_exists('keyFilePath', $config)
            && is_file($config['keyFilePath']) === false
        ) {
            $config['keyFilePath'] = null;
        }
        
        $config = array_filter($config);

        $this->client = new FirestoreClient($config);
    }

    /**
     * Get Firestore Client
     *
     * @return \Google\Cloud\Firestore\FirestoreClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder
     */
    public function query()
    {
        return new FirestoreQueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  \Closure|\Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder|string  $table
     * @param  bool|null  $collectionGroup
     * @return \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder
     */
    public function table($table, $collectionGroup = null)
    {
        return $this->query()->from($table, $collectionGroup);
    }

    /**
     * Query on collection
     *
     * @param string|\Google\Cloud\Firestore\CollectionReference $collection
     * 
     * @return \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder
     */
    public function collection($collection)
    {
        return $this->query()->from($collection, $collectionGroup = false);
    }

    /**
     * Query on collection group
     *
     * @param string|\Google\Cloud\Firestore\CollectionReference $collection
     * @param boolean|string|\Google\Cloud\Firestore\DocumentReference $name
     * 
     * @return \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder
     */
    public function collectionGroup($collection, $relativeName = true)
    {
        return $this->query()->from($collection, $relativeName);
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Pruvo\LaravelFirestoreConnection\Query\Grammars\FirestoreGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Pruvo\LaravelFirestoreConnection\Schema\FirestoreBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new FirestoreBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Pruvo\LaravelFirestoreConnection\Schema\Grammars\FirestoreGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @param  \Illuminate\Filesystem\Filesystem|null  $files
     * @param  callable|null  $processFactory
     * @return \Pruvo\LaravelFirestoreConnection\Schema\FirestoreSchemaState
     */
    public function getSchemaState(Filesystem $files = null, callable $processFactory = null)
    {
        return new FirestoreSchemaState($this, $files, $processFactory);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Pruvo\LaravelFirestoreConnection\Query\Processors\FirestoreProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new FirestoreProcessor;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryStatement $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    public function run($query, $bindings, Closure $callback)
    {
        $start = microtime(true);

        // Here we will run this query. If an exception occurs we'll determine if it was
        // caused by a connection that has been lost. If that is the cause, we'll try
        // to re-establish connection and re-run the query with a fresh connection.
        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->handleQueryException(
                $e,
                $query->toSql(),
                $bindings,
                $callback
            );
        }

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query->toSql(),
            $bindings,
            $this->getElapsedTime($start)
        );

        return $result;
    }

    /**
     * Run a SQL statement.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryStatement  $query
     * @param  array  $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        // To execute the statement, we'll simply call the callback, which will actually
        // run the SQL against the PDO connection. Then we can calculate the time it
        // took to execute and log the query SQL, bindings and time in our memory.
        try {
            $result = $callback($query, $bindings);
        }

        // If an exception occurs when attempting to run a query, we'll format the error
        // message to include the bindings with SQL, which will make this exception a
        // lot more helpful to the developer instead of just the database's errors.
        catch (ServiceException $e) {
            /** @var \Google\ApiCore\ApiException $apiException */
            $apiException = $e->getServiceException();

            throw new QueryException(
                $query->toSql(),
                $this->prepareBindings([]),
                new Exception(
                    sprintf("[%s] %s", $apiException->getStatus(), $apiException->getBasicMessage()),
                    $e->getCode()
                )
            );
        }

        return $result;
    }

    /**
     * Run a select statement against the database.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $query = FirestoreQueryStatement::from($query, FirestoreQueryStatement::SELECT, $bindings);
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            /** @var \Pruvo\LaravelFirestoreConnection\FirestoreQueryStatement $query */
            return $query->toFirestore()->documents();
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        $query = FirestoreQueryStatement::from($query, FirestoreQueryStatement::INSERT, $bindings);

        return $this->run($query, $bindings, function ($query, $bindings) {

            /** @var \Pruvo\LaravelFirestoreConnection\FirestoreQueryStatement $query */

            if ($this->pretending()) {
                return true;
            }

            $collectionReference = $query->toFirestore();
            $documentIdFieldPath = FieldPath::documentId()->pathString();

            foreach (collect($bindings)->chunk(100) as $chunkedBidings) {
                $batch = $this->client->batch();

                foreach ($chunkedBidings as $binding) {
                    $documentId = Arr::get($binding, $documentIdFieldPath, Str::orderedUuid()->toString());

                    $batch->create($collectionReference->document($documentId), Arr::except($binding, $documentIdFieldPath));
                }

                $batch->commit();
            }

            $this->recordsHaveBeenModified();

            return true;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        $query = FirestoreQueryStatement::from($query, FirestoreQueryStatement::UPDATE, $bindings);

        return $this->run($query, $bindings, function ($query, $bindings) {
            /** @var \Pruvo\LaravelFirestoreConnection\FirestoreQueryStatement $query */

            if ($this->pretending()) {
                return 0;
            }

            /** @var \Illuminate\Support\Collection<\Google\Cloud\Firestore\DocumentSnapshot> $result */
            $result = $query->query->get([]);

            /** @var \Google\Cloud\Firestore\DocumentSnapshot[][] $allDocs */
            $allDocs = $result->chunk(100);

            foreach ($allDocs as $docs) {
                $batch = $this->client->batch();

                foreach ($docs as $doc) {
                    $batch->set(
                        $doc->reference(),
                        $bindings,
                        ['merge' => true]
                    );
                }

                $batch->commit();
            }

            $this->recordsHaveBeenModified($result->isNotEmpty());

            return $result->count();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  \Pruvo\LaravelFirestoreConnection\FirestoreQueryBuilder  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        $query = FirestoreQueryStatement::from($query, FirestoreQueryStatement::DELETE, $bindings);

        return $this->run($query, $bindings, function ($query, $bindings) {
            /** @var \Pruvo\LaravelFirestoreConnection\FirestoreQueryStatement $query */

            if ($this->pretending()) {
                return 0;
            }

            /** @var \Illuminate\Support\Collection<\Google\Cloud\Firestore\DocumentSnapshot> $result */
            $result = $query->query->get([]);

            /** @var \Google\Cloud\Firestore\DocumentSnapshot[][] $allDocs */
            $allDocs = $result->chunk(500);

            foreach ($allDocs as $docs) {
                $batch = $this->client->batch();

                foreach ($docs as $doc) {
                    $batch->delete($doc->reference());
                }

                $batch->commit();
            }

            $this->recordsHaveBeenModified($result->isNotEmpty());

            return $result->count();
        });
    }
}
