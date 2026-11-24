# Session Lifecycle

These methods wrap PHP's native `session_*()` functions behind the
[`Manager`](The-Session-Facade.md), reachable through the facade
(`Session::start(...)`). Each native call is guarded so failures surface as a
[`SessionException`](Exceptions.md) rather than a bare warning.

## `start(array $options = []): bool`

Registers your adapter (if you passed one to `createImmutable()`) and starts the
session. `$options` are forwarded verbatim to
[`session_start()`](https://www.php.net/session_start).

```php
Session::createImmutable($adapter)
    ->start([
        'cookie_lifetime' => 3600,
        'cookie_httponly' => true, // PHP's default is off
        'cookie_secure'   => true,
        'cookie_samesite' => 'Lax',
    ]);
```

Call `setName()` / `setID()` **before** `start()` if you need them.

## `isStarted(): bool`

True when a session is currently active (`PHP_SESSION_ACTIVE`).

```php
if (!Session::isStarted()) {
    Session::start();
}
```

## `getName(): string` and `setName(string $name): Manager`

Read or change the session name (the cookie name PHP uses). Set it before
starting:

```php
Session::setName('APPSESSID');
Session::start();

echo Session::getName(); // 'APPSESSID'
```

`setName()` returns the `Manager`, so lifecycle calls chain.

## `getID(): string` and `setID(string $id): bool`

Read or set the session id. `setID()` must be called before `start()`. Do not
rely on its return value (it currently reports `false` even when the id was
applied); read `getID()` instead:

```php
// Rarely needed: PHP takes the id from the session cookie on its own.
// Only ever use an id your own code generated.
$id = session_create_id(); // string|false

if ($id !== false) {
    Session::setID($id);
}
Session::start();

echo Session::getID();
```

With PHP's native save handler and `session.use_strict_mode` on, PHP discards an id
that has no stored session and issues a new one, so `getID()` can differ from the
id you set. Do not assume it matches.

> **Never pass an id taken from the request** (a query parameter, a form field, a
> header) to `setID()`. That is session fixation: an attacker who can make a
> victim adopt a known id then shares their session once they log in.
>
> **`session.use_strict_mode` does not protect the package's adapters.** With
> PHP's native handlers, strict mode makes PHP refuse a session id it did not
> issue. It does that by asking the save handler to validate the id
> (`validateId()`), and the adapters in this package implement only the basic
> `SessionHandlerInterface`; PHP then accepts any id a client sends in the
> cookie. When you use an adapter, the protection against fixation is to call
> [`regenerateId(true)`](#regenerateidbool-deleteoldsession--false-bool) right
> after login and after every privilege change. Keep strict mode on anyway; it
> costs nothing and covers the native handler.

`getID()` returns an empty string when there is no id yet.

## `regenerateId(bool $deleteOldSession = false): bool`

Issues a fresh session id while keeping the current data. Call it after any
privilege change — most importantly right after login — to defeat
**session fixation**. Pass `true` to delete the old session record.

```php
// after verifying credentials:
Session::regenerateId(true);
Session::set('user_id', $user->id);
```

See the [Authentication recipe](Recipe-Authentication.md) for the full pattern.

## `flush(): bool` and `unset(): bool`

Clear **all** session variables while keeping the session open. `flush()` is an
alias of `unset()`. Both require an active session and throw a
[`SessionException`](Exceptions.md) otherwise:

```php
Session::flush();
Session::all(); // []
```

```php
// Without an active session:
Session::unset();
// SessionException: The session must be started for the "unset()" method to work.
```

## `destroy(): bool`

Tears the session down entirely (`session_destroy()`):

```php
Session::destroy();
```

A complete logout usually combines clearing data, regenerating the id, and
destroying:

```php
Session::flush();
Session::regenerateId(true);
Session::destroy();
```

## Lifecycle at a glance

| Method | Returns | Needs active session | Typical use |
| --- | --- | :---: | --- |
| `start` | `bool` | — | Begin the session |
| `isStarted` | `bool` | — | Guard before starting |
| `getName` / `setName` | `string` / `Manager` | before start | Custom cookie name |
| `getID` / `setID` | `string` / `bool` | before start | Set an id your own code generated (never one from the request) |
| `regenerateId` | `bool` | ✅ | After login / privilege change |
| `flush` / `unset` | `bool` | ✅ (throws otherwise) | Clear data, keep session |
| `destroy` | `bool` | ✅ | Logout / teardown |

## Next

- [Reading & Writing Values](Reading-And-Writing-Values.md)
- [Recipe: Authentication & Fixation](Recipe-Authentication.md)
- [Exceptions](Exceptions.md)
