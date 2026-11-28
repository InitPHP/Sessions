# InitPHP Sessions — Wiki

Welcome to the official documentation for **`initphp/sessions`** — a small,
framework-agnostic session manager that puts a fluent, exception-aware API in
front of PHP's native sessions, with **pluggable save handlers** so you can store
sessions wherever you like.

The package ships three building blocks:

| Type | Purpose |
| --- | --- |
| [`Session`](The-Session-Facade.md) | The facade you call (`Session::set(...)`, `Session::start()`). |
| [`AbstractAdapter`](Custom-Adapters.md) | Base class for save handlers; implement `read`/`write`/`destroy`. |
| [`AdapterInterface`](API-Reference.md#sinterfacesadapterinterface) | The contract — extends PHP's native `SessionHandlerInterface`. |

```bash
composer require initphp/sessions
```

```php
use InitPHP\Sessions\Session;

Session::createImmutable()
    ->start();

Session::set('username', 'admin')
    ->set('mail', 'admin@example.com');

echo Session::get('username', 'guest'); // 'admin'
```

## Start here

- **New to the package?** Read [Installation](Installation.md), then
  [Quick Start](Quick-Start.md).
- **Want the big picture?** Read [The Session Facade](The-Session-Facade.md).
- **Storing sessions somewhere specific?** See [Adapters](Adapters.md) and pick a
  backend.
- **Building login?** Read the [Authentication recipe](Recipe-Authentication.md).
- **Need a method signature?** The [API Reference](API-Reference.md) lists every
  public member.
- **Upgrading?** Read the [Migration Guide](Migration-Guide.md).

## What it does

- A **fluent facade** over `$_SESSION` and the `session_*()` lifecycle — readable
  reads, chainable writes, and predictable [exceptions](Exceptions.md) instead of
  raw warnings.
- **Six bundled adapters** behind one interface:
  [File](File-Adapter.md), [Redis](Redis-Adapter.md), [PDO](PDO-Adapter.md)
  (MySQL/PostgreSQL/SQLite), [Cookie](Cookie-Adapter.md) (encrypted, server-less),
  [Memcache/Memcached](Memcache-Adapter.md), and [MongoDB](MongoDB-Adapter.md).
- A tiny **extension point** — write your own handler by extending
  [`AbstractAdapter`](Custom-Adapters.md).
- Drops in alongside plain `$_SESSION`; it is a convenience layer, not a
  replacement.

## Adapter cheat-sheet

| Adapter | Stores in | Requires | Built-in expiry |
| --- | --- | --- | :---: |
| [File](File-Adapter.md) | Files in a directory | — | via `gc()` |
| [Redis](Redis-Adapter.md) | Redis | `ext-redis` | TTL |
| [PDO](PDO-Adapter.md) | SQL database | `ext-pdo` + driver | via `gc()` |
| [Cookie](Cookie-Adapter.md) | Encrypted client cookie | `initphp/encryption`, `ext-openssl` | cookie lifetime |
| [Memcache(d)](Memcache-Adapter.md) | Memcache/Memcached | `ext-memcached` or `ext-memcache` | TTL |
| [MongoDB](MongoDB-Adapter.md) | MongoDB | `ext-mongodb` | via `gc()` |

## Package metadata

- **License**: MIT
- **Minimum PHP**: 8.2 (tested on 8.2 – 8.6)
- **Runtime dependencies**: none (adapters pull in their own extensions)
- **Packagist**: [`initphp/sessions`](https://packagist.org/packages/initphp/sessions)
- **Source**: [github.com/InitPHP/Sessions](https://github.com/InitPHP/Sessions)
- **Issues**: [github.com/InitPHP/Sessions/issues](https://github.com/InitPHP/Sessions/issues)
- **Discussions**: [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions)
- **Security**: [`SECURITY.md`](https://github.com/InitPHP/.github/blob/main/SECURITY.md)

If something in this wiki is unclear, ambiguous, or wrong, please
[open an issue](https://github.com/InitPHP/Sessions/issues/new) —
documentation fixes are reviewed eagerly.
