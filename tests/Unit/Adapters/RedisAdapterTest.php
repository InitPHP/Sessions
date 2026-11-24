<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Tests\Support\WorkerRunner;
use PHPUnit\Framework\TestCase;

/**
 * RedisAdapter against an in-memory fake of `\Redis`. The scenarios run in a
 * `php -n` child process (see {@see WorkerRunner}) so they execute on every
 * machine, including those with ext-redis. Not exercised against a live server.
 */
final class RedisAdapterTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function observed(): array
    {
        return WorkerRunner::observe('redis');
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

    public function testValidateIdIsFalseAfterTheKeyExpiredOrWasDestroyed(): void
    {
        self::assertFalse($this->observed()['validate_after_expiry']);
        self::assertFalse($this->observed()['validate_after_destroy']);
    }

    public function testUpdateTimestampRenewsTheTtlWithoutRewritingTheData(): void
    {
        $o = $this->observed();

        self::assertSame(40, $o['ttl_before_renewal']);
        self::assertTrue($o['renewal_result']);
        self::assertSame(100, $o['ttl_after_renewal']);
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

    public function testAStoreFailureDoesNotRejectTheId(): void
    {
        $o = $this->observed();

        self::assertTrue($o['failure_validate']);
        self::assertFalse($o['failure_read']);
        self::assertFalse($o['failure_update']);
    }

    public function testAKeyWithoutExpiryIsKeptAsIs(): void
    {
        $o = $this->observed();

        self::assertTrue($o['no_expiry_update_result']);
        self::assertNull($o['no_expiry_ttl']);
        self::assertTrue($o['no_expiry_validate']);
    }
}
