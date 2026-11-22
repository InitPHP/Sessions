# The Session Facade

`InitPHP\Sessions\Session` is the single entry point you interact with. It is a
**facade**: a thin layer that forwards calls to two small collaborators.

| Collaborator | Responsibility | Methods |
| --- | --- | --- |
| `Classes\Manager` | The session lifecycle (`session_*()` wrappers) | `start`, `isStarted`, `getName`, `setName`, `getID`, `setID`, `regenerateId`, `flush`, `unset`, `destroy` |
| `Classes\GetterSetter` | `$_SESSION` access | `has`, `get`, `set`, `push`, `pull`, `remove`, `delete`, `all`, `setAssoc` |

You never construct `Manager` or `GetterSetter` directly — the facade creates and
routes to them for you.

## Bootstrapping with `createImmutable()`

```php
use InitPHP\Sessions\Session;

Session::createImmutable();          // no save handler → PHP default
Session::createImmutable($adapter);  // custom save handler (an Adapter)
```

`createImmutable()` does two things:

1. Builds a fresh `Manager` (registering your adapter as the save handler) and a
   fresh `GetterSetter`.
2. Returns a `Session` **instance**, so you can chain straight into `start()`:

```php
Session::createImmutable($adapter)
    ->start();
```

Call it **once per request**, before you touch any other method. Calling it again
rebuilds the collaborators (handy in tests).

## Two call styles

Every method is exposed both **statically** and **on the instance**. Both are
routed to the same underlying objects, so they operate on the same session:

```php
$session = Session::createImmutable();

$session->start();              // instance
Session::set('user', 'ada');    // static
echo $session->get('user');     // 'ada' — same state
```

Internally this is powered by `__callStatic()` (static calls) and `__call()`
(instance calls), both delegating through a single router. The documented,
canonical style across this wiki is **static** (`Session::set(...)`), but use
whichever reads better.

## Accessing the collaborators directly

The instance exposes the two collaborators as read-only properties, should you
ever want to pass one around:

```php
$session = Session::createImmutable();

$session->manager;   // InitPHP\Sessions\Classes\Manager
$session->session;   // InitPHP\Sessions\Classes\GetterSetter
```

## What happens on an unknown method

The router knows exactly which methods belong to which collaborator. Anything
else raises a [`SessionException`](Exceptions.md):

```php
Session::createImmutable();
Session::doesNotExist();
// SessionException: "doesNotExist" method is not found.
```

## Return types worth knowing

| Call | Returns |
| --- | --- |
| `Session::createImmutable()` | `Session` (for chaining) |
| `Session::set(...)` / `push` excluded | `GetterSetter` (chain more value calls) |
| `Session::push(...)` | the stored **value** |
| `Session::setName(...)` | `Manager` (chain more lifecycle calls) |
| everything else | a scalar/array/bool as documented in the [API Reference](API-Reference.md) |

Because `set()` returns the `GetterSetter`, value writes chain naturally:

```php
Session::set('a', 1)->set('b', 2)->setAssoc(['c' => 3]);
```

## Next

- [Reading & Writing Values](Reading-And-Writing-Values.md)
- [Session Lifecycle](Session-Lifecycle.md)
- [API Reference](API-Reference.md)
