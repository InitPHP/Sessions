# Memcache / Memcached Adapter

`InitPHP\Sessions\Adapters\MemCacheAdapter` stores sessions in Memcache or
Memcached, with a per-item TTL. The driver is **auto-detected**, preferring the
modern `Memcached` extension over the legacy `Memcache` one when both are present.

**Requirements:** `ext-memcached` (preferred) or `ext-memcache`.

## Options

Option keys are **case-insensitive**.

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `host` | string | `127.0.0.1` | |
| `port` | int | `11211` | |
| `weight` | int | `1` | Server weight. |
| `raw` | bool | `false` | Enable the binary protocol (Memcached only). |
| `prefix` | string | `''` | Key prefix. |
| `ttl` | int | `86400` | Item expiry in seconds. |

If neither extension is loaded, the constructor throws a
[`SessionNotSupportedAdapter`](Exceptions.md). A failure to connect at first use
throws a [`SessionAdapterException`](Exceptions.md).

## Usage

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\MemCacheAdapter;

$adapter = new MemCacheAdapter([
    'host'   => '127.0.0.1',
    'port'   => 11211,
    'weight' => 1,
    'raw'    => false,
    'prefix' => 'sess_',
    'ttl'    => 86400,
]);

Session::createImmutable($adapter)->start();
```

## Driver detection

| Available extension(s) | Driver used |
| --- | --- |
| `memcached` only | Memcached |
| `memcache` only | Memcache |
| both | Memcached (preferred) |
| neither | throws `SessionNotSupportedAdapter` |

The `raw` (binary protocol) option only applies to the Memcached driver and is
ignored by the legacy Memcache driver.

## Expiry

`write()` stores each item with `ttl` seconds of expiry, so the backend evicts
stale sessions automatically — no garbage collection step is required. Memcache
is a cache, so treat sessions as **ephemeral**: an eviction under memory pressure
logs the user out.

## Strict mode and lazy write

The server drops expired items itself, so with `session.use_strict_mode` on a
session id counts as known exactly when its item exists; an id with no item is
replaced by a fresh one. When the data did not change, `session.lazy_write` makes
PHP call `updateTimestamp()`, which renews the item's expiry with `touch` on the
Memcached driver (back to the full `ttl`) and stores the payload again on the
legacy Memcache driver, which has no `touch`.

## Behaviour notes

- `read()` returns the stored payload, or `''` when the key is absent (a new
  session).
- The connection is established lazily on first read/write, and closed in the
  adapter's destructor.

## When to use it

- You already run Memcached and want fast, in-memory, throwaway sessions.
- You don't need persistence across restarts (use [Redis](Redis-Adapter.md) if you
  want TTL **and** more durability/features).

## See also

- [Adapters overview](Adapters.md)
- [Redis Adapter](Redis-Adapter.md)
