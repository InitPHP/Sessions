<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\CookieAdapter;
use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Adapters\MongoDBAdapter;
use InitPHP\Sessions\Adapters\PDOAdapter;
use InitPHP\Sessions\Adapters\RedisAdapter;
use InitPHP\Sessions\Tests\Support\WorkerRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The bundled adapters implement `SessionIdInterface::create_sid()` (PHP 8.6
 * wants every session handler to) and must produce the ids PHP itself would,
 * for the configured `session.sid_length` and `session.sid_bits_per_character`.
 * Run in `php -n` workers (see {@see WorkerRunner}) so the ini settings can
 * differ per case.
 */
final class SessionIdGenerationTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string>, int, string, string}>
     */
    public static function configurations(): iterable
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,-';

        yield 'defaults (32 characters, 4 bits)' => [[], 32, '/^[0-9a-f]{32}$/', substr($alphabet, 0, 16)];
        yield '40 characters, 5 bits' => [
            ['session.sid_length' => '40', 'session.sid_bits_per_character' => '5'],
            40,
            '/^[0-9a-v]{40}$/',
            substr($alphabet, 0, 32),
        ];
        yield '48 characters, 6 bits' => [
            ['session.sid_length' => '48', 'session.sid_bits_per_character' => '6'],
            48,
            '/^[0-9a-zA-Z,-]{48}$/',
            $alphabet,
        ];
    }

    /**
     * @param array<string, string> $ini
     */
    #[DataProvider('configurations')]
    public function testIdsHaveTheShapePhpItselfProduces(array $ini, int $length, string $pattern, string $alphabet): void
    {
        $o = WorkerRunner::observe('ids', $ini);

        // PHP's own generator, under the same settings, is the reference.
        self::assertMatchesRegularExpression($pattern, $o['native']);
        self::assertSame($length, strlen($o['native']));

        $adapters = $o['adapters'];
        self::assertIsArray($adapters);
        self::assertCount(6, $adapters);

        foreach ($adapters as $class => $ids) {
            self::assertIsArray($ids);
            self::assertTrue($ids['implements_session_id_interface'], $class);
            self::assertMatchesRegularExpression($pattern, $ids['first'], $class);
            self::assertMatchesRegularExpression($pattern, $ids['second'], $class);
            self::assertNotSame($ids['first'], $ids['second'], $class . ' must not repeat ids');

            $this->assertIsRandom($ids['many'], $length, $alphabet, $class);
        }
    }

    /**
     * Guards against losing entropy (too few random bytes, a constant tail) or
     * ignoring the configured bits per character, which the pattern alone
     * cannot see: every position must vary widely and the whole alphabet must
     * show up. The thresholds are far below what 200 random ids produce
     * (about 16, 32 and 63 distinct characters per position), so a false alarm
     * is practically impossible.
     */
    private function assertIsRandom(mixed $ids, int $length, string $alphabet, string $class): void
    {
        self::assertIsArray($ids);
        self::assertCount(200, array_unique($ids), $class . ' repeated an id among 200');

        $seen = [];
        for ($position = 0; $position < $length; ++$position) {
            $column = [];
            foreach ($ids as $id) {
                self::assertIsString($id);
                $column[$id[$position]] = true;
                $seen[$id[$position]] = true;
            }
            self::assertGreaterThanOrEqual(
                intdiv(strlen($alphabet), 2),
                count($column),
                $class . ' position ' . $position . ' is not random enough',
            );
        }

        $missing = array_diff(str_split($alphabet), array_keys($seen));
        self::assertSame([], array_values($missing), $class . ' never produced some characters of the alphabet');
    }

    public function testTheBundledAdaptersRaiseNoSessionHandlerWarningOfTheirOwn(): void
    {
        $o = WorkerRunner::observe('warnings');

        $warnings = $o['warnings'];
        self::assertIsArray($warnings);

        // PHP 8.6 still warns about AbstractAdapter (unchanged by design);
        // nothing may be raised for the adapters themselves, nor on 8.2-8.5.
        foreach ($warnings as $warning) {
            self::assertIsString($warning);
            self::assertStringStartsWith('AbstractAdapter.php: ', $warning);
        }
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function adapters(): iterable
    {
        yield 'file' => [FileAdapter::class];
        yield 'pdo' => [PDOAdapter::class];
        yield 'redis' => [RedisAdapter::class];
        yield 'memcache' => [MemCacheAdapter::class];
        yield 'mongodb' => [MongoDBAdapter::class];
        yield 'cookie' => [CookieAdapter::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('adapters')]
    public function testEveryBundledAdapterImplementsSessionIdInterface(string $class): void
    {
        self::assertTrue(is_subclass_of($class, \SessionIdInterface::class));
    }
}
