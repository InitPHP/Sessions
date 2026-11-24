<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Tests\Support\WorkerRunner;
use PHPUnit\Framework\TestCase;

/**
 * MongoDBAdapter against in-memory fakes of the MongoDB driver classes, run in
 * a `php -n` child process (see {@see WorkerRunner}) so it executes on every
 * machine, including those with ext-mongodb. The constructor, which insists on
 * the real extension, is bypassed. Not exercised against a live server.
 */
final class MongoDBAdapterTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function observed(): array
    {
        return WorkerRunner::observe('mongodb');
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

    public function testValidateIdIgnoresDocumentsOlderThanTheSessionLifetime(): void
    {
        $o = $this->observed();

        self::assertSame(1440, $o['lifetime']);
        self::assertFalse($o['validate_after_expiry']);
    }

    public function testUpdateTimestampRenewsTheTimestampAndKeepsTheData(): void
    {
        $o = $this->observed();

        self::assertTrue($o['renewal_result']);
        self::assertTrue($o['timestamp_renewed']);
        self::assertSame('a|i:1;', $o['read_after_renewal']);
    }

    public function testUpdateTimestampDoesNotResurrectADestroyedSession(): void
    {
        $o = $this->observed();

        self::assertTrue($o['destroyed_update_result']);
        self::assertFalse($o['destroyed_validate']);
        self::assertSame('', $o['destroyed_read']);
        self::assertSame(0, $o['destroyed_documents']);
    }

    public function testAStoreFailureDoesNotRejectTheId(): void
    {
        $o = $this->observed();

        self::assertTrue($o['failure_validate']);
        self::assertFalse($o['failure_read']);
        self::assertFalse($o['failure_update']);
    }

    public function testAnIdThatIsNotValidUtf8IsNoSuchSessionAndNeverReachesTheDriver(): void
    {
        $o = $this->observed();

        self::assertFalse($o['bad_id_validate']);
        self::assertSame('', $o['bad_id_read']);
        self::assertTrue($o['bad_id_update']);
        self::assertFalse($o['bad_id_write']);
        self::assertTrue($o['bad_id_destroy']);
        self::assertSame(0, $o['bad_id_commands']);
    }
}
