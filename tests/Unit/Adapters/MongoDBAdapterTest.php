<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\MongoDBAdapter;
use InitPHP\Sessions\Tests\Support\ExtensionDouble;
use InitPHP\Sessions\Tests\Support\FakeMongoBulkWrite;
use InitPHP\Sessions\Tests\Support\FakeMongoManager;
use InitPHP\Sessions\Tests\Support\FakeMongoQuery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Runs against an in-memory fake of the MongoDB driver classes (see
 * {@see ExtensionDouble}); this has not been exercised against a live MongoDB
 * server. The constructor, which insists on the real extension, is bypassed.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MongoDBAdapterTest extends TestCase
{
    private FakeMongoManager $manager;

    protected function setUp(): void
    {
        ExtensionDouble::install($this, 'mongodb', 'MongoDB\\Driver\\Manager', FakeMongoManager::class);
        ExtensionDouble::install($this, 'mongodb', 'MongoDB\\Driver\\Query', FakeMongoQuery::class);
        ExtensionDouble::install($this, 'mongodb', 'MongoDB\\Driver\\BulkWrite', FakeMongoBulkWrite::class);

        $this->manager = new FakeMongoManager();
    }

    private function adapter(): MongoDBAdapter
    {
        $reflection = new ReflectionClass(MongoDBAdapter::class);
        $adapter = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('manager')->setValue($adapter, $this->manager);
        $reflection->getProperty('collection')->setValue($adapter, 'app.sessions');

        return $adapter;
    }

    public function testValidateIdIsFalseForUnknownSessions(): void
    {
        self::assertFalse($this->adapter()->validateId('missing'));
    }

    public function testValidateIdIsTrueOnceTheSessionIsWritten(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');

        self::assertTrue($adapter->validateId('sid'));
    }

    public function testValidateIdIgnoresDocumentsOlderThanTheSessionLifetime(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');
        $this->manager->documents['sid']['timestamp'] = time() - (int) ini_get('session.gc_maxlifetime') - 10;

        self::assertFalse($adapter->validateId('sid'));
    }

    public function testUpdateTimestampRenewsTheTimestampAndKeepsTheData(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'a|i:1;');
        $stale = time() - (int) ini_get('session.gc_maxlifetime') + 60;
        $this->manager->documents['sid']['timestamp'] = $stale;

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertGreaterThan($stale, $this->manager->documents['sid']['timestamp']);
        self::assertSame('a|i:1;', $adapter->read('sid'));
    }

    public function testUpdateTimestampStoresTheDataAgainWhenTheDocumentVanished(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->updateTimestamp('sid', 'a|i:1;'));

        self::assertSame('a|i:1;', $adapter->read('sid'));
        self::assertTrue($adapter->validateId('sid'));
    }
}
