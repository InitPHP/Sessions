<?php

/**
 * Observes MongoDBAdapter against the in-memory MongoDB driver fakes in a
 * process without ext-mongodb.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use InitPHP\Sessions\Adapters\MongoDBAdapter;
use InitPHP\Sessions\Tests\Support\FakeMongoBulkWrite;
use InitPHP\Sessions\Tests\Support\FakeMongoManager;
use InitPHP\Sessions\Tests\Support\FakeMongoQuery;

report(static function (): array {
    installDouble('mongodb', 'MongoDB\\Driver\\Manager', FakeMongoManager::class);
    installDouble('mongodb', 'MongoDB\\Driver\\Query', FakeMongoQuery::class);
    installDouble('mongodb', 'MongoDB\\Driver\\BulkWrite', FakeMongoBulkWrite::class);

    /** @return array{FakeMongoManager, MongoDBAdapter} */
    $make = static function (): array {
        $manager = new FakeMongoManager();
        // The constructor insists on the real extension, so it is bypassed.
        $reflection = new ReflectionClass(MongoDBAdapter::class);
        $adapter = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('manager')->setValue($adapter, $manager);
        $reflection->getProperty('collection')->setValue($adapter, 'app.sessions');

        return [$manager, $adapter];
    };

    $lifetime = (int) ini_get('session.gc_maxlifetime');

    $o = ['real_extension_loaded' => extension_loaded('mongodb'), 'lifetime' => $lifetime];

    [, $a] = $make();
    $o['validate_unknown'] = $a->validateId('missing');

    [, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $o['validate_after_write'] = $a->validateId('sid');

    [$m, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $m->documents['sid']['timestamp'] = time() - $lifetime - 10;
    $o['validate_after_expiry'] = $a->validateId('sid');

    [$m, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $stale = time() - $lifetime + 60;
    $m->documents['sid']['timestamp'] = $stale;
    $o['renewal_result'] = $a->updateTimestamp('sid', 'a|i:1;');
    $o['timestamp_renewed'] = $m->documents['sid']['timestamp'] > $stale;
    $o['read_after_renewal'] = $a->read('sid');

    [$m, $a] = $make();
    $a->write('sid', 'u|s:5:"admin";');
    $a->destroy('sid');
    $o['destroyed_update_result'] = $a->updateTimestamp('sid', 'u|s:5:"admin";');
    $o['destroyed_validate'] = $a->validateId('sid');
    $o['destroyed_read'] = $a->read('sid');
    $o['destroyed_documents'] = count($m->documents);

    [$m, $a] = $make();
    $a->write('sid', 'a|i:1;');
    $m->failing = true;
    $o['failure_validate'] = $a->validateId('sid');
    $o['failure_read'] = $a->read('sid');
    $o['failure_update'] = $a->updateTimestamp('sid', 'a|i:1;');

    // An id that is not valid UTF-8 cannot be a BSON string: it is "no such
    // session" and never reaches the driver (which would throw for it).
    [$m, $a] = $make();
    $badId = "bad\xFFid";
    $o['bad_id_validate'] = $a->validateId($badId);
    $o['bad_id_read'] = $a->read($badId);
    $o['bad_id_update'] = $a->updateTimestamp($badId, 'x');
    $o['bad_id_write'] = $a->write($badId, 'x');
    $o['bad_id_destroy'] = $a->destroy($badId);
    $o['bad_id_commands'] = $m->commands;

    return $o;
});
