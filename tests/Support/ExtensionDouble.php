<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use PHPUnit\Framework\TestCase;

use function class_alias;
use function class_exists;
use function extension_loaded;

/**
 * Lets a test run an adapter against an in-memory fake of an optional PHP
 * extension class (Redis, Memcached, MongoDB\Driver\Manager, ...) when the
 * extension is not installed, which is the case on the CI runners.
 *
 * The fake is registered under the extension's real class name. A test that
 * uses it must run in a separate process so the alias never leaks into other
 * tests, and it is skipped when the genuine extension is loaded.
 */
final class ExtensionDouble
{
    /**
     * @param class-string $fake The fake implementation.
     */
    public static function install(TestCase $test, string $extension, string $realClass, string $fake): void
    {
        if (extension_loaded($extension) || class_exists($realClass, false)) {
            $test::markTestSkipped('The real "' . $extension . '" extension is available; the in-memory fake is not used.');
        }

        class_alias($fake, $realClass);
    }
}
