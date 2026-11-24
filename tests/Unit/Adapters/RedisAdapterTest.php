<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\RedisAdapter;
use InitPHP\Sessions\Tests\Support\ExtensionDouble;
use InitPHP\Sessions\Tests\Support\FakeRedis;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Runs against an in-memory fake of `\Redis` (see {@see ExtensionDouble});
 * this has not been exercised against a live Redis server.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RedisAdapterTest extends TestCase
{
    private FakeRedis $redis;

    protected function setUp(): void
    {
        ExtensionDouble::install($this, 'redis', 'Redis', FakeRedis::class);
        $this->redis = new FakeRedis();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function adapter(array $options = []): RedisAdapter
    {
        return new RedisAdapter($options + ['redis' => $this->redis, 'prefix' => 'p:', 'ttl' => 100]);
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

    public function testValidateIdIsFalseAfterTheKeyExpired(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');

        $this->redis->advance(100);

        self::assertFalse($adapter->validateId('sid'));
    }

    public function testValidateIdIsFalseAfterDestroy(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');
        $adapter->destroy('sid');

        self::assertFalse($adapter->validateId('sid'));
    }

    public function testUpdateTimestampRenewsTheTtlWithoutRewritingTheData(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');
        $this->redis->advance(60);
        self::assertSame(40, $this->redis->ttlOf('p:sid'));

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertSame(100, $this->redis->ttlOf('p:sid'));
        $this->redis->advance(60);
        self::assertTrue($adapter->validateId('sid'), 'the renewed session must outlive its original expiry');
        self::assertSame('a|i:1;', $adapter->read('sid'));
    }

    public function testUpdateTimestampStoresTheDataAgainWhenTheKeyVanished(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertSame('a|i:1;', $adapter->read('sid'));
        self::assertSame(100, $this->redis->ttlOf('p:sid'));
    }

    public function testUpdateTimestampKeepsAKeyThatHasNoExpiry(): void
    {
        $adapter = $this->adapter(['ttl' => 0]);
        $adapter->write('sid', 'a|i:1;');

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertNull($this->redis->ttlOf('p:sid'));
        self::assertTrue($adapter->validateId('sid'));
    }
}
