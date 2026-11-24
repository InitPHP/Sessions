<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Tests\Support\ExtensionDouble;
use InitPHP\Sessions\Tests\Support\FakeMemcache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The legacy `Memcache` driver (only installed when `Memcached` is absent),
 * against an in-memory fake; not exercised against a live server.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LegacyMemcacheAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        ExtensionDouble::install($this, 'memcache', 'Memcache', FakeMemcache::class);
    }

    private function adapter(): MemCacheAdapter
    {
        return new MemCacheAdapter(['prefix' => 'p:', 'ttl' => 100]);
    }

    private function client(MemCacheAdapter $adapter): FakeMemcache
    {
        $client = (new ReflectionProperty($adapter, 'memcache'))->getValue($adapter);
        self::assertInstanceOf(FakeMemcache::class, $client);

        return $client;
    }

    public function testValidateId(): void
    {
        $adapter = $this->adapter();
        self::assertFalse($adapter->validateId('sid'));

        $adapter->write('sid', 'a|i:1;');
        self::assertTrue($adapter->validateId('sid'));

        $this->client($adapter)->advance(100);
        self::assertFalse($adapter->validateId('sid'));
    }

    public function testUpdateTimestampRenewsTheExpiryOfAnExistingItem(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');
        $client = $this->client($adapter);

        $client->advance(60);
        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));
        $client->advance(60);

        self::assertTrue($adapter->validateId('sid'));
    }

    public function testUpdateTimestampDoesNotResurrectADestroyedSession(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'u|s:5:"admin";');
        $adapter->destroy('sid');

        self::assertTrue($adapter->updateTimestamp('sid', 'u|s:5:"admin";'));

        self::assertFalse($adapter->validateId('sid'));
        self::assertSame('', $adapter->read('sid'));
    }
}
