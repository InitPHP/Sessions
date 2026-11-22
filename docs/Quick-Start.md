# Quick Start

A five-minute tour of the public API. Every snippet runs as-is once you have
required `vendor/autoload.php`.

## 1. Bootstrap and start

Call `createImmutable()` once per request, then `start()`:

```php
use InitPHP\Sessions\Session;

Session::createImmutable()
    ->start();
```

With no adapter, PHP's default save handler is used. To store sessions elsewhere,
pass an adapter — see [Adapters](Adapters.md):

```php
use InitPHP\Sessions\Adapters\FileAdapter;

Session::createImmutable(new FileAdapter(['path' => __DIR__ . '/storage']))
    ->start();
```

## 2. Write values

`set()` is chainable; `push()` returns the value you just stored:

```php
Session::set('username', 'admin')
    ->set('role', 'editor');

$token = Session::push('csrf', bin2hex(random_bytes(16)));
```

## 3. Read values

```php
Session::get('username');             // 'admin'
Session::get('missing');              // null
Session::get('theme', 'light');       // 'light' (default)

Session::has('role');                 // true
Session::all();                       // ['username' => 'admin', 'role' => 'editor', ...]
```

A stored `null` is returned as-is — the default only applies to **missing** keys:

```php
Session::set('nickname', null);
Session::get('nickname', 'anon');     // null, not 'anon'
```

## 4. Remove & one-shot reads

```php
Session::remove('csrf');              // delete a key (alias: delete())

$flash = Session::pull('flash');      // read, then remove — perfect for flash data
```

## 5. Bulk writes

`setAssoc()` writes many string-keyed values at once. Pass `true` to replace the
whole session instead of merging:

```php
Session::setAssoc([
    'username' => 'admin',
    'role'     => 'editor',
]);

Session::setAssoc(['only' => 'this'], true); // wipes everything else
```

## 6. Lifecycle

```php
Session::isStarted();        // true

Session::regenerateId(true); // rotate the id (e.g. right after login)

Session::flush();            // clear all values (session stays open)
Session::destroy();          // tear the session down entirely
```

## Static or instance — your choice

After `createImmutable()`, every method works both ways, against the same
session state:

```php
$session = Session::createImmutable();
$session->start();             // instance call
Session::set('user', 'ada');   // static call — same session
echo $session->get('user');    // 'ada'
```

## You can still use `$_SESSION`

The facade does not hide the superglobal:

```php
$_SESSION['cart'] = ['sku-1', 'sku-2'];
echo count(Session::get('cart')); // 2
```

## Where to go next

- [The Session Facade](The-Session-Facade.md) — how the pieces fit together.
- [Reading & Writing Values](Reading-And-Writing-Values.md) — every value method.
- [Session Lifecycle](Session-Lifecycle.md) — start, regenerate, flush, destroy.
- [Adapters](Adapters.md) — choose a storage backend.
