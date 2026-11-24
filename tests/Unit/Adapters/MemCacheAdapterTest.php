<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Tests\Support\ExtensionDouble;
use InitPHP\Sessions\Tests\Support\FakeMemcached;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Runs against an in-memory fake of `\Memcached` (see {@see ExtensionDouble});
 * this has not been exercised against a live Memcached server, and the legacy
 * `Memcache` driver path is not covered.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MemCacheAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        ExtensionDouble::install($this, 'memcached', 'Memcached', FakeMemcached::class);
    }

    private function adapter(): MemCacheAdapter
    {
        return new MemCacheAdapter(['prefix' => 'p:', 'ttl' => 100]);
    }

    /**
     * The client the adapter created lazily on its first operation.
     */
    private function client(MemCacheAdapter $adapter): FakeMemcached
    {
        $client = (new ReflectionProperty($adapter, 'memcache'))->getValue($adapter);
        self::assertInstanceOf(FakeMemcached::class, $client);

        return $client;
    }

    public function testValidateIdIsFalseForUnknownSessions(): void
    {
        self::assertFalse($this->adapter()->validateId('missing'));
    }

    public function testValidateIdIsTrueOnceTheSessionIsWritten(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');

        self::assertTrue($adapter->validateId('sid'));
    }

    public function testValidateIdIsFalseAfterTheItemExpired(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');

        $this->client($adapter)->advance(100);

        self::assertFalse($adapter->validateId('sid'));
    }

    public function testUpdateTimestampRenewsTheExpiry(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');
        $client = $this->client($adapter);

        $client->advance(60);
        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));
        $client->advance(60);

        self::assertTrue($adapter->validateId('sid'), 'the renewed session must outlive its original expiry');
        self::assertSame('a|i:1;', $adapter->read('sid'));
    }

    public function testUpdateTimestampStoresTheDataAgainWhenTheItemVanished(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertSame('a|i:1;', $adapter->read('sid'));
    }
}
