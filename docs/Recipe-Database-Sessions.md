# Recipe: Database-Backed Sessions

Storing sessions in your SQL database keeps them durable, shareable across app
servers, and queryable (e.g. "force-logout this user"). This recipe uses the
[PDO adapter](PDO-Adapter.md).

## 1. Create the table

MySQL:

```sql
CREATE TABLE `sessions` (
    `id`              VARCHAR(255) NOT NULL,
    `sess_timestamp`  DATETIME NULL DEFAULT NULL,
    `sess_ip_address` VARCHAR(48) DEFAULT NULL,
    `sess_data`       TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

PostgreSQL:

```sql
CREATE TABLE sessions (
    id              VARCHAR(255) PRIMARY KEY,
    sess_timestamp  TIMESTAMP NULL,
    sess_ip_address VARCHAR(48),
    sess_data       TEXT NOT NULL
);
```

SQLite:

```sql
CREATE TABLE sessions (
    id              VARCHAR(255) NOT NULL PRIMARY KEY,
    sess_timestamp  TEXT NULL,
    sess_ip_address VARCHAR(48) NULL,
    sess_data       TEXT NOT NULL
);
```

## 2. Wire up the adapter

Reuse your application's existing `PDO` connection:

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\PDOAdapter;

$pdo = new PDO('mysql:host=localhost;dbname=app', 'app', 'secret', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

Session::createImmutable(new PDOAdapter([
    'pdo'   => $pdo,
    'table' => 'sessions',
]))->start();
```

Or let the adapter connect from a DSN:

```php
new PDOAdapter([
    'dsn'      => 'mysql:host=localhost;dbname=app',
    'username' => 'app',
    'password' => 'secret',
    'table'    => 'sessions',
]);
```

## 3. Garbage collection

The adapter's `gc()` removes rows older than `gc_maxlifetime` and runs on PHP's
normal probability-based schedule. If you'd rather not rely on that (it can be
disabled or rare on some setups), run it from cron:

```php
// bin/session-gc.php
require __DIR__ . '/../vendor/autoload.php';

use InitPHP\Sessions\Adapters\PDOAdapter;

$adapter = new PDOAdapter(['dsn' => getenv('DB_DSN'), 'username' => getenv('DB_USER'), 'password' => getenv('DB_PASS'), 'table' => 'sessions']);
$deleted = $adapter->gc((int) ini_get('session.gc_maxlifetime'));

fwrite(STDOUT, "Deleted {$deleted} expired sessions\n");
```

```cron
*/15 * * * * php /var/www/app/bin/session-gc.php
```

## 4. Operational queries

Because sessions are rows, you can manage them with SQL:

```sql
-- How many active-ish sessions?
SELECT COUNT(*) FROM sessions
WHERE sess_timestamp > (NOW() - INTERVAL 30 MINUTE);

-- Force-expire everything (global logout):
DELETE FROM sessions;
```

> `sess_data` is PHP's session-encoded blob, not JSON — don't try to parse user
> data out of it in SQL. If you need to target a specific user, store a lookup
> (e.g. a `user_sessions` table) at login time.

## 5. Optionally bind to IP

For an extra anti-replay layer (mind the [caveats](PDO-Adapter.md#binding-sessions-to-the-client-ip)):

```php
new PDOAdapter(['pdo' => $pdo, 'table' => 'sessions', 'withIPAddress' => true]);
```

## See also

- [PDO Adapter](PDO-Adapter.md)
- [Recipe: Authentication](Recipe-Authentication.md)
- [MongoDB Adapter](MongoDB-Adapter.md) — the document-store equivalent.
