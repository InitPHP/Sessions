# API Reference

Exhaustive list of public members. Namespaces are abbreviated:
`S\` = `InitPHP\Sessions`, `S\Classes\`, `S\Adapters\`, `S\Interfaces\`,
`S\Exceptions\`.

---

## `S\Session`

The facade. After `createImmutable()`, every method below is callable
**statically** (`Session::get(...)`) or **on the instance**. Value methods are
delegated to [`GetterSetter`](#sclassesgettersetter); lifecycle methods to
[`Manager`](#sclassesmanager). An unknown name throws
[`SessionException`](Exceptions.md).

### `static createImmutable(?SessionHandlerInterface $adapter = null): self`

Bootstraps the facade and returns an instance for chaining. Pass one of the
[adapters](Adapters.md) (or any `SessionHandlerInterface`) to use a custom save
handler; omit for PHP's default.

```php
Session::createImmutable($adapter)->start();
```

### Properties (read-only, on the instance)

| Property | Type |
| --- | --- |
| `$manager` | `S\Classes\Manager` |
| `$session` | `S\Classes\GetterSetter` |

### Delegated methods

See [`GetterSetter`](#sclassesgettersetter) and [`Manager`](#sclassesmanager) for
signatures. Calling an unknown name throws
[`SessionException`](Exceptions.md).

---

## `S\Classes\GetterSetter`

Fluent, null-safe wrapper over `$_SESSION`.

| Method | Returns | Description |
| --- | --- | --- |
| `has(string $key)` | `bool` | Whether the key exists. |
| `set(string $key, mixed $value)` | `GetterSetter` | Store a value (chainable). |
| `get(string $key, mixed $default = null)` | `mixed` | Value, or `$default` if missing. A stored `null` is returned as-is. |
| `push(string $key, mixed $value)` | `mixed` | Store and return the **value**. |
| `pull(string $key, mixed $default = null)` | `mixed` | Read, then remove the key. |
| `remove(string ...$keys)` | `GetterSetter` | Remove one or more keys. |
| `delete(string ...$keys)` | `GetterSetter` | Alias of `remove()`. |
| `all()` | `array<string,mixed>` | The whole payload (`[]` before start). |
| `setAssoc(array $assoc, bool $reset = false)` | `GetterSetter` | Bulk-write string keys; `$reset` replaces instead of merges. |

Full prose: [Reading & Writing Values](Reading-And-Writing-Values.md).

---

## `S\Classes\Manager`

Exception-aware wrapper over PHP's session lifecycle functions.

| Method | Returns | Description |
| --- | --- | --- |
| `__construct(?SessionHandlerInterface $handler = null)` | — | Save handler registered on `start()`. |
| `start(array $options = [])` | `bool` | Register the handler and `session_start($options)`. |
| `isStarted()` | `bool` | `session_status() === PHP_SESSION_ACTIVE`. |
| `getName()` | `string` | Current session name (`''` if none). |
| `setName(string $name)` | `Manager` | Set the session name (before start). |
| `getID()` | `string` | Current id (`''` if none). |
| `setID(string $sessionId)` | `bool` | Set the id (before start). `true` when PHP accepted it and `getID()` now returns it; `false` when PHP refused it (session already active, headers sent) — see [Session Lifecycle](Session-Lifecycle.md#getid-string-and-setidstring-id-bool). |
| `regenerateId(bool $deleteOldSession = false)` | `bool` | New id, optionally deleting the old record. |
| `flush()` | `bool` | Clear all values. Requires an active session. |
| `unset()` | `bool` | Clear all values. Requires an active session. |
| `destroy()` | `bool` | `session_destroy()`. |

Lifecycle calls raise [`SessionException`](Exceptions.md) on
failure; `flush()`/`unset()` raise it when no session is active.

Full prose: [Session Lifecycle](Session-Lifecycle.md).

---

## `S\AbstractAdapter`

Base class for save handlers. `implements` [`AdapterInterface`](#sinterfacesadapterinterface).
It does not implement PHP's `SessionUpdateTimestampHandlerInterface`; the bundled
adapters add it themselves, and a custom adapter can too (see
[Custom Adapters](Custom-Adapters.md#strict-mode-and-lazy-write)).

| Method | Default | Notes |
| --- | --- | --- |
| `open(string $path, string $name): bool` | `true` | Override to open a connection. |
| `close(): bool` | `true` | Override to close a connection. |
| `gc(int $max_lifetime): int\|false` | `0` | Number of deleted sessions, or `false`. |
| `read(string $id): string\|false` | **abstract** | Payload, or `''` when absent, or `false` on failure. |
| `write(string $id, string $data): bool` | **abstract** | Persist the payload. |
| `destroy(string $id): bool` | **abstract** | Delete the session. |

Guide: [Custom Adapters](Custom-Adapters.md).

---

## `S\Interfaces\AdapterInterface`

```php
interface AdapterInterface extends \SessionHandlerInterface {}
```

A package-owned marker type that every adapter implements. It adds no methods on
top of PHP's native `SessionHandlerInterface`; hint against it when you accept an
adapter. (Strict mode and lazy write come from PHP's separate
`SessionUpdateTimestampHandlerInterface`, which each bundled adapter implements
in addition; see [Strict mode and lazy write](Session-Lifecycle.md#strict-mode-and-lazy-write).)

---

## Adapters (`S\Adapters\*`)

Each constructor takes a single `array $options`/`$credentials`. See each
adapter's page for the full option table.

| Class | Required options | Page |
| --- | --- | --- |
| `FileAdapter` | `path` | [File](File-Adapter.md) |
| `RedisAdapter` | `host`/`port` *or* `redis` | [Redis](Redis-Adapter.md) |
| `PDOAdapter` | `table` + (`pdo` *or* `dsn`) | [PDO](PDO-Adapter.md) |
| `CookieAdapter` | `name`, `key` | [Cookie](Cookie-Adapter.md) |
| `MemCacheAdapter` | — (host/port default) | [Memcache(d)](Memcache-Adapter.md) |
| `MongoDBAdapter` | `dsn`, `collection` | [MongoDB](MongoDB-Adapter.md) |

---

## Exceptions (`S\Exceptions\*`)

| Class | Extends | Raised when |
| --- | --- | --- |
| `SessionException` | `\Exception` | A lifecycle call failed; unknown facade method. |
| `SessionAdapterException` | `SessionException` | Adapter could not reach its backing store. |
| `SessionInvalidArgumentException` | `\InvalidArgumentException` | Missing/invalid adapter options. |
| `SessionNotSupportedAdapter` | `\RuntimeException` | Required extension/library missing. |

Full prose: [Exceptions](Exceptions.md).
