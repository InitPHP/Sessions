# Cookie Adapter

`InitPHP\Sessions\Adapters\CookieAdapter` stores the **entire** session payload
in an encrypted, base64-encoded cookie on the client. There is no server-side
storage at all — ideal for stateless/horizontally-scaled servers.

**Requirements:**
[`initphp/encryption`](https://github.com/InitPHP/Encryption)
(`composer require initphp/encryption`) and `ext-openssl`.

The payload is encrypted with AES-256-CTR + SHA-256 (via `initphp/encryption`'s
OpenSSL handler) before it ever leaves the server, so the client can hold the
data but cannot read or tamper with it.

## Options

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `name` | string | — | **Required.** Cookie name. |
| `key` | string | — | **Required.** Encryption key. Treat as a secret. |
| `ttl` | int | `86400` | Cookie lifetime in seconds. |
| `path` | string | `/` | Cookie path. |
| `domain` | string | `''` | Cookie domain. |
| `secure` | bool | `false` | Send only over HTTPS. |
| `httponly` | bool | `true` | Hide from JavaScript. |
| `samesite` | string | `Lax` | `Lax`, `Strict`, or `None`. |

Missing `name`/`key` throws a [`SessionInvalidArgumentException`](Exceptions.md);
a missing `initphp/encryption` library throws a
[`SessionNotSupportedAdapter`](Exceptions.md).

## Usage

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\CookieAdapter;

$adapter = new CookieAdapter([
    'name'     => 'app_session',
    'key'      => getenv('SESSION_KEY'), // keep this secret & stable
    'ttl'      => 86400,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

Session::createImmutable($adapter)->start();
```

## Security model

- **Confidentiality & integrity** come from `initphp/encryption`. A cookie that
  has been tampered with, corrupted, or encrypted with a different `key` fails to
  decrypt and is treated as an **empty session** (no exception is thrown).
- **`key` rotation** invalidates all existing cookies — rotate deliberately.
- **`secure`/`httponly`/`samesite`** default to safe-ish values (`httponly` on,
  `samesite=Lax`); set `secure => true` in production (HTTPS) and consider
  `samesite => 'Strict'` for sensitive apps.

## Strict mode and lazy write

The session data travels in the payload cookie, not on the server, so the session
id is not a lookup key here. With `session.use_strict_mode` on, an id counts as
known only when it is the id the client sent in its session cookie **and** the
same request carries a payload cookie that decrypts with your `key`. A first
visit, a missing, tampered or foreign-key payload cookie, or an id sent without
its payload therefore gets a fresh id. Ids PHP generates itself
(`session_create_id()`, `regenerateId()`) are never reported as taken, so they keep
working. Strict mode needs the id to travel in the session cookie
(`session.use_only_cookies`); an id passed in a URL or set with `setID()` is
replaced.

This keeps client-chosen ids out, but it is not an authentication check, and it
does not enforce a lifetime on the server: the payload carries no timestamp, so
`ttl` is enforced by the browser's cookie `expires` only. Anyone who holds a
valid payload cookie can pair it with any id, and a copied payload cookie stays
valid after `ttl` has passed or after logout until you rotate `key`. Keep the
cookie secret (`secure`, `httponly`) and still call `regenerateId(true)` after
login.

When the data did not change, `session.lazy_write` makes PHP call
`updateTimestamp()`, which sends the payload cookie again with a fresh `ttl`, so
the cookie's expiry slides with activity just as it does when the data changes.
`updateTimestamp()` itself never creates a payload cookie when the request carried none.

Because nothing is stored on the server, a parallel logout cannot be honoured: if
a request that only reads is still running when another request logs the user out
(deleting the payload cookie), the first response re-sends its payload cookie and
the user is signed in again.

## Constraints to keep in mind

- **Size.** Cookies are capped (~4 KB per cookie by browsers). Keep the payload
  small — store an id or a few flags, not large objects.
- **Every response re-sends the cookie**, so it travels on each request. Don't put
  large or rarely-changing data here.
- **`key` must be stable** across your fleet, or users on a different server will
  appear logged out.

If you need to store more than a few small values, use a server-side adapter
([Redis](Redis-Adapter.md), [PDO](PDO-Adapter.md), [MongoDB](MongoDB-Adapter.md)) instead.

## See also

- [Adapters overview](Adapters.md)
- [`initphp/encryption` wiki](https://github.com/InitPHP/Encryption/wiki)
