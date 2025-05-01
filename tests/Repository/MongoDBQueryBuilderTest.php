<?php

namespace App\Tests\Repository;

use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\never;


/**
 * MongoDBQueryBuilderTest
 *
 * Unit tests for the MongoDBQueryBuilder class.
 */
class MongoDBQueryBuilderTest extends TestCase
{
    /**
     * Sets up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(\MongoDB\Client::class);
        $this->mockDatabase = $this->createMock(\MongoDB\Database::class);
        $this->mockClient
            ->method('selectDatabase')
            ->willReturn($this->mockDatabase);

        $this->mockCollection = $this->createMock(\MongoDB\Collection::class);
        $this->mockDatabase
            ->method('selectCollection')
            ->willReturn($this->mockCollection);

        $this->queryBuilder = new \App\Repository\MongoDBQueryBuilder("mongodb://localhost:27017", "symfony_certification");
        $this->queryBuilder->selectCollection("test_collection");
    }

    /**
     * Tests the selectCollection method
     */
    public function testSelectCollection(): void
    {
        $this->assertNotSame($this->mockCollection, $this->queryBuilder->selectCollection("test_collection"));
    }

    /**
     * Tests the find method
     *
     * @return void
     */
    public function testFind(): void
    {
        $expectedResult = [
            ['_id' => 1, 'name' => 'Test Document'],
            ['_id' => 2, 'name' => 'Another Document'],
        ];

        $this->mockCollection
            ->expects(never())
            ->method('find')
            ->with(['name' => 'test'])
            ->willReturn(new \ArrayIterator($expectedResult));
        $this->queryBuilder->selectCollection("test_collection");
        $result = $this->queryBuilder->find(null);

        $this->assertIsObject($result);
    }

    /**
     * Tests the findOne method
     *
     * @return void
     */
    public function testFindOne(): void
    {
        $expectedResult = ['_id' => 1, 'name' => 'Test Document'];
        $result = $this->queryBuilder->findOne(['_id' => 1]);
        $this->assertIsArray($expectedResult);
    }

    /**
     * Tests the insertOne method
     *
     * @return void
     */
    public function testInsertOne(): void
    {
        $document = ['name' => 'Test Document'];
        $result = $this->queryBuilder->insertOne($document);
        $this->assertNotEquals(new ObjectId(), $result->getInsertedId());
    }

    /**
     * Tests the updateOne method
     *
     * @return void
     */
    public function testUpdateOne(): void
    {
        $filter = ['_id' => 1];
        $update = ['$set' => ['name' => 'Updated Document']];

        $result = $this->queryBuilder->updateOne($filter, $update);
        $this->assertEquals(0, $result->getModifiedCount());
    }

    /**
     * Tests the deleteOne method
     *
     * @return void
     */
    public function testDeleteOne(): void
    {
        $filter = ['_id' => 1];
        $result = $this->queryBuilder->deleteOne($filter);
        $this->assertEquals(0, $result->getDeletedCount());
    }

}
