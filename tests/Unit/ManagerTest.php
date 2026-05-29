<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit;

use InitPHP\Sessions\Classes\Manager;
use InitPHP\Sessions\Exceptions\SessionException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ManagerTest extends TestCase
{
    public function testIsStartedReturnsFalseWithoutActiveSession(): void
    {
        self::assertFalse((new Manager())->isStarted());
    }

    public function testSetNameIsFluentAndApplied(): void
    {
        $manager = new Manager();

        self::assertSame($manager, $manager->setName('UNITSESSID'));
        self::assertSame('UNITSESSID', $manager->getName());
    }

    public function testFlushThrowsWhenSessionNotStarted(): void
    {
        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('"flush()" method to work');

        (new Manager())->flush();
    }

    public function testUnsetThrowsWhenSessionNotStarted(): void
    {
        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('"unset()" method to work');

        (new Manager())->unset();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFullLifecycleWithDefaultHandler(): void
    {
        $manager = new Manager();

        self::assertTrue($manager->start(['use_cookies' => '0', 'cache_limiter' => '']));
        self::assertTrue($manager->isStarted());
        self::assertNotSame('', $manager->getID());

        $_SESSION['x'] = 1;
        self::assertTrue($manager->flush());
        self::assertSame([], $_SESSION);

        self::assertTrue($manager->destroy());
    }
}
