<?php

/**
 * MongoDBAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionAdapterException;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use Throwable;

use function class_exists;
use function current;
use function time;

/**
 * Stores sessions in MongoDB through the `ext-mongodb` driver.
 *
 * Each session is one document keyed by `_id`, with a `data` payload and a
 * `timestamp` (Unix time) used by {@see gc()} to expire stale sessions.
 *
 * Supported options:
 *  - `dsn`        (string, required) MongoDB connection string.
 *  - `collection` (string, required) Namespace as `database.collection`.
 *                 The legacy `collation` key is accepted as an alias.
 */
class MongoDBAdapter extends AbstractAdapter
{
    private Manager $manager;

    private string $collection;

    /**
     * @param array<string, mixed> $options
     * @throws SessionNotSupportedAdapter When the mongodb extension is missing.
     * @throws SessionInvalidArgumentException When required options are missing.
     * @throws SessionAdapterException When the connection cannot be opened.
     */
    public function __construct(array $options)
    {
        if (
            !\extension_loaded('mongodb')
            || !class_exists(Manager::class)
            || !class_exists(BulkWrite::class)
            || !class_exists(Query::class)
        ) {
            throw new SessionNotSupportedAdapter('The MongoDBAdapter requires the "mongodb" PHP extension.');
        }

        if (!isset($options['dsn'])) {
            throw new SessionInvalidArgumentException('The "dsn" option is required.');
        }

        $collection = $options['collection'] ?? $options['collation'] ?? null;
        if ($collection === null) {
            throw new SessionInvalidArgumentException('The "collection" option (database.collection) is required.');
        }
        $this->collection = (string) $collection;

        try {
            $this->manager = new Manager((string) $options['dsn']);
        } catch (Throwable $e) {
            throw new SessionAdapterException('MongoDB connection failed. ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function read(string $id): string|false
    {
        try {
            $query = new Query(['_id' => $id]);
            $rows = $this->manager->executeQuery($this->collection, $query)->toArray();
            if ($rows === []) {
                return '';
            }

            $first = current($rows);

            return isset($first->data) ? (string) $first->data : '';
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $bulkWrite = new BulkWrite();
            $bulkWrite->update(
                ['_id' => $id],
                ['$set' => ['data' => $data, 'timestamp' => time()]],
                ['upsert' => true],
            );

            return $this->manager->executeBulkWrite($this->collection, $bulkWrite)->isAcknowledged();
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $bulkWrite = new BulkWrite();
            $bulkWrite->delete(['_id' => $id], ['limit' => 1]);

            return $this->manager->executeBulkWrite($this->collection, $bulkWrite)->isAcknowledged();
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $bulkWrite = new BulkWrite();
            $bulkWrite->delete(['timestamp' => ['$lt' => time() - $max_lifetime]]);

            $result = $this->manager->executeBulkWrite($this->collection, $bulkWrite);

            return $result->getDeletedCount() ?? 0;
        } catch (Throwable) {
            return false;
        }
    }
}
