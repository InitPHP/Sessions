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
