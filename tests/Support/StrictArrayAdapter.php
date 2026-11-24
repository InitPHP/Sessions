<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use SessionUpdateTimestampHandlerInterface;

/**
 * The recipe from docs/Custom-Adapters.md: a custom adapter that opts in to
 * strict mode and lazy write by implementing the interface itself.
 */
final class StrictArrayAdapter extends \InitPHP\Sessions\AbstractAdapter implements SessionUpdateTimestampHandlerInterface
{
    /** @var array<string, string> */
    public array $store = [];

    public function validateId(string $id): bool
    {
        return isset($this->store[$id]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        return $this->store[$id] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $this->store[$id] = $data;

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->store[$id]);

        return true;
    }
}
