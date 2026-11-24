<?php

/**
 * Observes MemCacheAdapter (legacy Memcache driver) against FakeMemcache in a
 * process without ext-memcache and ext-memcached.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Tests\Support\FakeMemcache;

report(static function (): array {
    installDouble('memcache', 'Memcache', FakeMemcache::class);

    /** @return array{MemCacheAdapter, callable(): FakeMemcache} */
    $make = static function (): array {
        $adapter = new MemCacheAdapter(['prefix' => 'p:', 'ttl' => 100]);
        $client = static function () use ($adapter): FakeMemcache {
            $client = (new ReflectionProperty($adapter, 'memcache'))->getValue($adapter);
            \assert($client instanceof FakeMemcache);

            return $client;
        };

        return [$adapter, $client];
    };

    $o = ['real_extension_loaded' => extension_loaded('memcache') || extension_loaded('memcached')];

    [$a, $c] = $make();
    $o['validate_unknown'] = $a->validateId('sid');
    $a->write('sid', 'a|i:1;');
    $o['validate_after_write'] = $a->validateId('sid');
    $c()->advance(100);
    $o['validate_after_expiry'] = $a->validateId('sid');

    [$a, $c] = $make();
    $a->write('sid', 'a|i:1;');
    $c()->advance(60);
    $o['renewal_result'] = $a->updateTimestamp('sid', 'a|i:1;');
    $c()->advance(60);
    $o['validate_after_renewal'] = $a->validateId('sid');

    [$a] = $make();
    $a->write('sid', 'u|s:5:"admin";');
    $a->destroy('sid');
    $o['destroyed_update_result'] = $a->updateTimestamp('sid', 'u|s:5:"admin";');
    $o['destroyed_validate'] = $a->validateId('sid');
    $o['destroyed_read'] = $a->read('sid');

    return $o;
});
