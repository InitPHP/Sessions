<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Exceptions;

use Exception;
use InitPHP\Sessions\Exceptions\SessionAdapterException;
use InitPHP\Sessions\Exceptions\SessionException;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionHierarchyTest extends TestCase
{
    public function testSessionExceptionIsAnException(): void
    {
        self::assertInstanceOf(Exception::class, new SessionException('boom'));
    }

    public function testAdapterExceptionExtendsSessionException(): void
    {
        $exception = new SessionAdapterException('boom');

        self::assertInstanceOf(SessionException::class, $exception);
    }

    public function testAdapterExceptionIsCatchableAsSessionException(): void
    {
        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('store down');

        throw new SessionAdapterException('store down');
    }

    public function testInvalidArgumentExceptionExtendsSplInvalidArgument(): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, new SessionInvalidArgumentException('bad'));
    }

    public function testNotSupportedAdapterIsRuntimeExceptionWithDefaultMessage(): void
    {
        $exception = new SessionNotSupportedAdapter();

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertNotSame('', $exception->getMessage());
    }

    public function testCarriesPreviousException(): void
    {
        $previous = new RuntimeException('root cause');
        $exception = new SessionAdapterException('wrapper', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
