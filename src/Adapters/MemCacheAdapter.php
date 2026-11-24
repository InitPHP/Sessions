<?php

/**
 * MemCacheAdapter.php
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
use Memcache;
use Memcached;
use SessionUpdateTimestampHandlerInterface;
use Throwable;

use function array_change_key_case;
use function array_merge;
use function class_exists;

use const CASE_LOWER;

/**
 * Stores sessions in Memcache/Memcached.
 *
 * Auto-detects which driver is available, preferring the modern `Memcached`
 * extension over the legacy `Memcache` one when both are present.
 *
 * Supported options (case-insensitive keys):
 *  - `host`   (string) Defaults to `127.0.0.1`.
 *  - `port`   (int)    Defaults to `11211`.
 *  - `weight` (int)    Server weight. Defaults to `1`.
 *  - `raw`    (bool)   Enables the binary protocol (Memcached only).
 *  - `prefix` (string) Key prefix. Defaults to none.
 *  - `ttl`    (int)    Item expiry in seconds. Defaults to `86400`.
 *
 * The server drops expired items itself, so an item that exists is a live
 * session ({@see validateId()}); {@see updateTimestamp()} renews its expiry and
 * never creates an item. Only a "not found" answer (or a key too long or
 * malformed to be sent) counts as "no such session": a server error neither
 * rejects the id nor reads as an empty session. (The
 * legacy Memcache driver cannot tell the two apart; there a failed `get()` is
 * treated as "not found".)
 */
class MemCacheAdapter extends AbstractAdapter implements SessionUpdateTimestampHandlerInterface
{
    protected const DRIVER_MEMCACHE = 1;
    protected const DRIVER_MEMCACHED = 2;

    private Memcache|Memcached $memcache;

    private int $driver;

    /** @var array<string, mixed> */
    private array $credentials = [
        'host'   => '127.0.0.1',
        'port'   => 11211,
        'weight' => 1,
        'raw'    => false,
        'prefix' => '',
        'ttl'    => 86400,
    ];

    /**
     * @param array<string, mixed> $credentials
     * @throws SessionNotSupportedAdapter When neither extension is available.
     */
    public function __construct(array $credentials = [])
    {
        if (class_exists(Memcached::class)) {
            $this->driver = self::DRIVER_MEMCACHED;
        } elseif (class_exists(Memcache::class)) {
            $this->driver = self::DRIVER_MEMCACHE;
        } else {
            throw new SessionNotSupportedAdapter('The MemCacheAdapter requires the "memcached" or "memcache" PHP extension.');
        }

        $this->credentials = array_merge($this->credentials, array_change_key_case($credentials, CASE_LOWER));
    }

    public function __destruct()
    {
        if (!isset($this->memcache)) {
            return;
        }

        if ($this->memcache instanceof Memcache) {
            $this->memcache->close();
        } else {
            $this->memcache->quit();
        }

        unset($this->memcache);
    }

    public function validateId(string $id): bool
    {
        try {
            $key = $this->key($id);
            $client = $this->getMemcache();

            if ($client instanceof Memcache) {
                return $client->get($key) !== false;
            }

            $client->get($key);

            // Only "not found" (or a key the client refuses to send, which can
            // never hold a session) means the session does not exist; any
            // other failure must not replace the user's session id.
            return !$this->isMissing($client->getResultCode());
        } catch (Throwable) {
            return true;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $key = $this->key($id);
            $ttl = (int) $this->credentials['ttl'];
            $client = $this->getMemcache();

            // Renew only an item that still exists: a session that is gone
            // (destroyed or regenerated meanwhile) must stay gone.
            if ($client instanceof Memcache) {
                // The legacy driver has no touch(); replace() fails on a
                // missing key, which is then told apart from a real failure.
                return $client->replace($key, $data, 0, $ttl) !== false || $client->get($key) === false;
            }

            return $client->touch($key, $ttl) || $this->isMissing($client->getResultCode());
        } catch (Throwable) {
            return false;
        }
    }

    public function read(string $id): string|false
    {
        try {
            $key = $this->key($id);
            $client = $this->getMemcache();

            if ($client instanceof Memcache) {
                $data = $client->get($key);

                return \is_string($data) ? $data : '';
            }

            $data = $client->get($key);
            $code = $client->getResultCode();
            if ($this->isMissing($code)) {
                return '';
            }
            if ($code !== Memcached::RES_SUCCESS) {
                // A server error is a read failure, not an empty session.
                return false;
            }

            return \is_string($data) ? $data : '';
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $key = $this->key($id);
            $ttl = (int) $this->credentials['ttl'];
            $client = $this->getMemcache();

            if ($client instanceof Memcache) {
                return $client->set($key, $data, 0, $ttl) !== false;
            }

            return $client->set($key, $data, $ttl) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->getMemcache()->delete($this->key($id));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether a Memcached result code means "there is no such item": either it
     * was not found, or the key is one the client refuses to send (too long or
     * containing a space), which can never hold a session. The latter comes from
     * the id the client chose and must not lock the user out.
     */
    private function isMissing(int $code): bool
    {
        return $code === Memcached::RES_NOTFOUND || $code === Memcached::RES_BAD_KEY_PROVIDED;
    }

    private function key(string $id): string
    {
        return (string) $this->credentials['prefix'] . $id;
    }

    private function host(): string
    {
        return (string) $this->credentials['host'];
    }

    private function port(): int
    {
        return (int) $this->credentials['port'];
    }

    private function weight(): int
    {
        return (int) $this->credentials['weight'];
    }

    /**
     * Lazily creates and connects the underlying client.
     *
     * @throws SessionAdapterException When the connection cannot be opened.
     */
    private function getMemcache(): Memcache|Memcached
    {
        if (isset($this->memcache)) {
            return $this->memcache;
        }

        try {
            if ($this->driver === self::DRIVER_MEMCACHE) {
                $client = new Memcache();
                if ($client->connect($this->host(), $this->port()) === false) {
                    throw new SessionAdapterException('Memcache connection failed.');
                }
                $client->addServer($this->host(), $this->port(), true, $this->weight());

                return $this->memcache = $client;
            }

            $client = new Memcached();
            if (($this->credentials['raw'] ?? false) !== false) {
                $client->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
            }
            $client->addServer($this->host(), $this->port(), $this->weight());

            $statKey = $this->host() . ':' . $this->port();
            $stats = $client->getStats();
            if (!isset($stats[$statKey])) {
                throw new SessionAdapterException('Memcached connection failed.');
            }

            return $this->memcache = $client;
        } catch (SessionAdapterException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new SessionAdapterException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
