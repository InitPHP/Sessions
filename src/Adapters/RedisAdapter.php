<?php

/**
 * RedisAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionAdapterException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;
use InitPHP\Sessions\Internal\GeneratesSessionIds;
use Redis;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;
use Throwable;

/**
 * Stores sessions in Redis through the `ext-redis` extension.
 *
 * Provide connection details inline, or pass an already-configured `\Redis`
 * instance via the `redis` option to reuse an existing connection.
 *
 * Supported options:
 *  - `redis`    (\Redis)  An existing client. When given, the connection
 *                         options below are ignored.
 *  - `host`     (string)  Defaults to `127.0.0.1`.
 *  - `port`     (int)     Defaults to `6379`.
 *  - `timeout`  (float)   Connect timeout in seconds. Defaults to `0`.
 *  - `password` (string)  Optional AUTH password.
 *  - `database` (int)     Database index to SELECT. Defaults to `0`.
 *  - `ttl`      (int)     Key expiry in seconds. Defaults to `86400`.
 *  - `prefix`   (string)  Key prefix. Defaults to none.
 *
 * Redis drops expired keys itself, so a key that exists is a live session
 * ({@see validateId()}); {@see updateTimestamp()} renews the key's expiry and
 * never creates a key. A Redis error is not taken as proof that the session
 * does not exist.
 */
class RedisAdapter extends AbstractAdapter implements SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    use GeneratesSessionIds;

    private Redis $redis;

    private int $database;

    private int $ttl;

    private string $prefix;

    /**
     * @param array<string, mixed> $options
     * @throws SessionNotSupportedAdapter When the redis extension is missing.
     * @throws SessionAdapterException When the connection cannot be established.
     */
    public function __construct(array $options)
    {
        $this->ttl = (int) ($options['ttl'] ?? 86400);
        $this->database = (int) ($options['database'] ?? 0);
        $this->prefix = (string) ($options['prefix'] ?? '');

        if (isset($options['redis']) && $options['redis'] instanceof Redis) {
            $this->redis = $options['redis'];
        } else {
            if (!\extension_loaded('redis')) {
                throw new SessionNotSupportedAdapter('The RedisAdapter requires the "redis" PHP extension.');
            }

            try {
                $this->redis = new Redis();
                $connected = $this->redis->connect(
                    (string) ($options['host'] ?? '127.0.0.1'),
                    (int) ($options['port'] ?? 6379),
                    (float) ($options['timeout'] ?? 0),
                );
                if (!$connected) {
                    throw new SessionAdapterException('Redis connection failed.');
                }

                $password = $options['password'] ?? null;
                if ($password !== null && !$this->redis->auth($password)) {
                    throw new SessionAdapterException('Redis authentication failed.');
                }
            } catch (SessionAdapterException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new SessionAdapterException('Redis connection failed. ' . $e->getMessage(), (int) $e->getCode(), $e);
            }
        }

        try {
            $this->redis->select($this->database);
        } catch (Throwable $e) {
            throw new SessionAdapterException('Redis database select failed. ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function validateId(string $id): bool
    {
        try {
            $exists = $this->redis->exists($this->prefix . $id);
        } catch (Throwable) {
            // The store failed: do not reject the id (see PDOAdapter).
            return true;
        }

        if ($exists === false) {
            // phpredis reports a failed command as false.
            return true;
        }

        return $exists === true || (\is_int($exists) && $exists > 0);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            // EXPIRE only touches an existing key: a session that is gone
            // (destroyed or regenerated meanwhile) must stay gone. With
            // `ttl => 0` there is no expiry to renew.
            if ($this->ttl > 0) {
                $this->redis->expire($this->prefix . $id, $this->ttl);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function read(string $id): string|false
    {
        try {
            $data = $this->redis->get($this->prefix . $id);

            return $data === false ? '' : (string) $data;
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            if ($this->ttl > 0) {
                return $this->redis->setex($this->prefix . $id, $this->ttl, $data) !== false;
            }

            return $this->redis->set($this->prefix . $id, $data) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->redis->del($this->prefix . $id);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
