<?php

namespace App\Repository;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\DeleteResult;
use MongoDB\Driver\Cursor;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;

/**
 * MongoDBQueryBuilder
 */
class MongoDBQueryBuilder
{
    private Client $client;
    private string|Collection $collection;

    private Database $database;

    /**
     * Constructor
     *
     * @param string $url MongoDB connection URL
     * @param string $databaseName
     */
    public function __construct(string $url, string $databaseName)
    {
        $this->client = new Client($url);
        $this->database = $this->client->selectDatabase($databaseName);
    }

    /**
     * Finds the MongoDB collection
     *
     * @param int|null $limit   limit
     * @param array $filter     filter
     * @param array $options    options
     * @return Cursor
     */
    public function find(?int $limit, array $filter = [], array $options = []): Cursor
    {
        if ($limit !== null) {
            $options['limit'] = $limit;
        }

        return $this->collection->find($filter, $options);
    }

    /**
     * Get the MongoDB collection
     *
     * @param array $filter
     * @param array $options
     * @return object|array|null
     */
    public function findOne(array $filter = [], array $options = []): object|array|null
    {
        return $this->collection->findOne($filter, $options);
    }

    /**
     * Inserts a document
     *
     * @param array $document
     * @return InsertOneResult
     */
    public function insertOne(array $document): InsertOneResult
    {
        return $this->collection->insertOne($document);
    }

    /**
     * Updates a document
     *
     * @param array $filter
     * @param array $update
     * @param array $options
     * @return UpdateResult
     */
    public function updateOne(array $filter, array $update, array $options = []): UpdateResult
    {
        return $this->collection->updateOne($filter, $update, $options);
    }

    /**
     * Deletes a document
     *
     * @param array $filter
     * @param array $options
     * @return DeleteResult
     */
    public function deleteOne(array $filter, array $options = []): DeleteResult
    {
        return $this->collection->deleteOne($filter, $options);
    }

    /**
     * Selects a collection
     *
     * @param string|Collection $collection
     * @return self
     */
    public function selectCollection(string|Collection $collection): self
    {
        $this->collection = $this->database->selectCollection($collection);

        return $this;
    }

}

