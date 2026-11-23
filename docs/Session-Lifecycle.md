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

Read or set the session id. `setID()` returns whether the id was accepted, and
must be called before `start()`:

```php
// Only ever with an id your own code generated.
Session::setID(session_create_id());
Session::start();

echo Session::getID();
```

> **Never pass an id taken from the request** (a query parameter, a form field, a
> header) to `setID()`. That is session fixation: an attacker who can make a
> victim adopt a known id then shares their session once they log in. Normally
> you do not need `setID()` at all, because PHP reads the id from the session
> cookie on its own; keep `session.use_strict_mode` on so PHP refuses ids it did
> not issue.

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
