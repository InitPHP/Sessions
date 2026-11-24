<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Sessions\Adapters\PDOAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
final class PDOAdapterTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE sessions (
                id VARCHAR(255) NOT NULL PRIMARY KEY,
                sess_timestamp TEXT NULL,
                sess_ip_address VARCHAR(48) NULL,
                sess_data TEXT NOT NULL
            )',
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function adapter(array $options = []): PDOAdapter
    {
        return new PDOAdapter(['pdo' => $this->pdo, 'table' => 'sessions'] + $options);
    }

    public function testConstructorRequiresTable(): void
    {
        $this->expectException(SessionInvalidArgumentException::class);

        new PDOAdapter(['pdo' => $this->pdo]);
    }

    public function testConstructorRequiresPdoOrDsn(): void
    {
        $this->expectException(SessionInvalidArgumentException::class);

        new PDOAdapter(['table' => 'sessions']);
    }

    public function testReadReturnsEmptyStringForUnknownSession(): void
    {
        self::assertSame('', $this->adapter()->read('nope'));
    }

    public function testWriteThenReadRoundTrips(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->write('sid', 'the-payload'));
        self::assertSame('the-payload', $adapter->read('sid'));
    }

    public function testWritePersistsPayloadInTheDataColumn(): void
    {
        // Regression: write() used to map values to the wrong columns, so the
        // payload never landed in sess_data.
        $this->adapter()->write('sid', 'real-data');

        $stmt = $this->pdo->query("SELECT sess_data, sess_ip_address FROM sessions WHERE id = 'sid'");
        self::assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('real-data', $row['sess_data']);
    }

    public function testWriteUpsertsOnRepeatedId(): void
    {
        $adapter = $this->adapter();

        self::assertTrue($adapter->write('sid', 'first'));
        self::assertTrue($adapter->write('sid', 'second'));

        self::assertSame('second', $adapter->read('sid'));

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM sessions');
        self::assertNotFalse($stmt);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testDestroyRemovesRow(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');

        self::assertTrue($adapter->destroy('sid'));
        self::assertSame('', $adapter->read('sid'));
    }

    public function testWithIPAddressScopesReads(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $adapter = $this->adapter(['withIPAddress' => true]);
        $adapter->write('sid', 'data');

        self::assertSame('data', $adapter->read('sid'));

        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        self::assertSame('', $adapter->read('sid'));

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testGarbageCollectionDeletesExpiredRows(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');

        // Backdate the row well beyond the lifetime.
        $this->pdo->exec("UPDATE sessions SET sess_timestamp = '2000-01-01 00:00:00'");

        self::assertSame(1, $adapter->gc(3600));
        self::assertSame('', $adapter->read('sid'));
    }

    public function testValidateIdIsFalseForUnknownSessions(): void
    {
        self::assertFalse($this->adapter()->validateId('missing'));
    }

    public function testValidateIdIsTrueForAFreshSession(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');

        self::assertTrue($adapter->validateId('sid'));
    }

    public function testValidateIdIsFalseForARowOlderThanTheLifetime(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');
        $this->pdo->exec("UPDATE sessions SET sess_timestamp = '2000-01-01 00:00:00'");

        self::assertFalse($adapter->validateId('sid'));
    }

    public function testValidateIdHonoursTheIpAddressMatch(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $adapter = $this->adapter(['withIPAddress' => true]);
        $adapter->write('sid', 'data');

        self::assertTrue($adapter->validateId('sid'));

        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        self::assertFalse($adapter->validateId('sid'));

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testUpdateTimestampRenewsTheRowWithoutChangingItsData(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');
        $this->pdo->exec("UPDATE sessions SET sess_timestamp = '2000-01-01 00:00:00'");
        self::assertFalse($adapter->validateId('sid'));

        self::assertTrue($adapter->updateTimestamp('sid', 'data'));

        self::assertTrue($adapter->validateId('sid'));
        self::assertSame('data', $adapter->read('sid'));
    }

    public function testAStoreFailureDoesNotRejectTheIdAndReadFailsTheSameWay(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');
        $this->pdo->exec('DROP TABLE sessions');

        // Not "no such session": the user's id must survive a database outage.
        self::assertTrue($adapter->validateId('sid'));
        self::assertFalse($adapter->read('sid'));
    }

    public function testASilentStoreFailureDoesNotRejectTheIdEither(): void
    {
        $adapter = $this->adapter();
        $adapter->write('sid', 'data');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->pdo->exec('DROP TABLE sessions');

        self::assertTrue($adapter->validateId('sid'));
    }

    public function testUpdateTimestampNeverCreatesARow(): void
    {
        $this->adapter()->updateTimestamp('ghost', 'data');

        $statement = $this->pdo->query('SELECT COUNT(*) FROM sessions');
        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }
}
