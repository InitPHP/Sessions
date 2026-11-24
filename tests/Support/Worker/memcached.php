<?php

/**
 * Observes MemCacheAdapter (Memcached driver) against FakeMemcached in a
 * process without ext-memcached.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use InitPHP\Sessions\Adapters\MemCacheAdapter;
use InitPHP\Sessions\Tests\Support\FakeMemcached;

report(static function (): array {
    installDouble('memcached', 'Memcached', FakeMemcached::class);

    /** @return array{MemCacheAdapter, callable(): FakeMemcached} */
    $make = static function (): array {
        $adapter = new MemCacheAdapter(['prefix' => 'p:', 'ttl' => 100]);
        // The adapter connects lazily; this reaches the client it created.
        $client = static function () use ($adapter): FakeMemcached {
            $client = (new ReflectionProperty($adapter, 'memcache'))->getValue($adapter);
            \assert($client instanceof FakeMemcached);

            return $client;
        };

        return [$adapter, $client];
    };

    $o = ['real_extension_loaded' => extension_loaded('memcached')];

    [$a] = $make();
    $o['validate_unknown'] = $a->validateId('missing');

    [$a] = $make();
    $a->write('sid', 'a|i:1;');
    $o['validate_after_write'] = $a->validateId('sid');

    [$a, $c] = $make();
    $a->write('sid', 'a|i:1;');
    $c()->advance(100);
    $o['validate_after_expiry'] = $a->validateId('sid');

    [$a, $c] = $make();
    $a->write('sid', 'a|i:1;');
    $c()->advance(60);
    $o['renewal_result'] = $a->updateTimestamp('sid', 'a|i:1;');
    $c()->advance(60);
    $o['validate_after_renewal'] = $a->validateId('sid');
    $o['read_after_renewal'] = $a->read('sid');

    [$a] = $make();
    $a->write('sid', 'u|s:5:"admin";');
    $a->destroy('sid');
    $o['destroyed_update_result'] = $a->updateTimestamp('sid', 'u|s:5:"admin";');
    $o['destroyed_validate'] = $a->validateId('sid');
    $o['destroyed_read'] = $a->read('sid');

    [$a, $c] = $make();
    $a->write('sid', 'a|i:1;');
    $c()->failing = true;
    $o['failure_validate'] = $a->validateId('sid');
    $o['failure_read'] = $a->read('sid');
    $o['failure_update'] = $a->updateTimestamp('sid', 'a|i:1;');

    // An id the client chose that Memcached refuses as a key can never hold a
    // session; it must read as "no such session", not as a store failure.
    [$a] = $make();
    $badId = str_repeat('a', 300);
    $o['bad_key_validate'] = $a->validateId($badId);
    $o['bad_key_read'] = $a->read($badId);
    $o['bad_key_update'] = $a->updateTimestamp($badId, 'x');

    return $o;
});
