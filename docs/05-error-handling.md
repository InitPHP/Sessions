# 05 — Error Handling

All package exceptions live in `InitPHP\Sessions\Exceptions`.

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

| Exception | Thrown when | Catch as |
| --- | --- | --- |
| `SessionException` | A `session_*()` lifecycle call fails (e.g. `start()`, `destroy()`), or `flush()`/`unset()` is called without an active session, or the facade is asked for an unknown method. | itself |
| `SessionAdapterException` | An adapter cannot reach its backing store — connection, authentication or driver error. | itself **or** `SessionException` |
| `SessionInvalidArgumentException` | An adapter is constructed with missing or invalid options (e.g. no `table` for `PDOAdapter`, no `name`/`key` for `CookieAdapter`). | itself **or** `\InvalidArgumentException` |
| `SessionNotSupportedAdapter` | A required extension/library is unavailable (e.g. `ext-redis` for `RedisAdapter`, `initphp/encryption` for `CookieAdapter`). | itself **or** `\RuntimeException` |

## Catching everything

Because `SessionAdapterException` extends `SessionException`, a single catch
handles both lifecycle and store failures:

```php
use InitPHP\Sessions\Exceptions\SessionException;

try {
    Session::createImmutable($adapter)->start();
} catch (SessionException $e) {
    // lifecycle OR adapter failure
    error_log($e->getMessage());
}
```

Configuration problems and missing-dependency problems are intentionally **not**
`SessionException`s — they are programmer/environment errors you want to surface
loudly during development, not swallow at runtime:

```php
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;

try {
    $adapter = new RedisAdapter(['host' => '127.0.0.1']);
} catch (SessionNotSupportedAdapter $e) {
    // ext-redis is not installed
} catch (SessionInvalidArgumentException $e) {
    // an option was missing or invalid
}
```

## Adapter read/write failures

Inside the save-handler methods (`read()`, `write()`, `destroy()`), adapters do
**not** throw — PHP's session machinery expects a boolean / string result. A
failed operation returns `false` (or `''` from `read()` for an absent session),
which PHP surfaces through its own session warnings. Connection-time problems,
on the other hand, are raised as `SessionAdapterException` from the constructor.
