<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit;

use InitPHP\Sessions\Classes\GetterSetter;
use InitPHP\Sessions\Classes\Manager;
use InitPHP\Sessions\Exceptions\SessionException;
use InitPHP\Sessions\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Session::createImmutable();
    }

    protected function tearDown(): void
    {
        unset($_SESSION);
    }

    public function testCreateImmutableReturnsInstanceForChaining(): void
    {
        // Regression: createImmutable() previously returned void, so the
        // documented `createImmutable()->start()` chaining fatally failed.
        self::assertInstanceOf(Session::class, Session::createImmutable());
    }

    public function testIsStartedIsRoutedToTheManager(): void
    {
        // Regression: the router mapped "isStart" instead of "isStarted", so
        // Session::isStarted() used to throw "method is not found".
        self::assertFalse(Session::isStarted());
    }

    public function testInstanceCallsAreRoutedThroughMagicCall(): void
    {
        $instance = Session::createImmutable();

        // Exercises __call (instance routing), which mirrors __callStatic.
        $method = 'isStarted';
        self::assertFalse($instance->{$method}());
    }

    public function testGetterSetterMethodsAreRoutedStatically(): void
    {
        Session::set('user', 'admin');

        self::assertTrue(Session::has('user'));
        self::assertSame('admin', Session::get('user'));
        self::assertSame('admin', Session::pull('user'));
        self::assertFalse(Session::has('user'));
    }

    public function testSetReturnsGetterSetterForChaining(): void
    {
        $result = Session::set('a', 1);

        self::assertInstanceOf(GetterSetter::class, $result);
        self::assertSame(2, $result->push('b', 2));
    }

    public function testMagicGetExposesCollaborators(): void
    {
        $instance = Session::createImmutable();

        self::assertInstanceOf(Manager::class, $instance->manager);
        self::assertInstanceOf(GetterSetter::class, $instance->session);
    }

    public function testMagicGetReturnsNullForUnknownProperty(): void
    {
        $instance = Session::createImmutable();

        $unknown = $this->propertyName();
        self::assertNull($instance->{$unknown});
    }

    private function propertyName(): string
    {
        // Built at runtime so static analysis does not treat it as a known
        // (and therefore undefined) property access.
        return 'unknown' . 'Property';
    }

    public function testUnknownMethodThrows(): void
    {
        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('"doesNotExist" method is not found.');

        $method = 'doesNotExist';
        Session::$method();
    }
}
