# Installation

## Requirements

| | |
| --- | --- |
| PHP | `^8.2` (tested on 8.2 – 8.6) |
| Core extensions | None |
| Adapter extensions | Depends on the adapter you choose — see below |

The package core has **zero runtime dependencies**. Each adapter only needs its
own backend extension, and only when you actually use it.

## Install via Composer

```bash
composer require initphp/sessions
```

Classes are autoloaded under the `InitPHP\Sessions` namespace via PSR-4:

```json
{
    "autoload": {
        "psr-4": {
            "InitPHP\\Sessions\\": "src/"
        }
    }
}
```

## Per-adapter requirements

Install only what the adapter you pick needs:

| Adapter | Needs |
| --- | --- |
| [File](File-Adapter.md) | Nothing extra |
| [Redis](Redis-Adapter.md) | `ext-redis` |
| [PDO](PDO-Adapter.md) | `ext-pdo` + a driver (`pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, …) |
| [Cookie](Cookie-Adapter.md) | `composer require initphp/encryption` + `ext-openssl` |
| [Memcache / Memcached](Memcache-Adapter.md) | `ext-memcached` (preferred) or `ext-memcache` |
| [MongoDB](MongoDB-Adapter.md) | `ext-mongodb` |

These are declared as `suggest` entries in `composer.json`, so Composer reminds
you about them but never forces them on.

## Verifying the install

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\Sessions\Session;

Session::createImmutable()->start();
Session::set('hello', 'world');

echo Session::get('hello'); // world
```

If the autoloader cannot find the class, re-run `composer dump-autoload` and make
sure your script requires `vendor/autoload.php` before using `Session`.

## Supported PHP versions in CI

Every pull request runs the test matrix against:

- PHP 8.2 (also with lowest dependencies)
- PHP 8.3
- PHP 8.4
- PHP 8.5
- PHP 8.6

Static analysis (PHPStan level 8) and coding-style (PER-CS) checks run on every
PR as well. If you hit a regression on a supported version, please
[file an issue](https://github.com/InitPHP/Sessions/issues/new) and include the
output of `php -v`.

## Next steps

- Take the five-minute tour in [Quick Start](Quick-Start.md).
- Understand the design in [The Session Facade](The-Session-Facade.md).
- Pick a backend in [Adapters](Adapters.md).
