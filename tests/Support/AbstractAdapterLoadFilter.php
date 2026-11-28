<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use ErrorException;
use InitPHP\Sessions\AbstractAdapter;

/**
 * Silences the two PHP 8.6 "will be required in PHP 9.0" warnings that PHP
 * raises while it links {@see AbstractAdapter}, and nothing else.
 *
 * `AbstractAdapter` deliberately implements only `SessionHandlerInterface`
 * (a public contract: third-party subclasses must keep working), so PHP 8.6
 * warns once per process that `create_sid()` and `validateId()` are missing.
 * The filter is installed only around the class load, then the previous
 * handler is restored: every other warning, including one raised by the same
 * class load, still fails the run.
 */
final class AbstractAdapterLoadFilter
{
    private const EXPECTED_MESSAGE = '/^Class InitPHP\\\\Sessions\\\\AbstractAdapter implementing '
        . 'SessionHandlerInterface is missing the (?:create_sid|validateId)\(\) method '
        . 'which will be required in PHP 9\.0$/';

    /**
     * Whether the diagnostic is one of the two expected link-time warnings.
     */
    public static function isExpected(int $errno, string $message): bool
    {
        return $errno === E_WARNING && preg_match(self::EXPECTED_MESSAGE, $message) === 1;
    }

    /**
     * Loads {@see AbstractAdapter} (or the given class) with the filter installed.
     *
     * @param class-string|string $class The class to load; defaults to AbstractAdapter.
     * @throws ErrorException On any diagnostic that is not an expected one.
     */
    public static function load(string $class = AbstractAdapter::class): void
    {
        $previous = set_error_handler(
            static function (int $errno, string $errstr, string $errfile, int $errline) use (&$previous): bool {
                if (self::isExpected($errno, $errstr)) {
                    return true;
                }
                if (is_callable($previous)) {
                    return (bool) $previous($errno, $errstr, $errfile, $errline);
                }

                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            },
        );

        try {
            class_exists($class);
        } finally {
            restore_error_handler();
        }
    }
}
