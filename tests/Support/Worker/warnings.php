<?php

/**
 * Loads every bundled adapter class and reports the warnings PHP raised while
 * doing so (PHP 8.6 warns about session handlers that lack create_sid() and
 * validateId(); earlier versions raise none).
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
    $raised = [];
    // Replaces the strict handler of the bootstrap to record instead of throw.
    set_error_handler(static function (int $severity, string $message, string $file) use (&$raised): bool {
        $raised[] = basename($file) . ': ' . $message;

        return true;
    });

    foreach ([
        FileAdapter::class, PDOAdapter::class, RedisAdapter::class,
        MemCacheAdapter::class, MongoDBAdapter::class, CookieAdapter::class,
    ] as $class) {
        class_exists($class);
    }

    return ['warnings' => $raised, 'php' => PHP_VERSION_ID];
});
