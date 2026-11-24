<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Tests\Support\WorkerRunner;
use PHPUnit\Framework\TestCase;

/**
 * MemCacheAdapter (Memcached driver) against an in-memory fake of `\Memcached`,
 * run in a `php -n` child process (see {@see WorkerRunner}) so it executes on
 * every machine, including those with ext-memcached. Not exercised against a
 * live server.
 */
final class MemCacheAdapterTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function observed(): array
    {
        return WorkerRunner::observe('memcached');
    }

    public function testTheScenariosRanAgainstTheFakeNotTheRealExtension(): void
    {
        self::assertFalse($this->observed()['real_extension_loaded']);
    }

    public function testValidateIdIsFalseForUnknownSessions(): void
    {
        self::assertFalse($this->observed()['validate_unknown']);
    }

    public function testValidateIdIsTrueOnceTheSessionIsWritten(): void
    {
        self::assertTrue($this->observed()['validate_after_write']);
    }

    public function testValidateIdIsFalseAfterTheItemExpired(): void
    {
        self::assertFalse($this->observed()['validate_after_expiry']);
    }

    public function testUpdateTimestampRenewsTheExpiry(): void
    {
        $o = $this->observed();

        self::assertTrue($o['renewal_result']);
        self::assertTrue($o['validate_after_renewal'], 'the renewed session must outlive its original expiry');
        self::assertSame('a|i:1;', $o['read_after_renewal']);
    }

    public function testUpdateTimestampDoesNotResurrectADestroyedSession(): void
    {
        $o = $this->observed();

        self::assertTrue($o['destroyed_update_result']);
        self::assertFalse($o['destroyed_validate']);
        self::assertSame('', $o['destroyed_read']);
    }

    public function testOnlyNotFoundCountsAsNoSuchSession(): void
    {
        $o = $this->observed();

        // A server error must neither reject the id nor read as an empty session.
        self::assertTrue($o['failure_validate']);
        self::assertFalse($o['failure_read']);
        self::assertFalse($o['failure_update']);
    }

    public function testAKeyMemcachedRefusesIsNoSuchSessionNotAStoreFailure(): void
    {
        $o = $this->observed();

        self::assertFalse($o['bad_key_validate']);
        self::assertSame('', $o['bad_key_read']);
        self::assertTrue($o['bad_key_update']);
    }
}
