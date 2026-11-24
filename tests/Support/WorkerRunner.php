<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use PHPUnit\Framework\Assert;

use function dirname;
use function is_array;
use function is_resource;
use function json_decode;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_BINARY;

/**
 * Runs one of the scripts in `tests/Support/Worker` in a bare `php -n` process
 * and returns what it observed.
 *
 * The adapters for Redis, Memcached, Memcache and MongoDB are tested against
 * in-memory fakes that take the real class names. That is only possible where
 * the extension is not loaded, and CI runners ship those extensions, so the
 * code runs in a child process started without any php.ini or shared extension
 * (only `session`, which the adapters' parent interface needs, is loaded back).
 * The tests therefore run, and never skip, whatever the host has installed.
 */
final class WorkerRunner
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public static function observe(string $worker): array
    {
        if (isset(self::$cache[$worker])) {
            return self::$cache[$worker];
        }

        $script = dirname(__DIR__) . '/Support/Worker/' . $worker . '.php';
        $process = proc_open(
            [
                PHP_BINARY, '-n',
                // Statically built sessions only warn that it is already loaded.
                '-d', 'extension=session',
                '-d', 'display_errors=stderr',
                '-d', 'session.gc_maxlifetime=1440',
                $script,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        Assert::assertTrue(is_resource($process), 'Could not start the worker process.');

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        $decoded = json_decode($stdout, true, 512, 0);
        if (!is_array($decoded) || !isset($decoded['observations']) || !is_array($decoded['observations'])) {
            Assert::fail(
                'The "' . $worker . '" worker did not report (exit code ' . $exitCode . ').'
                . (is_array($decoded) && isset($decoded['error']) ? ' ' . (string) $decoded['error'] : '')
                . "\nstdout: " . $stdout . "\nstderr: " . $stderr,
            );
        }

        /** @var array<string, mixed> $observations */
        $observations = $decoded['observations'];

        return self::$cache[$worker] = $observations;
    }
}
