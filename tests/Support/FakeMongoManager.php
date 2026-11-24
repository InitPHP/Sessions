<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

/**
 * In-memory stand-in for `MongoDB\Driver\Manager`, understanding exactly the
 * filters and updates MongoDBAdapter issues: `_id` equality, `timestamp`
 * `$gte` / `$lt`, and `$set` updates with optional upsert.
 */
final class FakeMongoManager
{
    /** @var array<string, array<string, mixed>> documents keyed by `_id` */
    public array $documents = [];

    public function executeQuery(string $namespace, FakeMongoQuery $query): FakeMongoCursor
    {
        $rows = [];
        foreach ($this->documents as $document) {
            if ($this->matches($document, $query->filter)) {
                $rows[] = (object) $document;
            }
        }

        return new FakeMongoCursor($rows);
    }

    public function executeBulkWrite(string $namespace, FakeMongoBulkWrite $bulk): FakeMongoWriteResult
    {
        $matched = 0;
        $deleted = 0;

        foreach ($bulk->operations as $operation) {
            [$type, $filter, $payload, $options] = $operation;

            $hits = array_keys(array_filter(
                $this->documents,
                fn(array $document): bool => $this->matches($document, $filter),
            ));

            if ($type === 'delete') {
                foreach (($options['limit'] ?? 0) === 1 ? array_slice($hits, 0, 1) : $hits as $id) {
                    unset($this->documents[$id]);
                    ++$deleted;
                }
                continue;
            }

            if ($hits === [] && ($options['upsert'] ?? false) === true) {
                $this->documents[(string) $filter['_id']] = ['_id' => $filter['_id']] + $payload['$set'];
                continue;
            }

            foreach ($hits as $id) {
                $this->documents[$id] = $payload['$set'] + $this->documents[$id];
                ++$matched;
            }
        }

        return new FakeMongoWriteResult($matched, $deleted);
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $filter
     */
    private function matches(array $document, array $filter): bool
    {
        foreach ($filter as $field => $condition) {
            $value = $document[$field] ?? null;

            if (\is_array($condition)) {
                foreach ($condition as $operator => $operand) {
                    $ok = match ($operator) {
                        '$gte'  => $value !== null && $value >= $operand,
                        '$lt'   => $value !== null && $value < $operand,
                        default => throw new \LogicException('Unsupported operator ' . $operator),
                    };
                    if (!$ok) {
                        return false;
                    }
                }
            } elseif ($value !== $condition) {
                return false;
            }
        }

        return true;
    }
}
