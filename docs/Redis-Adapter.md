# Redis Adapter

`InitPHP\Sessions\Adapters\RedisAdapter` stores sessions in Redis with a per-key
TTL, so expiry is handled by Redis itself.

**Requirements:** `ext-redis`.

## Options

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `redis` | `\Redis` | — | Reuse an existing client. When set, the connection options below are ignored. |
| `host` | string | `127.0.0.1` | |
| `port` | int | `6379` | |
| `timeout` | float | `0` | Connect timeout in seconds. |
| `password` | string\|null | `null` | `AUTH` password. |
| `database` | int | `0` | Database index to `SELECT`. |
| `ttl` | int | `86400` | Key expiry in seconds. `0` disables expiry. |
| `prefix` | string | `''` | Key prefix. |

## Usage — connect inline

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\RedisAdapter;

$adapter = new RedisAdapter([
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'timeout'  => 0,
    'password' => null,
    'database' => 0,
    'ttl'      => 86400,
    'prefix'   => 'sess_',
]);

Session::createImmutable($adapter)->start();
```

## Usage — reuse an existing client

If your app already has a configured `\Redis`, hand it over and skip the
connection options:

```php
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth('secret');

$adapter = new RedisAdapter([
    'redis'  => $redis,
    'ttl'    => 86400,
    'prefix' => 'sess_',
]);

Session::createImmutable($adapter)->start();
```

## Expiry

Each `write()` stores the payload with `SETEX` using `ttl` (when `ttl > 0`), so
keys expire automatically — no garbage collection needed. Set `ttl => 0` to store
keys without expiry (you then manage cleanup yourself).

The session's effective lifetime is the larger of this `ttl` and whatever your
session cookie lifetime is; align them to avoid surprises.

## Strict mode and lazy write

Redis drops expired keys itself, so with `session.use_strict_mode` on a session id
counts as known exactly when its key exists (`EXISTS`); an id with no key is
replaced by a fresh one. When the data did not change, `session.lazy_write` makes
PHP call `updateTimestamp()`, which renews the key's expiry with `EXPIRE` (back to
the full `ttl`) instead of rewriting the payload. Without that renewal an active
user would be logged out `ttl` seconds after the last change to their session
data. With `ttl => 0` there is no expiry to renew.

## Errors

- No `ext-redis` → [`SessionNotSupportedAdapter`](Exceptions.md).
- Connection, `AUTH`, or `SELECT` failure → [`SessionAdapterException`](Exceptions.md)
  (a subtype of `SessionException`).

```php
use InitPHP\Sessions\Exceptions\SessionAdapterException;

try {
    $adapter = new RedisAdapter(['host' => '10.0.0.9', 'port' => 6379]);
} catch (SessionAdapterException $e) {
    // Redis is unreachable — fall back or fail fast.
}
```

## Migrating from `initphp/redis-session-handler`

This adapter supersedes the standalone `initphp/redis-session-handler` package.
See the [Migration Guide](Migration-Guide.md#from-initphpredis-session-handler).

## See also

- [Adapters overview](Adapters.md)
- [Memcache / Memcached Adapter](Memcache-Adapter.md) — the other in-memory option.
