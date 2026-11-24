<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Adapters\CookieAdapter;
use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Adapters\MongoDBAdapter;
use InitPHP\Sessions\Adapters\PDOAdapter;
use InitPHP\Sessions\Adapters\RedisAdapter;
use InitPHP\Sessions\Interfaces\AdapterInterface;
use InitPHP\Sessions\Tests\Support\ArrayAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SessionUpdateTimestampHandlerInterface;

/**
 * `AbstractAdapter` itself is unchanged: strict mode is opt-in per adapter, so
 * a third-party subclass keeps exactly the methods and behaviour it had.
 */
final class AbstractAdapterTest extends TestCase
{
    public function testTheBaseClassDoesNotImplementTheUpdateTimestampInterface(): void
    {
        $adapter = new ArrayAdapter();

        self::assertInstanceOf(AdapterInterface::class, $adapter);
        self::assertNotInstanceOf(SessionUpdateTimestampHandlerInterface::class, $adapter);
        self::assertFalse(method_exists(AbstractAdapter::class, 'validateId'));
        self::assertFalse(method_exists(AbstractAdapter::class, 'updateTimestamp'));
    }

    public function testTheBaseClassAddsNoMethodsBeyondTheSessionHandlerContract(): void
    {
        $own = array_map(
            static fn(\ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(AbstractAdapter::class))->getMethods(),
        );
        sort($own);

        self::assertSame(['close', 'destroy', 'gc', 'open', 'read', 'write'], $own);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function bundledAdapters(): iterable
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
    #[DataProvider('bundledAdapters')]
    public function testEveryBundledAdapterImplementsTheInterface(string $class): void
    {
        self::assertTrue(is_subclass_of($class, SessionUpdateTimestampHandlerInterface::class));
        self::assertTrue(is_subclass_of($class, AbstractAdapter::class));
    }
}
