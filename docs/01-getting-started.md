# 01 — Getting Started

## Install

```bash
composer require initphp/sessions
```

Requires PHP 8.1+. Individual adapters may need extra extensions or libraries;
see [03 — Adapters](./03-adapters.md).

## The facade

`InitPHP\Sessions\Session` is a thin facade over two collaborators:

- a **`Manager`** that wraps PHP's `session_*()` lifecycle functions, and
- a **`GetterSetter`** that wraps the `$_SESSION` superglobal.

You bootstrap it once per request with `createImmutable()`, optionally handing it
a save handler (an adapter). With no adapter, PHP's configured default handler is
used.

```php
require_once 'vendor/autoload.php';

use InitPHP\Sessions\Session;

Session::createImmutable()
    ->start();
```

`createImmutable()` returns a `Session` instance, which is why the
`createImmutable()->start()` chaining works.

## Two call styles, one state

After bootstrapping, every method works **statically** or on the **instance** —
both are routed to the same underlying `Manager`/`GetterSetter`.

```php
$session = Session::createImmutable();
$session->start();          // instance call
Session::set('user', 'ada'); // static call — same session
echo $session->get('user');  // "ada"
```

Use whichever reads better in your codebase. The static style matches the
examples throughout these docs.

## Your first values

```php
Session::createImmutable()->start();

Session::set('username', 'admin')
    ->set('mail', 'admin@example.com');

if (Session::has('username')) {
    echo Session::get('username');          // "admin"
}

echo Session::get('theme', 'light');         // "light" (default)
```

You can always fall back to `$_SESSION` directly — the facade does not replace
it:

```php
$_SESSION['cart'] = ['sku-1', 'sku-2'];
echo count(Session::get('cart'));            // 2
```

## Using an adapter

Pass any adapter to `createImmutable()` to change where sessions are stored:

```php
use InitPHP\Sessions\Adapters\FileAdapter;

Session::createImmutable(new FileAdapter(['path' => __DIR__ . '/storage']))
    ->start();
```

See [03 — Adapters](./03-adapters.md) for every backend.

## Next

- [02 — Session API](./02-session-api.md) — the full method reference.
- [05 — Error Handling](./05-error-handling.md) — what can go wrong.
