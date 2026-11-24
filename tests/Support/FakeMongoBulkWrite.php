<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

final class FakeMongoBulkWrite
{
    /** @var list<array{0: string, 1: array<string, mixed>, 2: array<string, mixed>, 3: array<string, mixed>}> */
    public array $operations = [];

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $newObj
     * @param array<string, mixed> $options
     */
    public function update(array $filter, array $newObj, array $options = []): void
    {
        $this->operations[] = ['update', $filter, $newObj, $options];
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function delete(array $filter, array $options = []): void
    {
        $this->operations[] = ['delete', $filter, [], $options];
    }
}
