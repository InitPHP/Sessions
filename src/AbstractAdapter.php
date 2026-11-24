<?php

/**
 * AbstractAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions;

use InitPHP\Sessions\Interfaces\AdapterInterface;

/**
 * Base class for the bundled save handlers.
 *
 * It provides sensible no-op defaults for the parts of
 * {@see \SessionHandlerInterface} that most stores do not need to customise
 * ({@see open()}, {@see close()}, {@see gc()}), leaving subclasses to
 * implement only {@see read()}, {@see write()} and {@see destroy()}.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * Initialises the handler. Called by PHP when the session starts.
     *
     * @param string $path The save path passed to the handler.
     * @param string $name The session name.
     * @return bool True on success.
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Closes the handler. Called by PHP when the session is written and closed.
     *
     * @return bool True on success.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Garbage-collects expired sessions.
     *
     * The default implementation deletes nothing and reports a count of zero.
     * Stores with their own expiry mechanism (e.g. Redis/Memcached TTL) keep
     * this default; stores that need manual cleanup (e.g. file, PDO) override
     * it.
     *
     * @param int $max_lifetime Maximum session lifetime in seconds.
     * @return int|false The number of deleted sessions, or false on failure.
     */
    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    /**
     * Reads the serialized session payload for the given id.
     *
     * @param string $id The session id.
     * @return string|false The raw, PHP-session-encoded payload, an empty
     *                      string when the session does not exist, or false on
     *                      a read failure.
     */
    abstract public function read(string $id): string|false;

    /**
     * Persists the serialized session payload for the given id.
     *
     * @param string $id   The session id.
     * @param string $data The raw, PHP-session-encoded payload.
     * @return bool True on success.
     */
    abstract public function write(string $id, string $data): bool;

    /**
     * Destroys the session with the given id.
     *
     * @param string $id The session id.
     * @return bool True on success.
     */
    abstract public function destroy(string $id): bool;
}
