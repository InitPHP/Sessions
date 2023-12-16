# InitPHP Session Manager


## Requirements

- PHP 8.0 or later

**Note :** Adapters used may have different dependencies.

## Installation

```
composer require initphp/sessions
```

## Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Session;

Session::createImmutable()
    ->start();
    

Session::set('username', 'admin')
        ->set('mail', 'admin@example.com');
/**
 * OR
 * 
 * $_SESSION['username'] = 'admin';
 * $_SESSION['mail'] = 'admin@example.com';
 */

echo Session::get('username', 'Undefined');
/**
 * OR
 * 
 * echo $_SESSION['username'] ?? 'Undefined';
 */
```

### Redis Adapter Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\RedisAdapter;

$adapter = new RedisAdapter([
    'host'      => '127.0.0.1', // string
    'port'      => 6379, // int
    'timeout'   => 0, // int
    'password'  => null, // null or string
    'database'  => 0,
    'ttl'       => 86400,
    'prefix'    => 'sess'
]);

Session::createImmutable($adapter)
    ->start();
```

### PDO Adapter Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\PDOAdapter;

$pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', '');

$adapter = new PDOAdapter(['pdo' => $pdo, 'table' => 'app_sessions']);

Session::createImmutable($adapter)
    ->start();
```

Example MySQL Table Create SQL :

```sql
CREATE TABLE `sessions` (
      `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `sess_timestamp` timestamp NULL DEFAULT NULL,
      `sess_ip_address` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL
      `sess_data` text COLLATE utf8mb4_unicode_ci NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `sessions` ADD PRIMARY KEY (`id`);
```


### Memcache Adapter Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\MemCacheAdapter;

$adapter = new MemCacheAdapter([
        'host'          => '127.0.0.1', // string
        'port'          => 11211, // int
        'weight'        => 1, // int
        'raw'           => false, // boolean
        'prefix'        => null, // null or string
        'ttl'           => 86400, // int
]);

Session::createImmutable($adapter)
    ->start();
```

### Cookie Adapter Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\CookieAdapter;

$adapter = new CookieAdapter(['name' => 'sessDataCookieName', 'key' => 'topSecretAppKey', 'ttl' => 86400]);

Session::createImmutable($adapter)
    ->start();
```

### MongoDB Adapter Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\MongoDBAdapter;

$adapter = new MongoDBAdapter(['dsn' => 'mongodb://127.0.0.1:27017', 'collation' => 'sessDbName.sessCollectionName']);

Session::createImmutable($adapter)
    ->start();
```

## Credits

- [Muhammet ŞAFAK](https://github.com/muhammetsafak) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
