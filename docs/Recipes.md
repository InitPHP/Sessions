# Recipes

Practical, copy-pasteable patterns built on the public API. Each recipe is
self-contained and links back to the reference material for the methods it uses.

| Recipe | Solves |
| --- | --- |
| [Authentication & Fixation](Recipe-Authentication.md) | Login/logout done safely, with id regeneration. |
| [Flash Messages](Recipe-Flash-Messages.md) | One-shot notices that survive exactly one redirect. |
| [Database-Backed Sessions](Recipe-Database-Sessions.md) | Storing sessions in MySQL/PostgreSQL/SQLite via PDO. |

If you build something reusable on top of the package, please
[open an issue](https://github.com/InitPHP/Sessions/issues/new) with a sketch —
recipe contributions are welcome.

## Choosing where to start a session

A common bootstrap that picks an adapter from configuration:

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\{FileAdapter, RedisAdapter, PDOAdapter};

$adapter = match (getenv('SESSION_DRIVER') ?: 'file') {
    'redis' => new RedisAdapter(['host' => '127.0.0.1', 'port' => 6379, 'ttl' => 86400]),
    'pdo'   => new PDOAdapter(['dsn' => getenv('DB_DSN'), 'username' => getenv('DB_USER'), 'password' => getenv('DB_PASS'), 'table' => 'sessions']),
    default => new FileAdapter(['path' => __DIR__ . '/storage/sessions']),
};

Session::createImmutable($adapter)->start();
```

## Always start before you read

A tiny guard you can call from a front controller:

```php
if (!Session::isStarted()) {
    Session::createImmutable($adapter)->start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        // Always Secure; $_SERVER['HTTPS'] is empty behind a TLS-terminating proxy.
        // Turn it off only for local development, through your config.
        'cookie_secure'   => true,
    ]);
}
```
