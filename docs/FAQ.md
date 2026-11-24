# FAQ

## Does this replace `$_SESSION`?

No. It's a convenience layer **over** native PHP sessions. `Session::set('x', 1)`
and `$_SESSION['x'] = 1` are equivalent, and you can mix both freely. The value of
the facade is the fluent API, predictable [exceptions](Exceptions.md), and pluggable
[adapters](Adapters.md).

## Why is it called `createImmutable()`?

It bootstraps the facade once and hands you an instance to chain from. Think of it
as "set up the session machinery for this request." Call it a single time, before
any other method.

## Static calls or instance calls — which should I use?

Both work and operate on the same session. The wiki uses the **static** style
(`Session::set(...)`) because it reads well and matches PHP's own
`session_*()` mental model. Use instance calls if you prefer dependency-style
passing — see [The Session Facade](The-Session-Facade.md#two-call-styles).

## Can I use more than one adapter at once?

No — a PHP session has exactly one save handler. Pick one adapter per request and
pass it to `createImmutable()`. You *can* choose the adapter dynamically (e.g.
from config) at bootstrap; see the [Recipes](Recipes.md#choosing-where-to-start-a-session).

## How do sessions expire?

It depends on the adapter:

- **[Redis](Redis-Adapter.md) / [Memcache(d)](Memcache-Adapter.md)** — per-key/item TTL;
  the backend evicts them.
- **[File](File-Adapter.md) / [PDO](PDO-Adapter.md) / [MongoDB](MongoDB-Adapter.md)** — via
  `gc()`, on PHP's probability-based GC schedule (or a cron you run yourself).
- **[Cookie](Cookie-Adapter.md)** — the cookie's own lifetime, enforced by the
  browser.

Remember the session **cookie** lifetime (`session.cookie_lifetime`) is separate
from the storage TTL — align them to avoid "logged out but data still there" (or
vice versa) surprises.

## How do I log a user out everywhere / globally?

With a server-side adapter you can clear the store directly. For
[PDO](Recipe-Database-Sessions.md#4-operational-queries): `DELETE FROM sessions;`.
For Redis: delete the keys under your `prefix`. Per-user global logout needs a
lookup you maintain (store session ids by user id at login).

## Is the Cookie adapter safe? How big can it get?

The payload is encrypted (AES-256-CTR + SHA-256) via `initphp/encryption`, so
clients can't read or tamper with it; a bad cookie is treated as an empty
session. But it lives in the browser, so keep it **small** (cookies are ~4 KB) and
keep the `key` secret and stable across servers. See [Cookie Adapter](Cookie-Adapter.md).

## I get "The session must be started for the ... method to work."

`flush()` and `unset()` require an active session. Call `start()` first, or guard
with `isStarted()`. See [Session Lifecycle](Session-Lifecycle.md).

## I get "method is not found."

The facade only routes its documented methods. Check the spelling against the
[API Reference](API-Reference.md); a typo (or a method that doesn't exist) raises
`SessionException`.

## My adapter throws on construction — why?

That's by design — construction-time failures are loud:

- missing extension/library → `SessionNotSupportedAdapter`
- missing/invalid option → `SessionInvalidArgumentException`
- unreachable backend → `SessionAdapterException`

See [Exceptions](Exceptions.md).

## Why do `read()`/`write()` return `false` instead of throwing?

PHP's session machinery calls save handlers and expects boolean/string results,
not exceptions. So adapters return `false` (or `''` from `read()` for an absent
session) at runtime, and reserve throwing for **construction time**.

## How do I test code that uses sessions?

- Pure `$_SESSION` logic (the [`GetterSetter`](Reading-And-Writing-Values.md) methods)
  can be tested by setting `$_SESSION` directly — no real session needed.
- Tests that call `start()`/`destroy()` should run in an isolated process
  (PHPUnit's `#[RunInSeparateProcess]` + `#[PreserveGlobalState(false)]`) and pass
  `['use_cookies' => '0']` to avoid header output.
- For adapters, the [File](File-Adapter.md) adapter (temp dir) and
  [PDO](PDO-Adapter.md) adapter (SQLite `:memory:`) test cleanly without external
  services.

## Is it PSR-6 / PSR-16 compatible?

No — those are caching standards. This package targets PHP's session model. The
adapters implement PHP's native `SessionHandlerInterface` (via
[`AdapterInterface`](API-Reference.md#sinterfacesadapterinterface)), so they plug
straight into `session_set_save_handler()`.

## Can I write my own adapter?

Yes — extend `AbstractAdapter` and implement `read`/`write`/`destroy`. See
[Custom Adapters](Custom-Adapters.md).

## Does `session.use_strict_mode` work with the adapters?

Yes. The adapters tell PHP whether an id belongs to a stored session, so an id the
server never issued (or whose session expired) is replaced with a fresh one. See
[Strict mode and lazy write](Session-Lifecycle.md#strict-mode-and-lazy-write).

## Where do I report bugs or ask questions?

- Bugs: [GitHub issues](https://github.com/InitPHP/Sessions/issues)
- Questions/ideas: [InitPHP discussions](https://github.com/orgs/InitPHP/discussions)
- Security: see the [Security Policy](https://github.com/InitPHP/.github/blob/main/SECURITY.md)
