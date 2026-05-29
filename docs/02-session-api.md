# 02 — Session API

Every method below is callable statically (`Session::get(...)`) or on the
instance returned by `createImmutable()`. Value methods are delegated to
`GetterSetter`; lifecycle methods to `Manager`.

## Bootstrapping

### `createImmutable(?SessionHandlerInterface $adapter = null): self`

Initialises the facade and returns an instance for chaining. Call it once per
request. Passing an adapter registers it as the save handler on `start()`.

```php
Session::createImmutable($adapter)->start();
```

## Value methods (`GetterSetter`)

These operate on `$_SESSION` and are null-safe: before the session is started,
reads return their defaults rather than erroring.

### `has(string $key): bool`

```php
Session::has('user'); // true / false
```

### `set(string $key, mixed $value): GetterSetter`

Stores a value; returns the `GetterSetter` for chaining.

```php
Session::set('a', 1)->set('b', 2);
```

### `get(string $key, mixed $default = null): mixed`

Returns the stored value, or `$default` when the key is absent. A stored `null`
is returned as-is (not replaced by the default).

```php
Session::get('user', 'guest');
```

### `push(string $key, mixed $value): mixed`

Stores a value and returns the **value** (not `$this`), handy in expressions.

```php
$token = Session::push('csrf', bin2hex(random_bytes(16)));
```

### `pull(string $key, mixed $default = null): mixed`

Returns the value and removes the key — useful for one-shot "flash" data.

```php
$notice = Session::pull('flash', null);
```

### `remove(string ...$keys): GetterSetter` / `delete(...)`

Removes one or more keys. `delete()` is an alias of `remove()`.

```php
Session::remove('a', 'b');
```

### `all(): array`

Returns the entire session payload.

```php
foreach (Session::all() as $key => $value) { /* ... */ }
```

### `setAssoc(array $assoc, bool $reset = false): GetterSetter`

Bulk-writes an associative array. Only **string** keys are written; numeric keys
are skipped. With `$reset = true` the session is replaced rather than merged.

```php
Session::setAssoc(['user' => 'ada', 'role' => 'admin']);
Session::setAssoc(['only' => 'this'], true); // replaces everything
```

## Lifecycle methods (`Manager`)

### `start(array $options = []): bool`

Registers the adapter (if any) and starts the session. `$options` are forwarded
to `session_start()`.

```php
Session::start(['cookie_lifetime' => 3600]);
```

### `isStarted(): bool`

```php
if (!Session::isStarted()) {
    Session::start();
}
```

### `getName(): string` / `setName(string $name): Manager`

Read or set the session name. Call `setName()` **before** `start()`.

```php
Session::setName('APPSESSID');
echo Session::getName(); // "APPSESSID"
```

### `getID(): string` / `setID(string $id): bool`

Read or set the session id. Call `setID()` before `start()`. `setID()` returns
whether the id was accepted.

### `regenerateId(bool $deleteOldSession = false): bool`

Issues a fresh session id — call it after a privilege change (e.g. login) to
prevent fixation.

```php
Session::regenerateId(true);
```

### `flush(): bool` / `unset(): bool`

Clear all session variables. Both require an active session and throw
`SessionException` otherwise. `flush()` is an alias of `unset()`.

```php
Session::flush();
```

### `destroy(): bool`

Destroys the session entirely.

```php
Session::destroy();
```
