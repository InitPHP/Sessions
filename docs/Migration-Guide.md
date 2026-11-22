# Migration Guide

This page covers two migrations:

1. [Upgrading to 3.x](#upgrading-to-3x) — from earlier `initphp/sessions` releases.
2. [From `initphp/redis-session-handler`](#from-initphpredis-session-handler) —
   the deprecated standalone package.

---

## Upgrading to 3.x

The 3.x line modernises the package (PHP 8.1+, typed throughout, tested, and with
the adapter examples actually working). Most code keeps running unchanged; the
notable points are below.

### Minimum PHP is now 8.1

Update your environment/CI to PHP `^8.1`. There are no PHP-8.1-specific API
changes you need to make in your own code. The current release raises the floor
further, to PHP `^8.2`.

### `createImmutable()` returns an instance

`createImmutable()` now returns a `Session` instance, so the documented chaining
works:

```php
// Now works as advertised:
Session::createImmutable($adapter)->start();
```

If you previously split it into two statements, that still works too:

```php
Session::createImmutable($adapter);
Session::start();
```

### Redis: pass connection options at the top level

Connection details are now top-level options (matching the documentation). To
reuse an existing client, pass it under `redis`.

```php
// 3.x
new RedisAdapter([
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'database' => 0,
    'ttl'      => 86400,
    'prefix'   => 'sess_',
]);

// reuse a client:
new RedisAdapter(['redis' => $existingRedis, 'ttl' => 86400]);
```

If you were constructing the adapter with a nested connection array, flatten it
to the top level. The default `ttl` is `86400`.

### MongoDB: the option is `collection`

Use `collection` (`"database.collection"`). The old `collation` key still works
as an alias, but prefer `collection`:

```php
new MongoDBAdapter([
    'dsn'        => 'mongodb://127.0.0.1:27017',
    'collection' => 'app.sessions',
]);
```

Writes now **upsert** by `_id`, so repeated writes to the same session no longer
fail with duplicate-key errors.

### PDO: corrected SQL & portable upsert

Reads and writes now work across MySQL/PostgreSQL/SQLite. Make sure your table
matches the documented schema (note `sess_timestamp` is written as a
`Y-m-d H:i:s` string):

```sql
CREATE TABLE `sessions` (
    `id`              VARCHAR(255) NOT NULL,
    `sess_timestamp`  DATETIME NULL DEFAULT NULL,
    `sess_ip_address` VARCHAR(48) DEFAULT NULL,
    `sess_data`       TEXT NOT NULL,
    PRIMARY KEY (`id`)
);
```

### Cookie: safer defaults & new options

The [Cookie adapter](Cookie-Adapter.md) now sets `httponly` (default **true**) and
supports `secure`, `samesite`, `path`, and `domain`. A tampered/corrupt/foreign
cookie is treated as an empty session. Review your options:

```php
new CookieAdapter([
    'name'     => 'app_session',
    'key'      => getenv('SESSION_KEY'),
    'secure'   => true,
    'samesite' => 'Strict',
]);
```

### Exceptions: one base to catch them all

`SessionAdapterException` now extends `SessionException`, so a single
`catch (SessionException $e)` covers both lifecycle and adapter-store failures.
Configuration and missing-dependency errors remain
`SessionInvalidArgumentException` (a `\InvalidArgumentException`) and
`SessionNotSupportedAdapter` (a `\RuntimeException`). See [Exceptions](Exceptions.md).

### Garbage collection semantics

`gc()` now returns the **number of deleted sessions** (the base default returns
`0`), instead of echoing back `max_lifetime`. File/PDO/MongoDB perform real
cleanup; Redis/Memcached rely on their TTLs.

---

## From `initphp/redis-session-handler`

The standalone
[`initphp/redis-session-handler`](https://github.com/InitPHP/RedisSessionHandler)
package is **deprecated** in favour of this one. The [Redis adapter](Redis-Adapter.md)
here is a superset: TTL support, typed exceptions, and the unified `Session` API.

**Before:**

```php
$redis = new \InitPHP\Redis\Redis(['host' => '127.0.0.1', 'port' => 6379]);

$handler = new \InitPHP\RedisSessionHandler\Handler($redis);
session_set_save_handler($handler, true);
session_start();
```

**After:**

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\RedisAdapter;

$adapter = new RedisAdapter([
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'database' => 0,
    'ttl'      => 86400,
    'prefix'   => 'sess_',
]);

Session::createImmutable($adapter)->start();
```

Notes:

- The new adapter talks **directly to `ext-redis`** (or to a `\Redis` instance you
  pass via the `redis` option), so the `initphp/redis` wrapper is no longer
  required.
- Keys are still prefixable — pass `prefix => 'sess_'` to keep the old prefix
  exactly, so existing sessions remain readable during the cut-over.

## See also

- [Installation](Installation.md)
- [Adapters](Adapters.md)
- [FAQ](FAQ.md)
