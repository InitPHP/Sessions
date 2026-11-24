<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

use PHPUnit\Framework\Assert;

use function array_push;
use function dirname;
use function file_get_contents;
use function is_array;
use function is_resource;
use function json_decode;
use function json_encode;
use function proc_close;
use function proc_open;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

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
     * Runs the worker and returns its observations. The test fails when the
     * worker exits with a non-zero code (an uncaught error, even one raised
     * after it reported), and the worker itself turns warnings into errors.
     *
     * @param array<string, string> $ini Extra ini settings for the process.
     * @return array<string, mixed>
     */
    public static function observe(string $worker, array $ini = []): array
    {
        $key = $worker . '|' . json_encode($ini);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $command = [
            PHP_BINARY, '-n',
            // First, so that any later startup notice goes to stderr.
            '-d', 'display_errors=stderr',
            // Statically built sessions only warn that it is already loaded.
            '-d', 'extension=session',
            '-d', 'session.gc_maxlifetime=1440',
        ];
        foreach ($ini as $name => $value) {
            array_push($command, '-d', $name . '=' . $value);
        }
        $command[] = dirname(__DIR__) . '/Support/Worker/' . $worker . '.php';

        // Files rather than pipes: a chatty worker can never fill a pipe and
        // block both processes.
        $stdout = (string) tempnam(sys_get_temp_dir(), 'initphp-worker-out-');
        $stderr = (string) tempnam(sys_get_temp_dir(), 'initphp-worker-err-');

        try {
            $process = proc_open($command, [1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes);
            Assert::assertTrue(is_resource($process), 'Could not start the worker process.');
            $exitCode = proc_close($process);

            $output = (string) file_get_contents($stdout);
            $errors = (string) file_get_contents($stderr);
        } finally {
            @unlink($stdout);
            @unlink($stderr);
        }

        $decoded = json_decode($output, true);
        $context = "\nexit code: " . $exitCode . "\nstdout: " . $output . "\nstderr: " . $errors;

        Assert::assertSame(0, $exitCode, 'The "' . $worker . '" worker did not exit cleanly.' . $context);
        if (!is_array($decoded) || !isset($decoded['observations']) || !is_array($decoded['observations'])) {
            Assert::fail(
                'The "' . $worker . '" worker did not report.'
                . (is_array($decoded) && isset($decoded['error']) ? ' ' . (string) $decoded['error'] : '')
                . $context,
            );
        }

        /** @var array<string, mixed> $observations */
        $observations = $decoded['observations'];

        return self::$cache[$key] = $observations;
    }
}
