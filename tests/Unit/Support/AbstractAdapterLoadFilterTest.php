<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Support;

use ErrorException;
use InitPHP\Sessions\Tests\Support\AbstractAdapterLoadFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AbstractAdapterLoadFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function expectedDiagnostics(): iterable
    {
        $format = 'Class InitPHP\Sessions\AbstractAdapter implementing SessionHandlerInterface '
            . 'is missing the %s() method which will be required in PHP 9.0';

        yield 'create_sid' => [E_WARNING, sprintf($format, 'create_sid')];
        yield 'validateId' => [E_WARNING, sprintf($format, 'validateId')];
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unexpectedDiagnostics(): iterable
    {
        yield 'another method' => [
            E_WARNING,
            'Class InitPHP\Sessions\AbstractAdapter implementing SessionHandlerInterface '
            . 'is missing the open() method which will be required in PHP 9.0',
        ];
        yield 'another class' => [
            E_WARNING,
            'Class App\MyAdapter implementing SessionHandlerInterface '
            . 'is missing the validateId() method which will be required in PHP 9.0',
        ];
        yield 'unrelated warning' => [E_WARNING, 'Undefined variable $x'];
        yield 'wrong severity' => [
            E_USER_WARNING,
            'Class InitPHP\Sessions\AbstractAdapter implementing SessionHandlerInterface '
            . 'is missing the validateId() method which will be required in PHP 9.0',
        ];
        yield 'deprecation' => [E_DEPRECATED, 'Something is deprecated'];
    }

    #[DataProvider('expectedDiagnostics')]
    public function testItAcceptsTheTwoExpectedLinkTimeWarnings(int $errno, string $message): void
    {
        $this->assertTrue(AbstractAdapterLoadFilter::isExpected($errno, $message));
    }

    #[DataProvider('unexpectedDiagnostics')]
    public function testItRejectsEverythingElse(int $errno, string $message): void
    {
        $this->assertFalse(AbstractAdapterLoadFilter::isExpected($errno, $message));
    }

    public function testLoadRestoresThePreviousErrorHandler(): void
    {
        $handler = static fn(): bool => false;
        set_error_handler($handler);

        try {
            AbstractAdapterLoadFilter::load();
            $current = set_error_handler(static fn(): bool => false);
            restore_error_handler();
        } finally {
            restore_error_handler();
        }

        $this->assertSame($handler, $current);
    }

    /**
     * Registers an autoloader that raises a genuine E_WARNING when $class is
     * requested, and returns it so the caller can unregister it.
     */
    private function warningAutoloader(string $class): callable
    {
        $autoloader = static function (string $name) use ($class): void {
            if ($name === $class) {
                /** @var array<string, int> $values */
                $values = [];
                // Raises an E_WARNING ("Undefined array key") while the class loads.
                $value = $values['missing'];
                unset($value);
            }
        };
        spl_autoload_register($autoloader, true, true);

        return $autoloader;
    }

    public function testLoadDoesNotSwallowAForeignWarningRaisedWhileTheClassLoads(): void
    {
        $class = 'InitPHP\\Sessions\\Tests\\Support\\NeverDefinedForTheFilterTest';
        $autoloader = $this->warningAutoloader($class);
        set_error_handler(null); // no previous handler, as in the PHPUnit bootstrap

        try {
            try {
                AbstractAdapterLoadFilter::load($class);
                $this->fail('A foreign warning raised while the class loads must not be swallowed.');
            } catch (ErrorException $e) {
                $this->assertStringContainsString('Undefined array key', $e->getMessage());
                $this->assertSame(E_WARNING, $e->getSeverity());
            }

            $this->assertNull(set_error_handler(static fn(): bool => false));
            restore_error_handler();
        } finally {
            restore_error_handler();
            spl_autoload_unregister($autoloader);
        }
    }

    public function testLoadPassesAForeignWarningToThePreviousHandler(): void
    {
        $class = 'InitPHP\\Sessions\\Tests\\Support\\NeverDefinedForTheChainTest';
        $autoloader = $this->warningAutoloader($class);
        $seen = [];
        $handler = static function (int $errno, string $errstr) use (&$seen): bool {
            $seen[] = [$errno, $errstr];

            return true;
        };
        set_error_handler($handler);

        try {
            AbstractAdapterLoadFilter::load($class);
        } finally {
            restore_error_handler();
            spl_autoload_unregister($autoloader);
        }

        $this->assertCount(1, $seen);
        $this->assertSame(E_WARNING, $seen[0][0]);
        $this->assertStringContainsString('Undefined array key', $seen[0][1]);
    }
}
