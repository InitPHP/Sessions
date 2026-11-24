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

/*
 * Any warning, notice or deprecation raised while a scenario runs is an error
 * (the PHPUnit settings that do this do not reach this process). The one
 * exception: PHP 8.6 warns that AbstractAdapter, which this package must keep
 * unchanged, lacks create_sid() and validateId(); no other file may warn.
 */
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (str_ends_with($file, 'src' . DIRECTORY_SEPARATOR . 'AbstractAdapter.php') && str_contains($message, 'will be required in PHP 9.0')) {
        return true;
    }

    // Also remembered: the adapters catch Throwable, which could swallow the
    // exception below; report() fails the run when anything was recorded.
    workerErrors($message . ' at ' . $file . ':' . $line);

    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Records (when given a message) and returns the warnings raised so far.
 *
 * @return list<string>
 */
function workerErrors(?string $message = null): array
{
    static $errors = [];

    if ($message !== null) {
        $errors[] = $message;
    }

    return $errors;
}

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

    if (workerErrors() !== []) {
        $result = ['error' => 'Warnings were raised (possibly swallowed by an adapter): ' . implode('; ', workerErrors())];
    }

    echo json_encode($result, JSON_THROW_ON_ERROR);

    exit(0);
}
