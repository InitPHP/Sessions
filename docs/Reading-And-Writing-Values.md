# Reading & Writing Values

These methods wrap the `$_SESSION` superglobal. They live on `GetterSetter` and
are reachable through the [facade](The-Session-Facade.md) — `Session::get(...)` — or
on the object returned by `set()`/`Session::createImmutable()`.

All reads are **null-safe**: if the session has not been started (so `$_SESSION`
is not populated), reads return their defaults instead of raising a type error.

## `has(string $key): bool`

```php
Session::has('user'); // true / false
```

## `set(string $key, mixed $value): GetterSetter`

Stores a value and returns the `GetterSetter`, so writes chain:

```php
Session::set('user', 'ada')
    ->set('role', 'admin')
    ->set('prefs', ['theme' => 'dark']);
```

Any serialisable value is allowed — strings, ints, arrays, objects:

```php
Session::set('cart', ['sku-1', 'sku-2']);
```

## `get(string $key, mixed $default = null): mixed`

Returns the stored value, or `$default` when the key is **missing**:

```php
Session::get('user');           // 'ada'
Session::get('missing');        // null
Session::get('locale', 'en');   // 'en'
```

A stored `null` is a real value and is returned as-is:

```php
Session::set('mute', null);
Session::get('mute', false);    // null, not false
```

## `push(string $key, mixed $value): mixed`

Like `set()`, but returns the **value** rather than the bag — handy in
expressions:

```php
$token = Session::push('csrf', bin2hex(random_bytes(16)));
header('X-CSRF: ' . $token);
```

## `pull(string $key, mixed $default = null): mixed`

Reads a key **and removes it** — the canonical "flash" primitive:

```php
Session::set('flash', 'Saved!');

// ... next request ...
$message = Session::pull('flash');   // 'Saved!', then gone
Session::has('flash');               // false
```

If the key is absent, `pull()` returns `$default` and changes nothing.

## `remove(string ...$keys): GetterSetter` and `delete(...)`

Removes one or more keys. `delete()` is an exact alias of `remove()`. Removing a
missing key is a silent no-op.

```php
Session::remove('csrf');
Session::remove('a', 'b', 'c');
Session::delete('legacy');           // same thing
```

## `all(): array`

Returns the entire session payload as an array:

```php
foreach (Session::all() as $key => $value) {
    // ...
}
```

Before the session is started this returns `[]`.

## `setAssoc(array $assoc, bool $reset = false): GetterSetter`

Bulk-writes an associative array. **Only string keys are written** — numeric keys
are silently skipped, so you can pass mixed arrays safely.

```php
Session::setAssoc([
    'user' => 'ada',
    'role' => 'admin',
    0      => 'ignored',   // numeric key → skipped
]);
```

With `$reset = true`, the session is **replaced** by `$assoc` rather than merged:

```php
Session::setAssoc(['only' => 'this'], true);
Session::all(); // ['only' => 'this']
```

## Falling back to `$_SESSION`

The facade does not replace the superglobal — mix and match freely:

```php
$_SESSION['counter'] = ($_SESSION['counter'] ?? 0) + 1;
echo Session::get('counter');
```

## Summary

| Method | Returns | Removes? | Default on miss |
| --- | --- | :---: | :---: |
| `has` | `bool` | — | — |
| `get` | value | — | ✅ |
| `set` | `GetterSetter` | — | — |
| `push` | the value | — | — |
| `pull` | value | ✅ | ✅ |
| `remove` / `delete` | `GetterSetter` | ✅ | — |
| `all` | `array` | — | `[]` |
| `setAssoc` | `GetterSetter` | optional (`$reset`) | — |

## Next

- [Session Lifecycle](Session-Lifecycle.md) — start, regenerate, destroy.
- [Recipe: Flash Messages](Recipe-Flash-Messages.md) — `pull()` in practice.
- [API Reference](API-Reference.md) — exhaustive signatures.
