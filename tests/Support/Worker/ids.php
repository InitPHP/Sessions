<?php

/**
 * Observes the session ids the bundled adapters create, next to the ids PHP's
 * own generator makes under the same settings.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use InitPHP\Sessions\Adapters\CookieAdapter;
use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Adapters\MongoDBAdapter;
use InitPHP\Sessions\Adapters\PDOAdapter;
use InitPHP\Sessions\Adapters\RedisAdapter;

report(static function (): array {
    $classes = [
        FileAdapter::class, PDOAdapter::class, RedisAdapter::class,
        MemCacheAdapter::class, MongoDBAdapter::class, CookieAdapter::class,
    ];

    $o = ['native' => session_create_id(), 'adapters' => []];
    foreach ($classes as $class) {
        // The constructors need services or extensions; create_sid() needs none.
        $adapter = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $o['adapters'][$class] = [
            'implements_session_id_interface' => $adapter instanceof SessionIdInterface,
            'first' => $adapter->create_sid(),
            'second' => $adapter->create_sid(),
        ];
    }

    return $o;
});
