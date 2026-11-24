<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

final class FakeMongoCursor
{
    /**
     * @param list<object> $rows
     */
    public function __construct(private array $rows) {}

    /**
     * @return list<object>
     */
    public function toArray(): array
    {
        return $this->rows;
    }
}
