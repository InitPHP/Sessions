<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

final class FakeMongoQuery
{
    /**
     * @param array<string, mixed> $filter
     */
    public function __construct(public array $filter) {}
}
