# InitPHP Sessions

Framework-agnostic PHP session manager with a small, fluent API and pluggable
save handlers. Bring your own backend — files, an encrypted cookie, a SQL
database (PDO), Redis, Memcache/Memcached, or MongoDB — behind one consistent
interface.

[![Designed & Maintained with Tan](https://muhammetsafak.com/badges/designed-maintained-with-tan.svg)](https://muhammetsafak.com/tan/)
[![CI](https://github.com/InitPHP/Sessions/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/Sessions/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)

## Requirements

- PHP 8.1 or later
- Individual adapters may require additional extensions or libraries (see each
  adapter below).

## Installation

```bash
composer require initphp/sessions
```

## Quick Start

Bootstrap the facade once with `createImmutable()`, start the session, then read
and write values. With no adapter, PHP's default save handler is used.

```php
require_once 'vendor/autoload.php';

use InitPHP\Sessions\Session;

Session::createImmutable()
    ->start();

Session::set('username', 'admin')
    ->set('mail', 'admin@example.com');

echo Session::get('username', 'Undefined');
```

Every method is available both statically (`Session::set(...)`) and on the
instance returned by `createImmutable()` — the two styles are interchangeable.
You can keep using `$_SESSION` directly if you prefer; the facade is a
convenience layer, not a replacement.

```php
$_SESSION['username'] = 'admin';
echo $_SESSION['username'] ?? 'Undefined';
```

## The `Session` API

### Reading & writing values

| Method | Returns | Description |
| --- | --- | --- |
| `has(string $key)` | `bool` | Whether the key exists. |
| `set(string $key, mixed $value)` | `GetterSetter` | Store a value (chainable). |
| `get(string $key, mixed $default = null)` | `mixed` | Read a value, or `$default`. |
| `push(string $key, mixed $value)` | `mixed` | Store and return the value itself. |
| `pull(string $key, mixed $default = null)` | `mixed` | Read, then remove the key. |
| `remove(string ...$keys)` / `delete(...)` | `GetterSetter` | Remove one or more keys. |
| `all()` | `array` | The whole session payload. |
| `setAssoc(array $assoc, bool $reset = false)` | `GetterSetter` | Merge (or replace) many string-keyed values. |

### Managing the session lifecycle

| Method | Returns | Description |
| --- | --- | --- |
| `start(array $options = [])` | `bool` | Register the adapter (if any) and start the session. |
| `isStarted()` | `bool` | Whether a session is currently active. |
| `getName()` / `setName(string $name)` | `string` / `Manager` | Read / set the session name (before `start()`). |
| `getID()` / `setID(string $id)` | `string` / `bool` | Read / set the session id (before `start()`). |
| `regenerateId(bool $deleteOld = false)` | `bool` | Issue a fresh session id. |
| `flush()` / `unset()` | `bool` | Clear all session variables (requires an active session). |
| `destroy()` | `bool` | Destroy the session entirely. |

## Adapters

All adapters implement `InitPHP\Sessions\Interfaces\AdapterInterface`, which
extends PHP's native `SessionHandlerInterface`. Pass an adapter to
`createImmutable()` and it becomes the session's save handler.

### File

Stores sessions as files in a directory you choose, without touching the global
`session.save_path`.

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\FileAdapter;

$adapter = new FileAdapter([
    'path'   => '/var/www/storage/sessions', // required, writable directory
    'prefix' => 'sess_',                      // optional, default "sess_"
]);

Session::createImmutable($adapter)->start();
```

### Redis

Requires `ext-redis`. Provide connection details inline, or reuse an existing
`\Redis` instance via the `redis` option.

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\RedisAdapter;

$adapter = new RedisAdapter([
    'host'     => '127.0.0.1', // string
    'port'     => 6379,        // int
    'timeout'  => 0,           // float, connect timeout (seconds)
    'password' => null,        // null or string (AUTH)
    'database' => 0,           // int (SELECT)
    'ttl'      => 86400,       // int, key expiry (seconds)
    'prefix'   => 'sess_',     // optional key prefix
]);

// Or reuse an existing client:
// $adapter = new RedisAdapter(['redis' => $existingRedis, 'ttl' => 86400]);

Session::createImmutable($adapter)->start();
```

### PDO

Requires `ext-pdo` and a driver (MySQL, PostgreSQL, SQLite, ...). Pass an
existing `PDO` instance, or a `dsn` to let the adapter connect.

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\PDOAdapter;

$pdo = new PDO('mysql:host=localhost;dbname=test', 'root', '');

$adapter = new PDOAdapter([
    'pdo'           => $pdo,          // or 'dsn' => '...', 'username' => '...', 'password' => '...'
    'table'         => 'sessions',    // required
    'withIPAddress' => false,         // optional: also match the client IP on read/destroy
]);

Session::createImmutable($adapter)->start();
```

Example table (MySQL):

```sql
CREATE TABLE `sessions` (
    `id`              VARCHAR(255) NOT NULL,
    `sess_timestamp`  DATETIME NULL DEFAULT NULL,
    `sess_ip_address` VARCHAR(48) DEFAULT NULL,
    `sess_data`       TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Cookie

Stores the whole session payload in an encrypted, base64-encoded cookie — no
server-side storage. Requires `initphp/encryption`:

```bash
composer require initphp/encryption
```

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\CookieAdapter;

$adapter = new CookieAdapter([
    'name'     => 'app_session',     // required
    'key'      => 'your-secret-key', // required, encryption key
    'ttl'      => 86400,             // optional, default 86400
    'secure'   => true,              // optional, HTTPS only (default false)
    'httponly' => true,              // optional, hide from JS (default true)
    'samesite' => 'Lax',             // optional, Lax|Strict|None (default Lax)
]);

Session::createImmutable($adapter)->start();
```

### Memcache / Memcached

Requires `ext-memcached` (preferred) or `ext-memcache`. The driver is detected
automatically.

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\MemCacheAdapter;

$adapter = new MemCacheAdapter([
    'host'   => '127.0.0.1', // string
    'port'   => 11211,       // int
    'weight' => 1,           // int
    'raw'    => false,       // bool, binary protocol (Memcached only)
    'prefix' => '',          // optional key prefix
    'ttl'    => 86400,       // int, item expiry (seconds)
]);

Session::createImmutable($adapter)->start();
```

### MongoDB

Requires `ext-mongodb`.

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\MongoDBAdapter;

$adapter = new MongoDBAdapter([
    'dsn'        => 'mongodb://127.0.0.1:27017',  // required
    'collection' => 'app.sessions',               // required, "database.collection"
]);

Session::createImmutable($adapter)->start();
```

## Custom Adapters

Extend `InitPHP\Sessions\AbstractAdapter` and implement `read()`, `write()` and
`destroy()`. `open()`, `close()` and `gc()` have sensible defaults you can
override:

```php
use InitPHP\Sessions\AbstractAdapter;

final class ArrayAdapter extends AbstractAdapter
{
    /** @var array<string, string> */
    private array $store = [];

    public function read(string $id): string|false
    {
        return $this->store[$id] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $this->store[$id] = $data;

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->store[$id]);

        return true;
    }
}
```

## Error Handling

The package throws a small, predictable set of exceptions, all rooted at
`InitPHP\Sessions\Exceptions\SessionException`:

| Exception | When |
| --- | --- |
| `SessionException` | Base type; a `session_*()` lifecycle call failed. |
| `SessionAdapterException` | An adapter failed to reach its backing store (connection, auth, driver). Extends `SessionException`. |
| `SessionInvalidArgumentException` | Missing or invalid adapter options. Extends `\InvalidArgumentException`. |
| `SessionNotSupportedAdapter` | A required extension or library is unavailable. Extends `\RuntimeException`. |

```php
use InitPHP\Sessions\Exceptions\SessionException;

try {
    Session::createImmutable($adapter)->start();
} catch (SessionException $e) {
    // Covers both lifecycle and adapter failures.
}
```

## Documentation

In-depth, copy-pasteable docs live in [`docs/`](./docs/README.md).

## Quality

```bash
composer test     # PHPUnit
composer phpstan  # Static analysis (level 8)
composer cs-check # Coding style (PSR-12)
composer qa       # All of the above
```

## Migrating from `initphp/redis-session-handler`

The standalone [`initphp/redis-session-handler`](https://github.com/InitPHP/RedisSessionHandler)
package is deprecated in favour of this one. The `RedisAdapter` here is a
superset: TTL support, typed exceptions and the unified `Session` API.

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

This adapter talks directly to `ext-redis` (or an existing `\Redis` instance
passed via the `redis` option), so the `initphp/redis` wrapper is no longer
required. Pass the `prefix` option if you want to keep the previous `sess_`
prefix exactly.

## Credits

- [Muhammet ŞAFAK](https://github.com/muhammetsafak) <<info@muhammetsafak.com>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
