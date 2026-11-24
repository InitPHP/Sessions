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
use InitPHP\Sessions\Internal\GeneratesSessionIds;
use InitPHP\Sessions\Internal\ResolvesSessionLifetime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;
use Throwable;

use function class_exists;
use function current;
use function preg_match;
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
 *
 * With `session.use_strict_mode` on, a document counts as an existing session
 * only while its `timestamp` is younger than `session.gc_maxlifetime`;
 * {@see updateTimestamp()} renews that timestamp and never creates a document.
 * A driver error is not taken as proof that the session does not exist.
 */
class MongoDBAdapter extends AbstractAdapter implements SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    use GeneratesSessionIds;
    use ResolvesSessionLifetime;

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

    public function validateId(string $id): bool
    {
        if (!$this->isStorable($id)) {
            return false;
        }

        try {
            $query = new Query([
                '_id'       => $id,
                'timestamp' => ['$gte' => time() - $this->resolveSessionLifetime()],
            ]);

            return $this->manager->executeQuery($this->collection, $query)->toArray() !== [];
        } catch (Throwable) {
            // The store failed: do not reject the id (see PDOAdapter).
            return true;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        if (!$this->isStorable($id)) {
            return true;
        }

        try {
            // No upsert: a document that is gone must stay gone.
            $bulkWrite = new BulkWrite();
            $bulkWrite->update(['_id' => $id], ['$set' => ['timestamp' => time()]]);

            return $this->manager->executeBulkWrite($this->collection, $bulkWrite)->isAcknowledged();
        } catch (Throwable) {
            return false;
        }
    }

    public function read(string $id): string|false
    {
        if (!$this->isStorable($id)) {
            return '';
        }

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
        if (!$this->isStorable($id)) {
            return false;
        }

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
        if (!$this->isStorable($id)) {
            return true;
        }

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

    /**
     * Whether the id can be used as a BSON string `_id`. The driver throws on
     * invalid UTF-8; since that id comes from the client, it must read as "no
     * such session" instead of failing every request of the user.
     */
    private function isStorable(string $id): bool
    {
        return preg_match('//u', $id) === 1;
    }
}
