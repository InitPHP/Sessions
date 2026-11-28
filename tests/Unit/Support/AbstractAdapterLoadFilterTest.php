<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Support;

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
}
