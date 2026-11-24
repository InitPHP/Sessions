<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Tests\Support\WorkerRunner;
use PHPUnit\Framework\TestCase;

/**
 * MemCacheAdapter with the legacy `Memcache` driver, against an in-memory fake,
 * run in a `php -n` child process (see {@see WorkerRunner}). Not exercised
 * against a live server.
 */
final class LegacyMemcacheAdapterTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function observed(): array
    {
        return WorkerRunner::observe('memcache');
    }

    public function testTheScenariosRanAgainstTheFakeNotTheRealExtension(): void
    {
        self::assertFalse($this->observed()['real_extension_loaded']);
    }

    public function testValidateId(): void
    {
        $o = $this->observed();

        self::assertFalse($o['validate_unknown']);
        self::assertTrue($o['validate_after_write']);
        self::assertFalse($o['validate_after_expiry']);
    }

    public function testUpdateTimestampRenewsTheExpiryOfAnExistingItem(): void
    {
        $o = $this->observed();

        self::assertTrue($o['renewal_result']);
        self::assertTrue($o['validate_after_renewal']);
    }

    public function testUpdateTimestampDoesNotResurrectADestroyedSession(): void
    {
        $o = $this->observed();

        self::assertTrue($o['destroyed_update_result']);
        self::assertFalse($o['destroyed_validate']);
        self::assertSame('', $o['destroyed_read']);
    }
}
