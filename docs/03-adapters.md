# 03 — Adapters

An adapter is a save handler: it tells PHP where session data lives. All bundled
adapters extend `InitPHP\Sessions\AbstractAdapter` and implement
`InitPHP\Sessions\Interfaces\AdapterInterface`, which extends the native
`SessionHandlerInterface`. Pass one to `createImmutable()`:

```php
Session::createImmutable($adapter)->start();
```

A note on the save-handler contract: `read()` returns the raw session-encoded
payload, or an empty string `''` for a session that does not exist yet — never a
serialized empty array. Every bundled adapter honours this.

---

## FileAdapter

Stores each session as a file in a directory you choose.

**Requirements:** none.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `path` | string | — | **Required.** Existing, writable directory. |
| `prefix` | string | `sess_` | File name prefix. |

```php
use InitPHP\Sessions\Adapters\FileAdapter;

$adapter = new FileAdapter([
    'path'   => '/var/www/storage/sessions',
    'prefix' => 'sess_',
]);

Session::createImmutable($adapter)->start();
```

Garbage collection deletes files whose modification time is older than the
configured `gc_maxlifetime`.

---

## RedisAdapter

Stores sessions in Redis with a per-key TTL.

**Requirements:** `ext-redis`.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `redis` | `\Redis` | — | Reuse an existing client. When set, the connection options below are ignored. |
| `host` | string | `127.0.0.1` | |
| `port` | int | `6379` | |
| `timeout` | float | `0` | Connect timeout (seconds). |
| `password` | string\|null | `null` | `AUTH` password. |
| `database` | int | `0` | `SELECT` index. |
| `ttl` | int | `86400` | Key expiry (seconds). `0` disables expiry. |
| `prefix` | string | `''` | Key prefix. |

```php
use InitPHP\Sessions\Adapters\RedisAdapter;

$adapter = new RedisAdapter([
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'database' => 0,
    'ttl'      => 86400,
    'prefix'   => 'sess_',
]);

// Reusing a configured client instead:
// $adapter = new RedisAdapter(['redis' => $client, 'ttl' => 86400]);

Session::createImmutable($adapter)->start();
```

---

## PDOAdapter

Stores sessions in a relational database. Writes use a portable
"insert-or-update" upsert, so the same backend works on MySQL, PostgreSQL and
SQLite.

**Requirements:** `ext-pdo` and a driver.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `pdo` | `PDO` | — | An existing connection. |
| `dsn` | string | — | Used when `pdo` is absent. |
| `username` | string | — | With `dsn`. |
| `password` | string | — | With `dsn`. |
| `table` | string | — | **Required.** |
| `withIPAddress` | bool | `false` | Also match the client IP on read/destroy. |

Either `pdo` or `dsn` is required.

```php
use InitPHP\Sessions\Adapters\PDOAdapter;

$pdo = new PDO('mysql:host=localhost;dbname=app', 'root', '');

$adapter = new PDOAdapter([
    'pdo'   => $pdo,
    'table' => 'sessions',
]);

Session::createImmutable($adapter)->start();
```

Expected table (MySQL):

```sql
CREATE TABLE `sessions` (
    `id`              VARCHAR(255) NOT NULL,
    `sess_timestamp`  DATETIME NULL DEFAULT NULL,
    `sess_ip_address` VARCHAR(48) DEFAULT NULL,
    `sess_data`       TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

With `withIPAddress => true`, a session is only readable/destroyable from the
same client IP that wrote it. Garbage collection deletes rows whose
`sess_timestamp` is older than `gc_maxlifetime`.

---

## CookieAdapter

Stores the whole payload in an encrypted, base64-encoded cookie — no server-side
storage at all.

**Requirements:** `initphp/encryption` (`composer require initphp/encryption`)
and `ext-openssl`.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `name` | string | — | **Required.** Cookie name. |
| `key` | string | — | **Required.** Encryption key. |
| `ttl` | int | `86400` | Cookie lifetime (seconds). |
| `path` | string | `/` | Cookie path. |
| `domain` | string | `''` | Cookie domain. |
| `secure` | bool | `false` | Send over HTTPS only. |
| `httponly` | bool | `true` | Hide from JavaScript. |
| `samesite` | string | `Lax` | `Lax`, `Strict` or `None`. |

```php
use InitPHP\Sessions\Adapters\CookieAdapter;

$adapter = new CookieAdapter([
    'name'     => 'app_session',
    'key'      => 'your-secret-key',
    'secure'   => true,
    'samesite' => 'Strict',
]);

Session::createImmutable($adapter)->start();
```

A tampered, corrupt, or wrong-key cookie is treated as an empty session rather
than throwing. Because everything lives client-side, keep the payload small and
treat the `key` like any other secret.

---

## MemCacheAdapter

Stores sessions in Memcache/Memcached. The driver is auto-detected, preferring
`Memcached` over the legacy `Memcache` when both are present.

**Requirements:** `ext-memcached` or `ext-memcache`.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `host` | string | `127.0.0.1` | |
| `port` | int | `11211` | |
| `weight` | int | `1` | Server weight. |
| `raw` | bool | `false` | Binary protocol (Memcached only). |
| `prefix` | string | `''` | Key prefix. |
| `ttl` | int | `86400` | Item expiry (seconds). |

Option keys are case-insensitive.

```php
use InitPHP\Sessions\Adapters\MemCacheAdapter;

$adapter = new MemCacheAdapter([
    'host' => '127.0.0.1',
    'port' => 11211,
    'ttl'  => 86400,
]);

Session::createImmutable($adapter)->start();
```

---

## MongoDBAdapter

Stores each session as one document, keyed by `_id`, with a `timestamp` used for
garbage collection.

**Requirements:** `ext-mongodb`.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `dsn` | string | — | **Required.** Connection string. |
| `collection` | string | — | **Required.** `database.collection`. The legacy `collation` key is accepted as an alias. |

```php
use InitPHP\Sessions\Adapters\MongoDBAdapter;

$adapter = new MongoDBAdapter([
    'dsn'        => 'mongodb://127.0.0.1:27017',
    'collection' => 'app.sessions',
]);

Session::createImmutable($adapter)->start();
```

Writes upsert by `_id`, so repeated writes to the same session update the
existing document. Garbage collection deletes documents whose `timestamp` is
older than `gc_maxlifetime`.
