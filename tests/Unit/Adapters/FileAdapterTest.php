<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
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
}
