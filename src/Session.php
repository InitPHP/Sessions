<?php
/**
 * Session.php
 *
 * This file is part of Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Sessions;

use InitPHP\Sessions\Exception\SessionInvalidArgumentException;
use \InitPHP\ParameterBag\{ParameterBagInterface, ParameterBag};
use InitPHP\Sessions\Exception\SessionException;

class Session implements SessionInterface
{

    /** @var ParameterBagInterface */
    protected $storage;

    public function __construct()
    {
        $this->storage = new ParameterBag(($_SESSION ?? []), ['isMulti' => false]);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        $name = \session_name();

        return \is_string($name) ? $name : '';
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): SessionInterface
    {
        try {
            \session_name($name);
            return $this;
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function isStarted(): bool
    {
        return \session_status() === \PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    public function start(array $options = []): bool
    {
        try {
            return \session_start($options);
        } catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function regenerateId(bool $deleteOldSession = false): bool
    {
        try {
            return (bool)\session_regenerate_id($deleteOldSession);
        } catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getID(): string
    {
        try {
            return (string)\session_id();
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function setID(string $sessionId): bool
    {
        try {
            return (\session_id($sessionId)) === $sessionId;
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        if($this->isStarted() === FALSE){
            return [];
        }
        $now = \time();
        $all = $this->storage->all();
        $sessions = []; $removes = [];
        foreach ($all as $key => $value) {
            if($value['ttl'] !== null && $value['ttl'] < $now){
                $removes[] = $key;
                continue;
            }
            $sessions[$key] = $value;
        }
        unset($all);
        if(!empty($removes)){
            $this->remove(...$removes);
            unset($removes);
        }
        return $sessions;
    }

    /**
     * @inheritDoc
     */
    public function destroy(): bool
    {
        try {
            return (bool)\session_destroy();
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function flush(): bool
    {
        $this->sessionStartedCheck('flush');
        try {
            if(($unset = (bool)\session_unset()) !== FALSE){
                $this->storage->clear();
            }
            return $unset;
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        if($this->isStarted() === FALSE){
            return false;
        }
        $get = $this->storage->get($key, null);
        if(!\is_array($get)){
            return false;
        }
        if(($has = ($get['ttl'] === null || $get['ttl'] > \time())) === FALSE){
            $this->remove($key);
        }
        return $has;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, $default = null)
    {
        if($this->isStarted() === FALSE){
            return $default;
        }
        $get = $this->storage->get($key, null);
        if(!\is_array($get)){
            return $default;
        }
        if($get['ttl'] !== null && $get['ttl'] > \time()){
            $this->remove($key);
            return $default;
        }
        return $get['value'];
    }

    /**
     * @inheritDoc
     */
    public function pull(string $key, $default = null)
    {
        if($this->isStarted() === FALSE){
            return $default;
        }
        $get = $this->storage->get($key, null);
        if(!\is_array($get)){
            return $default;
        }
        $this->remove($key);
        if($get['ttl'] !== null && $get['ttl'] > \time()){
            return $default;
        }
        return $get['value'];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value, ?int $ttl = null): SessionInterface
    {
        $this->sessionStartedCheck('set');
        $ttl = $this->ttlCheck($ttl);

        try {
            $key = \strtolower($key);
            $value = ['value' => $value, 'ttl' => $ttl];
            $this->storage->set($key, $value);
            $_SESSION[$key] = $value;
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function push(string $key, $value, ?int $ttl = null)
    {
        $this->sessionStartedCheck('push');
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function setAssoc(array $assoc, ?int $ttl = null): SessionInterface
    {
        $this->sessionStartedCheck('setAssoc');
        $ttl = $this->ttlCheck($ttl);

        $sessions = [];
        foreach ($assoc as $key => $value) {
            if(!\is_string($key)){
                throw new SessionInvalidArgumentException('Only associative array can be used to define collective session data.');
            }
            $key = \strtolower($key);
            $sessions[$key] = ['value' => $value, 'ttl' => $ttl];
        }

        try {
            foreach ($sessions as $key => $value) {
                $_SESSION[$key] = $value;
            }
            $this->storage->merge($sessions);
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function remove(string ...$key): SessionInterface
    {
        $this->sessionStartedCheck('remove');
        try {
            foreach ($key as $name) {
                $name = \strtolower($name);
                if(isset($_SESSION[$name])){
                    unset($_SESSION[$name]);
                }
            }
            $this->storage->remove(...$key);
        }catch (\Throwable $throwable) {
            throw new SessionException($throwable->getMessage());
        }

        return $this;
    }


    private function sessionStartedCheck(string $method): void
    {
        if($this->isStarted() === FALSE){
            throw new SessionException('The session must be started for the "' . $method . '()" method to work.');
        }
    }

    private function ttlCheck(?int $ttl): ?int
    {
        if($ttl === null){
            return null;
        }
        $ttl = (int)\abs($ttl);
        if($ttl === 0){
            throw new SessionInvalidArgumentException('The session data timeout can be NULL or a positive integer.');
        }
        return (int)($ttl + \time());
    }

}
