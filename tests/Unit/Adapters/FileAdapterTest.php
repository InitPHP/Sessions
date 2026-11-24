<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileAdapterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/initphp-sessions-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function adapter(array $options = []): FileAdapter
    {
        return new FileAdapter(['path' => $this->dir] + $options);
    }

    public function testConstructorRejectsMissingPath(): void
    {
        $this->expectException(SessionInvalidArgumentException::class);

        new FileAdapter([]);
    }

    public function testConstructorRejectsNonWritableDirectory(): void
    {
        $this->expectException(SessionInvalidArgumentException::class);

        new FileAdapter(['path' => $this->dir . '/does-not-exist']);
    }

    public function testReadReturnsEmptyStringForUnknownSession(): void
    {
        self::assertSame('', $this->adapter()->read('unknown'));
    }

    public function testWriteThenReadRoundTrips(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->write('abc', 'payload'));
        self::assertSame('payload', $adapter->read('abc'));
    }

    public function testWriteUsesPrefixedFilename(): void
    {
        $this->adapter(['prefix' => 'pfx_'])->write('abc', 'data');

        self::assertFileExists($this->dir . '/pfx_abc');
    }

    public function testDestroyRemovesFileAndIsIdempotent(): void
    {
        $adapter = $this->adapter();
        $adapter->write('abc', 'data');

        self::assertTrue($adapter->destroy('abc'));
        self::assertFileDoesNotExist($this->dir . '/sess_abc');
        self::assertTrue($adapter->destroy('abc'));
    }

    public function testGarbageCollectionDeletesExpiredFilesOnly(): void
    {
        $adapter = $this->adapter();
        $adapter->write('fresh', 'data');
        $adapter->write('stale', 'data');

        touch($this->dir . '/sess_stale', time() - 5000);

        self::assertSame(1, $adapter->gc(1000));
        self::assertFileExists($this->dir . '/sess_fresh');
        self::assertFileDoesNotExist($this->dir . '/sess_stale');
    }

    public function testValidateIdIsFalseForUnknownSessions(): void
    {
        self::assertFalse($this->adapter()->validateId('missing'));
    }

    public function testValidateIdIsTrueForAFreshSession(): void
    {
        $adapter = $this->adapter();
        $adapter->write('abc', 'data');

        self::assertTrue($adapter->validateId('abc'));
    }

    public function testValidateIdIsFalseForASessionOlderThanTheLifetime(): void
    {
        $adapter = $this->adapter();
        $adapter->write('abc', 'data');

        touch($this->dir . '/sess_abc', time() - (int) ini_get('session.gc_maxlifetime') - 10);

        self::assertFalse($adapter->validateId('abc'));
    }

    public function testUpdateTimestampRenewsAnExistingSessionWithoutChangingItsData(): void
    {
        $adapter = $this->adapter();
        $adapter->write('abc', 'data');
        $stale = time() - (int) ini_get('session.gc_maxlifetime') + 60;
        touch($this->dir . '/sess_abc', $stale);
        clearstatcache();

        self::assertTrue($adapter->updateTimestamp('abc', 'data'));

        clearstatcache();
        self::assertGreaterThan($stale, (int) filemtime($this->dir . '/sess_abc'));
        self::assertSame('data', $adapter->read('abc'));
    }

    public function testUpdateTimestampNeverCreatesASession(): void
    {
        self::assertFalse($this->adapter()->updateTimestamp('ghost', 'data'));
        self::assertFileDoesNotExist($this->dir . '/sess_ghost');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeIds(): iterable
    {
        yield 'parent directory' => ['../outside'];
        yield 'nested traversal' => ['a/../../outside'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'backslash' => ['..\\outside'];
        yield 'dot dot' => ['..'];
        yield 'nul byte' => ["abc\0"];
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 257)];
    }

    #[DataProvider('unsafeIds')]
    public function testUnsafeIdsAreTreatedAsNoSessionAndNeverTouchTheFilesystem(string $id): void
    {
        $outside = dirname($this->dir) . '/outside';
        file_put_contents($outside, 'secret');

        try {
            // An empty prefix makes the id the whole file name, the worst case.
            $adapter = $this->adapter(['prefix' => '']);

            self::assertFalse($adapter->validateId($id));
            self::assertFalse($adapter->updateTimestamp($id, 'x'));
            self::assertSame('', $adapter->read($id));
            self::assertFalse($adapter->write($id, 'x'));
            self::assertTrue($adapter->destroy($id));

            self::assertSame('secret', file_get_contents($outside));
            self::assertSame([], glob($this->dir . '/*'));
        } finally {
            unlink($outside);
        }
    }

    public function testIdsWithTheCharactersPhpItselfIssuesAreAccepted(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->write('aZ09,-_', 'data'));
        self::assertTrue($adapter->validateId('aZ09,-_'));
    }
}
