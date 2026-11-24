<?php

/**
 * Observes RedisAdapter against FakeRedis in a process without ext-redis.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use InitPHP\Sessions\Adapters\RedisAdapter;
use InitPHP\Sessions\Tests\Support\FakeRedis;

report(static function (): array {
    installDouble('redis', 'Redis', FakeRedis::class);

    /** @return array{FakeRedis, RedisAdapter} */
    $make = static function (int $ttl = 100): array {
        $redis = new FakeRedis();

        return [$redis, new RedisAdapter(['redis' => $redis, 'prefix' => 'p:', 'ttl' => $ttl])];
    };

    $o = ['real_extension_loaded' => extension_loaded('redis')];

    [, $a] = $make();
    $o['validate_unknown'] = $a->validateId('missing');

    [, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $o['validate_after_write'] = $a->validateId('sid');

    [$r, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $r->advance(100);
    $o['validate_after_expiry'] = $a->validateId('sid');

    [, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $a->destroy('sid');
    $o['validate_after_destroy'] = $a->validateId('sid');

    [$r, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $r->advance(60);
    $o['ttl_before_renewal'] = $r->ttlOf('p:sid');
    $o['renewal_result'] = $a->updateTimestamp('sid', 'a|i:1;');
    $o['ttl_after_renewal'] = $r->ttlOf('p:sid');
    $r->advance(60);
    $o['validate_after_renewal'] = $a->validateId('sid');
    $o['read_after_renewal'] = $a->read('sid');

    [, $a] = $make();
    $a->write('sid', 'u|s:5:"admin";');
    $a->destroy('sid');
    $o['destroyed_update_result'] = $a->updateTimestamp('sid', 'u|s:5:"admin";');
    $o['destroyed_validate'] = $a->validateId('sid');
    $o['destroyed_read'] = $a->read('sid');

    [$r, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $r->failing = true;
    $o['failure_validate'] = $a->validateId('sid');
    $o['failure_read'] = $a->read('sid');
    $o['failure_update'] = $a->updateTimestamp('sid', 'a|i:1;');

    [$r, $a] = $make(0);
    $a->write('sid', 'a|i:1;');
    $o['no_expiry_update_result'] = $a->updateTimestamp('sid', 'a|i:1;');
    $o['no_expiry_ttl'] = $r->ttlOf('p:sid');
    $o['no_expiry_validate'] = $a->validateId('sid');

    return $o;
});
