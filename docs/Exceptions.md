# Exceptions

All package exceptions live in the `InitPHP\Sessions\Exceptions` namespace.

## Hierarchy

```
\Exception
└── SessionException                  (base — lifecycle failures)
    └── SessionAdapterException       (adapter / backing-store failures)

\InvalidArgumentException
└── SessionInvalidArgumentException   (bad adapter options / arguments)

\RuntimeException
└── SessionNotSupportedAdapter        (missing extension or library)
```

The split is intentional:

- **Runtime, recoverable** failures (lifecycle + store) descend from
  `SessionException`, so one catch handles them.
- **Programmer / environment** failures (bad options, missing extension) are
  *not* `SessionException`s — you want them loud during development, not silently
  swallowed.

## The four types

| Exception | Thrown when | Also catchable as |
| --- | --- | --- |
| `SessionException` | A `session_*()` call fails (`start`, `destroy`, …); `flush`/`unset` called with no active session; an unknown facade method is invoked. | — |
| `SessionAdapterException` | An adapter cannot reach its backing store (connect/auth/driver error). | `SessionException` |
| `SessionInvalidArgumentException` | An adapter is built with missing/invalid options (e.g. no `table` for PDO, no `name`/`key` for Cookie). | `\InvalidArgumentException` |
| `SessionNotSupportedAdapter` | A required extension or library is unavailable (e.g. `ext-redis`, `initphp/encryption`). | `\RuntimeException` |

## Catching everything recoverable

Because `SessionAdapterException` extends `SessionException`, a single catch
covers both lifecycle and store failures:

```php
use InitPHP\Sessions\Exceptions\SessionException;

try {
    Session::createImmutable($adapter)->start();
} catch (SessionException $e) {
    error_log('Session unavailable: ' . $e->getMessage());
    // degrade gracefully, or show a 503
}
```

## Catching configuration / environment problems

Surface these early — usually at boot:

```php
use InitPHP\Sessions\Adapters\RedisAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;

try {
    $adapter = new RedisAdapter(['host' => '127.0.0.1', 'port' => 6379]);
} catch (SessionNotSupportedAdapter $e) {
    // ext-redis is not installed in this environment
} catch (SessionInvalidArgumentException $e) {
    // a required option is missing or malformed
}
```

## Examples per type

### `SessionException`

```php
Session::createImmutable();
Session::unset();
// SessionException: The session must be started for the "unset()" method to work.

Session::nope();
// SessionException: "nope" method is not found.
```

### `SessionInvalidArgumentException`

```php
use InitPHP\Sessions\Adapters\PDOAdapter;

new PDOAdapter(['pdo' => $pdo]); // no "table"
// SessionInvalidArgumentException: The "table" option is required.
```

### `SessionNotSupportedAdapter`

```php
use InitPHP\Sessions\Adapters\CookieAdapter;

new CookieAdapter(['name' => 'app', 'key' => 'secret']);
// SessionNotSupportedAdapter (when initphp/encryption is absent):
//   This adapter depends on the InitPHP Encryption library. Run:
//   composer require initphp/encryption
```

### `SessionAdapterException`

```php
use InitPHP\Sessions\Adapters\RedisAdapter;

new RedisAdapter(['host' => '10.0.0.9', 'port' => 6379]); // unreachable
// SessionAdapterException: Redis connection failed. ...
```

## Read/write don't throw

Inside the save-handler methods (`read()`, `write()`, `destroy()`), adapters do
**not** throw — PHP's session machinery expects a boolean/string result. A failed
operation returns `false` (or `''` from `read()` for an absent session).
Connection-time failures, by contrast, are raised as `SessionAdapterException`
from the constructor.

## Re-throwing from your own boundary

Wrapping the package? Re-throw as your own domain exception, preserving the
original as `$previous`:

```php
use InitPHP\Sessions\Exceptions\SessionException;

try {
    Session::createImmutable($adapter)->start();
} catch (SessionException $e) {
    throw new \App\SessionUnavailableException($e->getMessage(), 0, $e);
}
```

## See also

- [API Reference → Exceptions](API-Reference.md#exceptions-sexceptions)
- [Custom Adapters → Signalling failures](Custom-Adapters.md#signalling-failures)
