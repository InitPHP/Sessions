# Custom Adapters

A save handler is any class implementing PHP's `SessionHandlerInterface`. The
easiest path is to extend `InitPHP\Sessions\AbstractAdapter`, which provides safe
defaults for the parts most stores don't need to customise.

## What `AbstractAdapter` gives you

| Method | Default | Override when |
| --- | --- | --- |
| `open(string $path, string $name): bool` | returns `true` | You open a connection per request. |
| `close(): bool` | returns `true` | You close a connection. |
| `gc(int $max_lifetime): int\|false` | returns `0` (deletes nothing) | Your store has no built-in expiry. |
| `validateId(string $id): bool` | `true` when `read($id)` returns a non-empty payload | Your store can check existence without reading the payload. |
| `updateTimestamp(string $id, string $data): bool` | calls `write($id, $data)` | Your store can renew the lifetime without rewriting the payload. |
| `read(string $id): string\|false` | **abstract** | Always. |
| `write(string $id, string $data): bool` | **abstract** | Always. |
| `destroy(string $id): bool` | **abstract** | Always. |

## The `read()` contract

`read()` must return the raw, PHP-session-encoded payload for `$id`. For a session
that does not exist yet, return an **empty string `''`**. Returning `false`
signals a genuine read failure; returning a serialized empty array (`a:0:{}`)
corrupts the session.

## Strict mode and lazy write

`AbstractAdapter` also implements PHP's `SessionUpdateTimestampHandlerInterface`,
so an adapter that only implements `read()`, `write()` and `destroy()` already
works with `session.use_strict_mode` and `session.lazy_write`:

- With strict mode on, PHP calls `validateId($id)` at session start with the id
  the client sent and replaces the id with a fresh one when it returns `false`.
  The default treats an id as valid when `read($id)` returns a non-empty payload,
  so an unknown or expired id is rejected. A session that holds no data yet
  reads as empty and is therefore also replaced; that is harmless (the new id
  carries the same empty data) and it keeps client-chosen ids out of your store.
  Override `validateId()` with a cheaper existence check (`EXISTS`, `SELECT 1`)
  when you can, and make sure an **expired** record counts as missing.
- With lazy write on (the default), PHP calls `updateTimestamp($id, $data)`
  instead of `write()` when the data did not change. The default calls
  `write()` again, which renews whatever expiry your store tracks. Override it
  with a touch/expire call if rewriting the payload is costly. If your store
  expires sessions by age, an implementation that does nothing here would let
  active sessions expire.

If your adapter turns the session id into a file name, a key with structure, or
a query, treat the id as untrusted input: `setID()` and a custom cookie can hand
you anything, and PHP does not restrict what `session_id()` accepts.

## A worked example — in-memory adapter

Great for tests, demos, and understanding the contract:

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

Use it like any bundled adapter:

```php
use InitPHP\Sessions\Session;

Session::createImmutable(new ArrayAdapter())->start();
```

## A more realistic example — APCu

```php
use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;

final class ApcuAdapter extends AbstractAdapter
{
    public function __construct(
        private int $ttl = 86400,
        private string $prefix = 'sess_',
    ) {
        if (!\function_exists('apcu_enabled') || !apcu_enabled()) {
            throw new SessionNotSupportedAdapter('The ApcuAdapter requires ext-apcu.');
        }
    }

    public function read(string $id): string|false
    {
        $value = apcu_fetch($this->prefix . $id, $ok);

        return $ok ? (string) $value : '';
    }

    public function write(string $id, string $data): bool
    {
        return apcu_store($this->prefix . $id, $data, $this->ttl);
    }

    public function destroy(string $id): bool
    {
        apcu_delete($this->prefix . $id);

        return true;
    }
}
```

(APCu has its own TTL, so leaving the default `gc()` — which deletes nothing — is
correct here.)

## Signalling failures

Use the package's exceptions so callers get a consistent surface — see
[Exceptions](Exceptions.md):

| Situation | Throw |
| --- | --- |
| Required extension/library missing | `SessionNotSupportedAdapter` |
| Bad / missing constructor options | `SessionInvalidArgumentException` |
| Backing store unreachable (connect/auth) | `SessionAdapterException` |

```php
use InitPHP\Sessions\Exceptions\SessionAdapterException;

if (!$this->client->ping()) {
    throw new SessionAdapterException('Backing store is unreachable.');
}
```

Because `SessionAdapterException extends SessionException`, callers can catch
everything with a single `catch (SessionException $e)`.

> Inside `read()`/`write()`/`destroy()` themselves, prefer returning `false`
> (or `''` from `read()`) over throwing — PHP's session machinery expects a
> boolean/string result there. Reserve throwing for **construction time**.

## See also

- [Adapters overview](Adapters.md)
- [API Reference → AbstractAdapter](API-Reference.md#sabstractadapter)
- [Exceptions](Exceptions.md)
