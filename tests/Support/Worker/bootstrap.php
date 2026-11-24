<?php

/**
 * Bootstrap for the extension-less worker scripts (see WorkerRunner).
 *
 * The workers run in a bare `php -n` process, so the in-memory fakes of the
 * Redis, Memcached, Memcache and MongoDB classes can take the real class names
 * even on machines (such as the CI runners) where those extensions are
 * installed for the main PHPUnit process.
 */

declare(strict_types=1);

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

/**
 * Registers an in-memory fake under the name of an extension class.
 *
 * @param class-string $fake
 */
function installDouble(string $extension, string $realClass, string $fake): void
{
    if (\extension_loaded($extension) || class_exists($realClass, false)) {
        throw new RuntimeException('The "' . $extension . '" extension is loaded in the worker; it must run without it.');
    }

    class_alias($fake, $realClass);
}

/**
 * Runs the scenarios and prints their observations as JSON for the test.
 *
 * @param callable(): array<string, mixed> $scenarios
 */
function report(callable $scenarios): never
{
    try {
        $result = ['observations' => $scenarios()];
    } catch (Throwable $e) {
        $result = ['error' => $e::class . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()];
    }

    echo json_encode($result, JSON_THROW_ON_ERROR);

    exit(0);
}
