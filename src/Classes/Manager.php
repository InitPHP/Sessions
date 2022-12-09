<?php
/**
 * Manager.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions\Classes;

use InitPHP\Sessions\Exceptions\SessionException;

class Manager
{

    private \SessionHandlerInterface $handler;

    public function __construct(?\SessionHandlerInterface &$handler = null)
    {
        if($handler !== null){
            $this->handler = &$handler;
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function getName(): string
    {
        $name = session_name();

        return is_string($name) ? $name : '';
    }

    public function setName(string $name): self
    {
        try {
            session_name($name);
        } catch (\Throwable $e) {
            throw new SessionException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        return $this;
    }

    public function start(array $options = []): bool
    {
        try {
            if (isset($this->handler)) {
                session_set_save_handler($this->handler, true);
            }
            return session_start($options);
        } catch (\Throwable $e) {
            throw new SessionException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    public function destroy(): bool
    {
        try {
            return session_destroy();
        } catch (\Throwable $e) {
            throw new SessionException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    public function regenerateId(bool $deleteOldSession = false): bool
    {
        try {
            return (bool)session_regenerate_id($deleteOldSession);
        } catch (\Throwable $e) {
            throw new SessionException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    public function getID(): string
    {
        try {
            return (string)session_id();
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    public function setID(string $sessionId): bool
    {
        try {
            return (session_id($sessionId)) === $sessionId;
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    public function flush(): bool
    {
        $this->sessionStartedCheck('flush');
        return $this->unset();
    }

    public function unset(): bool
    {
        $this->sessionStartedCheck('unset');
        try {
            return session_unset() !== FALSE;
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }


    protected function sessionStartedCheck(string $method): void
    {
        if ($this->isStarted() === FALSE) {
            throw new SessionException('The session must be started for the "' . $method . '()" method to work.');
        }
    }

}
