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

Read or set the session id. `setID()` must be called before `start()`. It
returns `true` when PHP accepted the id (so `getID()` now returns it) and `false`
when PHP refused it, for example because a session is already active or headers
were already sent:

```php
// Rarely needed: PHP takes the id from the session cookie on its own.
// Only ever use an id your own code generated.
$id = session_create_id(); // string|false

if ($id !== false && Session::setID($id)) {
    Session::start();
}

echo Session::getID();
```

With `session.use_strict_mode` on, PHP discards an id that has no stored session and
issues a new one, so `getID()` can differ from the id you set. Do not assume it
matches.

> **Never pass an id taken from the request** (a query parameter, a form field, a
> header) to `setID()`. That is session fixation: an attacker who can make a
> victim adopt a known id then shares their session once they log in.

### Strict mode and lazy write

The adapters that ship with this package (File, PDO, Redis, Memcache(d), MongoDB
and Cookie) support `session.use_strict_mode` and `session.lazy_write`. They
implement PHP's `SessionUpdateTimestampHandlerInterface`, which PHP uses for two
things:

- **Strict mode.** At `start()`, PHP asks the adapter whether the id the client
  sent belongs to a stored session (`validateId()`). If it does not (an id the
  server never issued, or a session that has expired), PHP discards it and issues
  a fresh id, so an attacker cannot make a victim adopt an id of the attacker's
  choosing. Turn it on with `session.use_strict_mode=1` (the recommended setting)
  or per call: `Session::start(['use_strict_mode' => 1])`.
- **Lazy write.** When a request does not change the session data, PHP skips
  `write()` and calls `updateTimestamp()` instead, which renews the session's
  lifetime (file modification time, row/document timestamp, key TTL, cookie
  expiry) without rewriting the payload. On the server-side stores it only renews: a
  session that was destroyed or regenerated in the meantime is not brought back
  (the cookie adapter has no server-side record and cannot honour that).
  `session.lazy_write` is on by default.

How "exists" is decided depends on the store: see the *Strict mode and lazy
write* section of each adapter page ([File](File-Adapter.md),
[Redis](Redis-Adapter.md), [PDO](PDO-Adapter.md), [Cookie](Cookie-Adapter.md),
[Memcache(d)](Memcache-Adapter.md), [MongoDB](MongoDB-Adapter.md)). A
[custom adapter](Custom-Adapters.md#strict-mode-and-lazy-write) does **not** get
this automatically: `AbstractAdapter` is unchanged, and you opt in by
implementing the interface yourself.

Strict mode is not a replacement for
[`regenerateId(true)`](#regenerateidbool-deleteoldsession--false-bool): call it
right after login and after every privilege change, because an id the server
*did* issue can still be known to an attacker.

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
