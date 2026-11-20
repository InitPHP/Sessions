<?php

/**
 * Manager.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Classes;

use InitPHP\Sessions\Exceptions\SessionException;
use SessionHandlerInterface;
use Throwable;

/**
 * Wraps PHP's native session lifecycle functions behind a small, fluent,
 * exception-aware API.
 *
 * Every native call is guarded so that low-level failures surface as a
 * {@see SessionException} rather than a bare warning or false return.
 */
class Manager
{
    private ?SessionHandlerInterface $handler;

    /**
     * @param SessionHandlerInterface|null $handler Optional custom save
     *        handler registered on {@see start()}. When null, PHP's configured
     *        default handler is used.
     */
    public function __construct(?SessionHandlerInterface $handler = null)
    {
        $this->handler = $handler;
    }

    /**
     * Determines whether a session is currently active.
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Returns the current session name.
     *
     * @return string
     */
    public function getName(): string
    {
        $name = session_name();

        return $name === false ? '' : $name;
    }

    /**
     * Sets the session name. Must be called before {@see start()}.
     *
     * @param string $name The new session name.
     * @return $this
     * @throws SessionException
     */
    public function setName(string $name): self
    {
        try {
            session_name($name);
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Registers the custom handler (if any) and starts the session.
     *
     * @param array<string, mixed> $options Options forwarded to
     *        {@see session_start()}.
     * @return bool True when the session was started.
     * @throws SessionException
     */
    public function start(array $options = []): bool
    {
        try {
            if ($this->handler !== null) {
                session_set_save_handler($this->handler, true);
            }

            return session_start($options);
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Destroys all data registered to the current session.
     *
     * @return bool
     * @throws SessionException
     */
    public function destroy(): bool
    {
        try {
            return session_destroy();
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Replaces the current session id with a freshly generated one.
     *
     * @param bool $deleteOldSession Whether to delete the old session data.
     * @return bool
     * @throws SessionException
     */
    public function regenerateId(bool $deleteOldSession = false): bool
    {
        try {
            return session_regenerate_id($deleteOldSession);
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Returns the current session id.
     *
     * @return string The session id, or an empty string when there is none.
     * @throws SessionException
     */
    public function getID(): string
    {
        try {
            $id = session_id();

            return $id === false ? '' : $id;
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Sets the session id. Must be called before {@see start()}.
     *
     * @param string $sessionId The session id to use.
     * @return bool True when the id was accepted.
     * @throws SessionException
     */
    public function setID(string $sessionId): bool
    {
        try {
            return session_id($sessionId) === $sessionId;
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Frees all session variables. The session must be active.
     *
     * @return bool
     * @throws SessionException
     */
    public function flush(): bool
    {
        $this->sessionStartedCheck('flush');

        return $this->unset();
    }

    /**
     * Frees all session variables. The session must be active.
     *
     * @return bool
     * @throws SessionException
     */
    public function unset(): bool
    {
        $this->sessionStartedCheck('unset');

        try {
            return session_unset() !== false;
        } catch (Throwable $e) {
            throw new SessionException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Guards methods that require an active session.
     *
     * @param string $method The calling method's name, used in the message.
     * @throws SessionException When no session is active.
     */
    protected function sessionStartedCheck(string $method): void
    {
        if ($this->isStarted() === false) {
            throw new SessionException('The session must be started for the "' . $method . '()" method to work.');
        }
    }
}
