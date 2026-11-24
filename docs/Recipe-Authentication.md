# Recipe: Authentication & Fixation

Logging a user in is the most security-sensitive thing you'll do with sessions.
The golden rule: **regenerate the session id the moment the privilege level
changes** — i.e. right after you verify credentials. This defeats
[session fixation](https://owasp.org/www-community/attacks/Session_fixation).

Uses: [`regenerateId`](Session-Lifecycle.md#regenerateidbool-deleteoldsession--false-bool),
[`set`/`get`](Reading-And-Writing-Values.md),
[`flush`/`destroy`](Session-Lifecycle.md).

## Bootstrap

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\RedisAdapter;

Session::createImmutable(new RedisAdapter([
    'host' => '127.0.0.1',
    'port' => 6379,
    'ttl'  => 86400,
]))->start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    // Always Secure. Do not derive this from $_SERVER['HTTPS']: behind a proxy or
    // load balancer that terminates TLS it is empty, and the flag would silently
    // be dropped. Switch it off only for local development, through your config.
    'cookie_secure'   => true,
]);
```

## Login

```php
function login(string $email, string $password): bool
{
    $user = findUserByEmail($email);

    if ($user === null || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // 1. Rotate the id — old fixated ids become useless.
    Session::regenerateId(true);

    // 2. Store the minimum needed to identify the user.
    Session::set('user_id', $user['id'])
        ->set('logged_in_at', time());

    return true;
}
```

Store an **id**, not the whole user record — reload the user per request from the
id, so permission changes take effect immediately.

## Reading the current user

```php
function currentUserId(): ?int
{
    return Session::get('user_id');
}

function requireLogin(): void
{
    if (currentUserId() === null) {
        header('Location: /login');
        exit;
    }
}
```

## Logout

Clear the data, rotate the id, and destroy the session:

```php
function logout(): void
{
    Session::flush();            // clear all values
    Session::regenerateId(true); // new empty id, delete the old record
    Session::destroy();          // tear down
}
```

## Optional: idle & absolute timeouts

```php
const IDLE_TTL     = 1800;   // 30 min of inactivity
const ABSOLUTE_TTL = 28800;  // 8 h hard cap

function enforceTimeouts(): void
{
    $now  = time();
    $last = Session::get('last_seen', $now);
    $born = Session::get('logged_in_at', $now);

    if ($now - $last > IDLE_TTL || $now - $born > ABSOLUTE_TTL) {
        logout();
        return;
    }

    Session::set('last_seen', $now);
}
```

## Optional: bind to client IP (PDO)

The [PDO adapter](PDO-Adapter.md#binding-sessions-to-the-client-ip) can refuse to
load a session from a different IP than created it:

```php
new PDOAdapter(['pdo' => $pdo, 'table' => 'sessions', 'withIPAddress' => true]);
```

Use with care behind proxies and for mobile clients (see the adapter's warning).

## Checklist

- ✅ `regenerateId(true)` immediately after a successful login.
- ✅ `session.use_strict_mode=1`, so PHP replaces any id the server never issued
  (see [Strict mode and lazy write](Session-Lifecycle.md#strict-mode-and-lazy-write)).
- ✅ Store an id, reload the user each request.
- ✅ `cookie_httponly`, `cookie_secure` (always on; off only for local development), and a sensible `cookie_samesite`.
- ✅ `flush()` + `regenerateId(true)` + `destroy()` on logout.

## See also

- [Session Lifecycle](Session-Lifecycle.md)
- [Cookie Adapter](Cookie-Adapter.md) — for stateless deployments.
