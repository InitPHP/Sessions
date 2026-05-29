# 04 — Custom Adapters

A custom save handler is any class that implements PHP's
`SessionHandlerInterface`. The easiest path is to extend
`InitPHP\Sessions\AbstractAdapter`, which provides defaults for the methods most
stores do not need to customise.

## What `AbstractAdapter` gives you

| Method | Default | Override when |
| --- | --- | --- |
| `open(string $path, string $name): bool` | returns `true` | You need to open a connection per request. |
| `close(): bool` | returns `true` | You need to close a connection. |
| `gc(int $max_lifetime): int\|false` | returns `0` (deletes nothing) | Your store has no built-in expiry. |
| `read(string $id): string\|false` | **abstract** | Always. |
| `write(string $id, string $data): bool` | **abstract** | Always. |
| `destroy(string $id): bool` | **abstract** | Always. |

## The `read()` contract

`read()` must return the raw, PHP-session-encoded payload for `$id`. For a
session that does not exist yet, return an **empty string** `''`. Returning
`false` signals a genuine read failure; returning a serialized empty array
(`a:0:{}`) corrupts the session.

## A worked example

An in-memory adapter (handy for tests):

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

## Signalling failures

When your backing store is unreachable, throw
`InitPHP\Sessions\Exceptions\SessionAdapterException` (it extends
`SessionException`, so callers can catch everything in one place):

```php
use InitPHP\Sessions\Exceptions\SessionAdapterException;

if (!$this->client->isConnected()) {
    throw new SessionAdapterException('Backing store is unreachable.');
}
```

If your adapter depends on an extension or library that may be missing, validate
in the constructor and throw `SessionNotSupportedAdapter`. Validate bad options
with `SessionInvalidArgumentException`. See
[05 — Error Handling](./05-error-handling.md).
