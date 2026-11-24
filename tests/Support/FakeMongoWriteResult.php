<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

final class FakeMongoWriteResult
{
    public function __construct(private int $matched, private int $deleted) {}

    public function isAcknowledged(): bool
    {
        return true;
    }

    public function getMatchedCount(): int
    {
        return $this->matched;
    }

    public function getDeletedCount(): int
    {
        return $this->deleted;
    }
}
