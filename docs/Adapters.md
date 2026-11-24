# Adapters

An **adapter** is a session *save handler*: it tells PHP where session data is
read from and written to. Every bundled adapter extends
[`AbstractAdapter`](Custom-Adapters.md) and implements
`InitPHP\Sessions\Interfaces\AdapterInterface`, which extends PHP's native
`SessionHandlerInterface`.

You wire one in by passing it to `createImmutable()`:

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\RedisAdapter;

Session::createImmutable(new RedisAdapter([
    'host' => '127.0.0.1',
    'port' => 6379,
]))->start();
```

With no adapter, PHP's configured default handler (usually files) is used.

## Choosing an adapter

| Adapter | Best for | Requires | Expiry |
| --- | --- | --- | --- |
| [File](File-Adapter.md) | A custom save path/prefix without touching `php.ini` | — | `gc()` by file mtime |
| [Redis](Redis-Adapter.md) | Fast, shared sessions across app servers | `ext-redis` | per-key TTL |
| [PDO](PDO-Adapter.md) | Sessions in your existing SQL database | `ext-pdo` + driver | `gc()` by timestamp |
| [Cookie](Cookie-Adapter.md) | Stateless servers; no server storage at all | `initphp/encryption`, `ext-openssl` | cookie lifetime |
| [Memcache(d)](Memcache-Adapter.md) | Fast, ephemeral, in-memory sessions | `ext-memcached`/`ext-memcache` | per-item TTL |
| [MongoDB](MongoDB-Adapter.md) | Document-store deployments | `ext-mongodb` | `gc()` by timestamp |

## The save-handler contract

If you write your own adapter, two contract details matter most:

- **`read()` returns the raw, session-encoded payload** that PHP handed to
  `write()` — *not* your individual `$_SESSION` values. For a session that does
  not exist yet, return an **empty string `''`**. Returning `false` signals a
  genuine read failure; returning a serialized empty array (`a:0:{}`) corrupts the
  session.
- **`write()` / `destroy()` return `bool`**, and `gc()` returns the number of
  deleted sessions (or `false`).

`AbstractAdapter` gives you safe defaults for `open()`, `close()` and `gc()`, so a
minimal adapter only implements `read()`, `write()` and `destroy()`. See
[Custom Adapters](Custom-Adapters.md).

Every bundled adapter supports `session.use_strict_mode` (PHP rejects a session id
the server did not issue) and `session.lazy_write` (an unchanged session only has
its lifetime renewed); see
[Strict mode and lazy write](Session-Lifecycle.md#strict-mode-and-lazy-write).

## Error behaviour

- **Construction-time** problems (missing extension, bad options, unreachable
  backend) throw — see [Exceptions](Exceptions.md):
  - missing extension/library → `SessionNotSupportedAdapter`
  - missing/invalid option → `SessionInvalidArgumentException`
  - connection/auth failure → `SessionAdapterException`
- **Runtime** read/write/destroy calls do **not** throw — they return `false`
  (or `''` for an absent session), as PHP's session machinery expects.

## Reusing existing clients

Two adapters let you inject an already-configured client instead of connection
options, so you can share one connection across your app:

```php
// Redis
new RedisAdapter(['redis' => $existingRedisInstance, 'ttl' => 86400]);

// PDO
new PDOAdapter(['pdo' => $existingPdoInstance, 'table' => 'sessions']);
```

## Adapter pages

- [File Adapter](File-Adapter.md)
- [Redis Adapter](Redis-Adapter.md)
- [PDO Adapter](PDO-Adapter.md)
- [Cookie Adapter](Cookie-Adapter.md)
- [Memcache / Memcached Adapter](Memcache-Adapter.md)
- [MongoDB Adapter](MongoDB-Adapter.md)
- [Custom Adapters](Custom-Adapters.md)
