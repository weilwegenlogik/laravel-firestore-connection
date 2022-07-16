<?php

namespace Pruvo\LaravelFirestoreConnection;

use Google\Cloud\Core\ClientTrait;
use Google\Cloud\Core\ValidateTrait;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\FirestoreClient as Client;
use Google\Cloud\Firestore\Query;
use Google\Cloud\Firestore\SnapshotTrait;
use ReflectionObject;

class FirestoreClient extends Client
{
    use ClientTrait;
    use SnapshotTrait;
    use ValidateTrait;

    /**
     * @var \Google\Cloud\Firestore\Connection\ConnectionInterface
     */
    private $connection;

    /**
     * @var string
     */
    private $database = self::DEFAULT_DATABASE;

    /**
     * @var \Google\Cloud\Firestore\ValueMapper
     */
    private $valueMapper;

    public function __construct()
    {
        parent::__construct(...func_get_args());

        $reflection = new ReflectionObject($this);
        $r = $reflection->getParentClass();

        foreach (['projectId', 'connection', 'valueMapper'] as $property) {
            $rp = $r->getProperty($property);
            $rp->setAccessible(true);
            $this->{$property} = $rp->getValue($this);
        }
    }

    public function collectionGroup($id, $relativeName = null)
    {
        if (strpos($id, '/') !== false) {
            throw new \InvalidArgumentException(
                'Collection ID may not contain a slash.'
            );
        }
        if ($relativeName instanceof DocumentSnapshot || $relativeName instanceof DocumentReference) {
            $relativeName = $relativeName->path();
        }

        return new Query(
            $this->connection,
            $this->valueMapper,
            $this->fullName($this->projectId, $this->database, $relativeName),
            [
                'from' => [
                    [
                        'collectionId' => $id,
                        'allDescendants' => true
                    ]
                ]
            ]
        );
    }
}
