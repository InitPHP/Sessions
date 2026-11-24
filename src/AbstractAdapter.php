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
use SessionUpdateTimestampHandlerInterface;

use function ini_get;

/**
 * Base class for the bundled save handlers.
 *
 * It provides sensible no-op defaults for the parts of
 * {@see \SessionHandlerInterface} that most stores do not need to customise
 * ({@see open()}, {@see close()}, {@see gc()}), leaving subclasses to
 * implement only {@see read()}, {@see write()} and {@see destroy()}.
 *
 * It also implements {@see SessionUpdateTimestampHandlerInterface}, which is
 * what makes `session.use_strict_mode` and `session.lazy_write` work with an
 * adapter: PHP asks {@see validateId()} whether an id sent by the client
 * belongs to a stored session, and calls {@see updateTimestamp()} instead of
 * {@see write()} when the session data did not change. The defaults below are
 * correct for any store (they are built on {@see read()} and {@see write()});
 * the bundled adapters override them with cheaper, store-specific versions.
 */
abstract class AbstractAdapter implements AdapterInterface, SessionUpdateTimestampHandlerInterface
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
     * Tells whether the given id belongs to a stored, unexpired session.
     *
     * PHP calls this at session start when `session.use_strict_mode` is on and
     * discards the id (issuing a fresh one) when it returns false. The default
     * implementation reads the session and treats an empty payload as "no such
     * session"; override it with a cheaper existence check when the store has
     * one.
     *
     * @param string $id The session id sent by the client.
     * @return bool True when a session with this id exists.
     */
    public function validateId(string $id): bool
    {
        $data = $this->read($id);

        return $data !== false && $data !== '';
    }

    /**
     * Refreshes the lifetime of a session whose data did not change.
     *
     * PHP calls this instead of {@see write()} when `session.lazy_write` is on
     * (the default) and the data is identical to what was read. The default
     * implementation simply writes the data again, which renews any expiry the
     * store tracks; override it when the store can renew the lifetime without
     * rewriting the payload.
     *
     * @param string $id   The session id.
     * @param string $data The unchanged, PHP-session-encoded payload.
     * @return bool True on success.
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    /**
     * Returns the session lifetime PHP uses for garbage collection
     * (`session.gc_maxlifetime`), for stores that expire sessions by age.
     *
     * @return int The lifetime in seconds.
     */
    protected function sessionLifetime(): int
    {
        $lifetime = ini_get('session.gc_maxlifetime');

        return $lifetime === false || $lifetime === '' ? 1440 : (int) $lifetime;
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
