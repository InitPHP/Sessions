<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use InitPHP\Sessions\AbstractAdapter;

/**
 * A third-party style adapter: it implements only the three abstract methods
 * and knows nothing about strict mode.
 */
final class ArrayAdapter extends AbstractAdapter
{
    /** @var array<string, string> */
    public array $store = [];

    public int $writes = 0;

    public function read(string $id): string|false
    {
        return $this->store[$id] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        ++$this->writes;
        $this->store[$id] = $data;

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->store[$id]);

        return true;
    }
}
