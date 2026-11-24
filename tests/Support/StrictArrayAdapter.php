<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * The recipe from docs/Custom-Adapters.md: a custom adapter that opts in to
 * strict mode, lazy write and PHP 8.6's create_sid() by implementing the
 * interfaces itself.
 */
final class StrictArrayAdapter extends \InitPHP\Sessions\AbstractAdapter implements SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    /** @var array<string, string> */
    public array $store = [];

    public function create_sid(): string
    {
        return bin2hex(random_bytes(16));
    }

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
