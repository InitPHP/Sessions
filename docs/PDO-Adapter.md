# PDO Adapter

`InitPHP\Sessions\Adapters\PDOAdapter` stores sessions in a relational database
through PDO. Writes use a **portable upsert** (select, then insert or update), so
the same code works on MySQL, PostgreSQL and SQLite.

**Requirements:** `ext-pdo` and a driver (`pdo_mysql`, `pdo_pgsql`,
`pdo_sqlite`, …).

## Options

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `pdo` | `PDO` | — | An existing connection. |
| `dsn` | string | — | Used to connect when `pdo` is absent. |
| `username` | string | — | With `dsn`. |
| `password` | string | — | With `dsn`. |
| `table` | string | — | **Required.** Table that holds sessions. |
| `withIPAddress` | bool | `false` | Also match the client IP on read/destroy. |

Either `pdo` **or** `dsn` is required. Missing `table`, or neither connection
source, throws a [`SessionInvalidArgumentException`](Exceptions.md); a failed `dsn`
connection throws a [`SessionAdapterException`](Exceptions.md).

## The table

```sql
CREATE TABLE `sessions` (
    `id`              VARCHAR(255) NOT NULL,
    `sess_timestamp`  DATETIME NULL DEFAULT NULL,
    `sess_ip_address` VARCHAR(48) DEFAULT NULL,
    `sess_data`       TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

For PostgreSQL/SQLite use the equivalent column types (`TIMESTAMP`/`TEXT`); the
adapter's SQL is otherwise portable.

## Usage — existing connection

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\PDOAdapter;

$pdo = new PDO('mysql:host=localhost;dbname=app', 'root', '');

$adapter = new PDOAdapter([
    'pdo'   => $pdo,
    'table' => 'sessions',
]);

Session::createImmutable($adapter)->start();
```

## Usage — let the adapter connect

```php
$adapter = new PDOAdapter([
    'dsn'      => 'pgsql:host=localhost;dbname=app',
    'username' => 'postgres',
    'password' => 'secret',
    'table'    => 'sessions',
]);
```

## Binding sessions to the client IP

With `withIPAddress => true`, the client IP (`$_SERVER['REMOTE_ADDR']`, or
`0.0.0.0` when absent) is stored on write and used as an extra match condition on
read and destroy. A session is then only usable from the IP that created it —
extra defence against stolen-cookie replay:

```php
$adapter = new PDOAdapter([
    'pdo'           => $pdo,
    'table'         => 'sessions',
    'withIPAddress' => true,
]);
```

> ⚠️ Behind proxies/load balancers `REMOTE_ADDR` may be the proxy's IP, and
> mobile clients change IP often. Enable this only when your network topology
> makes it reliable.

## Garbage collection

`gc()` deletes rows whose `sess_timestamp` is older than `gc_maxlifetime` and
returns the number removed. It runs on PHP's normal GC schedule, or manually:

```php
$deleted = $adapter->gc(1440); // remove sessions older than 24 minutes
```

## Behaviour notes

- `read()` returns `sess_data`, or `''` for an unknown session.
- `write()` upserts by `id`; repeated writes update the existing row (no
  duplicate-key errors).
- `sess_timestamp` is stored as a `Y-m-d H:i:s` string and refreshed on every
  write.

## See also

- [Adapters overview](Adapters.md)
- [Recipe: Database-Backed Sessions](Recipe-Database-Sessions.md)
