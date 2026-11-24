<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Interfaces\AdapterInterface;
use InitPHP\Sessions\Tests\Support\ArrayAdapter;
use PHPUnit\Framework\TestCase;
use SessionUpdateTimestampHandlerInterface;

final class AbstractAdapterTest extends TestCase
{
    private function legacyAdapter(): ArrayAdapter
    {
        return new ArrayAdapter();
    }

    public function testAdaptersAreUpdateTimestampHandlersAndStillAdapters(): void
    {
        $adapter = $this->legacyAdapter();

        self::assertInstanceOf(SessionUpdateTimestampHandlerInterface::class, $adapter);
        self::assertInstanceOf(AdapterInterface::class, $adapter);
    }

    public function testDefaultValidateIdRejectsIdsWithoutStoredData(): void
    {
        $adapter = $this->legacyAdapter();

        self::assertFalse($adapter->validateId('unknown'));
    }

    public function testDefaultValidateIdAcceptsIdsWithStoredData(): void
    {
        $adapter = $this->legacyAdapter();
        $adapter->write('known', 'a|i:1;');

        self::assertTrue($adapter->validateId('known'));
    }

    public function testDefaultValidateIdRejectsIdsWhoseReadFails(): void
    {
        $adapter = new class extends AbstractAdapter {
            public function read(string $id): string|false
            {
                return false;
            }

            public function write(string $id, string $data): bool
            {
                return true;
            }

            public function destroy(string $id): bool
            {
                return true;
            }
        };

        self::assertFalse($adapter->validateId('sid'));
    }

    public function testDefaultUpdateTimestampWritesTheDataAgain(): void
    {
        $adapter = $this->legacyAdapter();

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertSame(1, $adapter->writes);
        self::assertSame('a|i:1;', $adapter->read('sid'));
    }
}
