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
| `read(string $id): string\|false` | **abstract** | Always. |
| `write(string $id, string $data): bool` | **abstract** | Always. |
| `destroy(string $id): bool` | **abstract** | Always. |

## The `read()` contract

`read()` must return the raw, PHP-session-encoded payload for `$id`. For a session
that does not exist yet, return an **empty string `''`**. Returning `false`
signals a genuine read failure; returning a serialized empty array (`a:0:{}`)
corrupts the session.

## Strict mode and lazy write

`AbstractAdapter` implements only PHP's `SessionHandlerInterface`, exactly as
before: a custom adapter that extends it keeps working unchanged, but PHP then
accepts any session id a client sends, even with `session.use_strict_mode` on, and
calls `write()` on every request even with `session.lazy_write` on.

To opt in, implement PHP's `SessionUpdateTimestampHandlerInterface` yourself:

```php
use InitPHP\Sessions\AbstractAdapter;

final class ArrayAdapter extends AbstractAdapter implements SessionUpdateTimestampHandlerInterface
{
    // read(), write(), destroy() as below ...

    public function validateId(string $id): bool
    {
        return isset($this->store[$id]); // an expired record must count as missing
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return true; // renew the lifetime here; see the rules below
    }
}
```

- **`validateId($id)`** is called by PHP with the id the client sent when
  `session.use_strict_mode` is on, and when PHP checks an id it has just generated
  (`session_create_id()`, `session_regenerate_id()`) for a collision. Return `true`
  only when a live session with exactly that id is stored. Treat an **expired**
  record as missing. If the store itself fails (connection lost), do not return
  `false`: that would replace the user's id over a transient outage; return `true`
  and let `read()` fail.
- **`updateTimestamp($id, $data)`** is called instead of `write()` when
  `session.lazy_write` is on (the default) and the data did not change. Renew the
  lifetime your store tracks, **never create the record**: if the session is gone
  (a parallel request logged out or regenerated the id) it must stay gone, so
  return `true` without writing. Do not simply call `write()` here.
- **Locking.** PHP calls `validateId()` and then `read()` for the same id without
  `close()` in between. If your `read()` takes a lock that it releases only in
  `close()`/`write()`, `validateId()` must not call `read()` and must not take the
  lock again, or the request will block on its own lock. Use a lock-free
  existence check.
- **The id is untrusted input** (`setID()`, a custom cookie and a hostile client
  can all supply anything, and PHP does not restrict what `session_id()` accepts).
  Validate it before using it as a file name, a key with structure or part of a
  query.

Adapters that extend one of the *bundled* adapters inherit these methods; see
[Extending a bundled adapter](#extending-a-bundled-adapter).

## Extending a bundled adapter

The bundled adapters implement `SessionUpdateTimestampHandlerInterface` with
public `validateId()` and `updateTimestamp()` methods, and with
`session.lazy_write` on PHP no longer calls `write()` for unchanged data. If your
subclass overrides `read()`/`write()`/`destroy()` (for example to prefix or
encrypt ids or payloads), override **both** new methods as well, or active
sessions stop being renewed and ids are looked up under the wrong key. A subclass
must not declare its own `validateId` or `updateTimestamp` with a different
signature or a non-public visibility.

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
