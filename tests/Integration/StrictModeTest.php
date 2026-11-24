<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Integration;

use InitPHP\Encryption\Encrypt;
use InitPHP\Encryption\OpenSSL;
use InitPHP\Sessions\Adapters\CookieAdapter;
use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Adapters\PDOAdapter;
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Tests\Support\StrictArrayAdapter;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use SessionHandlerInterface;

/**
 * Drives real PHP sessions with `session.use_strict_mode` and
 * `session.lazy_write` on, handing the session id (and, for the cookie
 * adapter, the payload cookie) from one simulated request to the next the way
 * a browser would: through `$_COOKIE`.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StrictModeTest extends TestCase
{
    private const COOKIE_KEY = 'strict-mode-test-key';

    private const OPTIONS = [
        'use_cookies'      => '1',
        'use_only_cookies' => '1',
        'use_strict_mode'  => '1',
        'lazy_write'       => '1',
        'cache_limiter'    => '',
    ];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/initphp-sessions-strict-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * Starts a request as a browser holding `$cookieId` would, runs `$work`
     * inside the session and closes it. Returns the id the request ended up
     * with.
     *
     * @param callable(): void $work
     */
    private function request(SessionHandlerInterface $adapter, ?string $cookieId, callable $work): string
    {
        // Present the browser's session cookie. PHP keeps the id of the
        // previous (closed) session in the same process, so it is replaced
        // explicitly; strict mode validates it exactly like a cookie id.
        unset($_COOKIE[session_name()]);
        if ($cookieId !== null) {
            $_COOKIE[session_name()] = $cookieId;
            session_id($cookieId);
        }

        Session::createImmutable($adapter);
        self::assertTrue(Session::start(self::OPTIONS));
        $id = Session::getID();
        $work();
        session_write_close();

        return $id;
    }

    public function testStrictModeReplacesAnIdTheServerNeverIssued(): void
    {
        $attackerId = 'attackerchosenid0123456789abcdef';

        $id = $this->request(new FileAdapter(['path' => $this->dir]), $attackerId, static function (): void {
            Session::set('user', 'victim');
        });

        self::assertNotSame($attackerId, $id);
        self::assertFileDoesNotExist($this->dir . '/sess_' . $attackerId);
        self::assertFileExists($this->dir . '/sess_' . $id);
    }

    public function testStrictModeKeepsAnIdTheServerIssued(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);

        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $seen = null;
        $second = $this->request($adapter, $first, static function () use (&$seen): void {
            $seen = Session::get('user');
        });

        self::assertSame($first, $second);
        self::assertSame('admin', $seen);
    }

    public function testStrictModeReplacesAnIdWhoseSessionExpired(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);

        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });
        touch($this->dir . '/sess_' . $first, time() - (int) ini_get('session.gc_maxlifetime') - 60);
        clearstatcache();

        $seen = 'unset';
        $second = $this->request($adapter, $first, static function () use (&$seen): void {
            $seen = Session::get('user');
        });

        self::assertNotSame($first, $second);
        self::assertNull($seen);
    }

    public function testAnIdThatIsAPathNeverReachesOutsideTheSaveDirectory(): void
    {
        $outside = dirname($this->dir) . '/initphp-sessions-escape-' . uniqid('', true);
        $traversal = '../' . basename($outside);

        try {
            $id = $this->request(new FileAdapter(['path' => $this->dir, 'prefix' => '']), $traversal, static function (): void {
                Session::set('user', 'victim');
            });

            self::assertNotSame($traversal, $id);
            self::assertFileDoesNotExist($outside);
        } finally {
            if (file_exists($outside)) {
                unlink($outside);
            }
        }
    }

    public function testLazyWriteRenewsTheSessionInsteadOfRewritingIt(): void
    {
        $adapter = new class (['path' => $this->dir]) extends FileAdapter {
            public int $writes = 0;

            public int $renewals = 0;

            public function write(string $id, string $data): bool
            {
                ++$this->writes;

                return parent::write($id, $data);
            }

            public function updateTimestamp(string $id, string $data): bool
            {
                ++$this->renewals;

                return parent::updateTimestamp($id, $data);
            }
        };

        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });
        self::assertSame(1, $adapter->writes);

        // Nearly expired but still alive.
        $nearlyExpired = time() - (int) ini_get('session.gc_maxlifetime') + 60;
        touch($this->dir . '/sess_' . $first, $nearlyExpired);
        clearstatcache();

        // A request that only reads: the data is unchanged.
        $second = $this->request($adapter, $first, static function (): void {
            Session::get('user');
        });

        self::assertSame($first, $second);
        self::assertSame(1, $adapter->writes, 'unchanged data must not be rewritten');
        self::assertSame(1, $adapter->renewals);
        clearstatcache();
        self::assertGreaterThan($nearlyExpired, (int) filemtime($this->dir . '/sess_' . $first));
    }

    private function cookieAdapter(): CookieAdapter
    {
        return new CookieAdapter(['name' => 'payload', 'key' => self::COOKIE_KEY, 'ttl' => 3600]);
    }

    /**
     * The payload cookie value a browser would send back for the given
     * PHP-session-encoded data.
     */
    private function payloadCookie(string $data): string
    {
        $handler = Encrypt::use(OpenSSL::class, [
            'algo'      => 'SHA256',
            'cipher'    => 'AES-256-CTR',
            'key'       => self::COOKIE_KEY,
            'blocksize' => 16,
        ]);

        return base64_encode($handler->encrypt($data));
    }

    private function requireEncryption(): void
    {
        if (!class_exists(Encrypt::class)) {
            self::markTestSkipped('initphp/encryption is not installed.');
        }
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterReplacesAClientChosenIdWithoutAPayloadCookie(): void
    {
        $this->requireEncryption();
        $attackerId = 'attackerchosenid0123456789abcdef';

        $id = $this->request($this->cookieAdapter(), $attackerId, static function (): void {
            Session::set('user', 'victim');
        });

        self::assertNotSame($attackerId, $id);
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterKeepsTheIdWhenTheRequestCarriesItsPayloadCookie(): void
    {
        $this->requireEncryption();
        $_COOKIE['payload'] = $this->payloadCookie('user|s:5:"admin";');
        $knownId = 'issuedbytheserver0123456789abcdef';

        $seen = null;
        $id = $this->request($this->cookieAdapter(), $knownId, static function () use (&$seen): void {
            $seen = Session::get('user');
        });

        self::assertSame($knownId, $id);
        self::assertSame('admin', $seen);
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterReplacesTheIdWhenThePayloadCookieIsTampered(): void
    {
        $this->requireEncryption();
        $_COOKIE['payload'] = 'not-a-valid-ciphertext';
        $knownId = 'issuedbytheserver0123456789abcdef';

        $id = $this->request($this->cookieAdapter(), $knownId, static function (): void {});

        self::assertNotSame($knownId, $id);
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterRenewsTheCookieWhenTheDataDidNotChange(): void
    {
        $this->requireEncryption();
        $_COOKIE['payload'] = $this->payloadCookie('user|s:5:"admin";');

        $adapter = new class (['name' => 'payload', 'key' => self::COOKIE_KEY, 'ttl' => 3600]) extends CookieAdapter {
            public int $writes = 0;

            public int $renewals = 0;

            public function write(string $id, string $data): bool
            {
                ++$this->writes;

                return parent::write($id, $data);
            }

            public function updateTimestamp(string $id, string $data): bool
            {
                ++$this->renewals;

                return parent::updateTimestamp($id, $data);
            }
        };

        $this->request($adapter, 'issuedbytheserver0123456789abcdef', static function (): void {
            Session::get('user');
        });

        self::assertSame(1, $adapter->renewals, 'unchanged data must go through updateTimestamp()');
        // updateTimestamp() re-sends the cookie through write() so its expiry slides.
        self::assertSame(1, $adapter->writes);
    }

    public function testRegenerateIdWithDeletionMovesTheSessionToANewId(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);
        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $regenerated = '';
        $second = $this->request($adapter, $first, static function () use (&$regenerated): void {
            $regenerated = '';
            self::assertTrue(Session::regenerateId(true));
            $regenerated = Session::getID();
        });

        self::assertSame($first, $second);
        self::assertNotSame($first, $regenerated);
        self::assertFileDoesNotExist($this->dir . '/sess_' . $first);

        $seen = null;
        $third = $this->request($adapter, $regenerated, static function () use (&$seen): void {
            $seen = Session::get('user');
        });
        self::assertSame($regenerated, $third);
        self::assertSame('admin', $seen);

        // The old id is no longer known, so a client replaying it gets a fresh one.
        $replayed = $this->request($adapter, $first, static function () use (&$seen): void {
            $seen = Session::get('user');
        });
        self::assertNotSame($first, $replayed);
        self::assertNull($seen);
    }

    public function testRegenerateIdWithoutDeletionKeepsTheOldRecordUntilItExpires(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);
        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $regenerated = '';
        $this->request($adapter, $first, static function () use (&$regenerated): void {
            self::assertTrue(Session::regenerateId(false));
            $regenerated = Session::getID();
        });

        self::assertNotSame($first, $regenerated);
        self::assertFileExists($this->dir . '/sess_' . $first);
        self::assertFileExists($this->dir . '/sess_' . $regenerated);
    }

    public function testADestroyedSessionIdIsNotAcceptedAgain(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);
        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $this->request($adapter, $first, static function (): void {
            self::assertTrue(Session::destroy());
        });
        self::assertFileDoesNotExist($this->dir . '/sess_' . $first);

        $seen = 'unset';
        $replayed = $this->request($adapter, $first, static function () use (&$seen): void {
            $seen = Session::get('user');
        });

        self::assertNotSame($first, $replayed);
        self::assertNull($seen);
    }

    public function testASessionDestroyedByAParallelRequestStaysDestroyedAndRaisesNoWarning(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);
        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $warnings = [];
        set_error_handler(static function (int $no, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_ALL & ~E_DEPRECATED);

        try {
            // A read-only request; while it runs, a parallel request logs out.
            $dir = $this->dir;
            $this->request($adapter, $first, static function () use ($dir, $first): void {
                Session::get('user');
                unlink($dir . '/sess_' . $first);
            });
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertFileDoesNotExist($this->dir . '/sess_' . $first, 'the destroyed session must not come back');
    }

    public function testSessionCreateIdStillWorksInAnActiveSession(): void
    {
        $adapter = new FileAdapter(['path' => $this->dir]);
        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $created = null;
        $this->request($adapter, $first, static function () use (&$created): void {
            $created = session_create_id('x-');
        });

        self::assertIsString($created);
        self::assertStringStartsWith('x-', $created);
    }

    public function testACustomAdapterThatImplementsTheInterfaceGetsStrictMode(): void
    {
        $adapter = new StrictArrayAdapter();
        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        $seen = null;
        $second = $this->request($adapter, $first, static function () use (&$seen): void {
            $seen = Session::get('user');
        });
        self::assertSame($first, $second);
        self::assertSame('admin', $seen);

        $attackerId = 'attackerchosenid0123456789abcdef';
        $third = $this->request($adapter, $attackerId, static function (): void {});
        self::assertNotSame($attackerId, $third);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testADatabaseOutageDoesNotReplaceTheUsersSessionId(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE sessions (id VARCHAR(255) NOT NULL PRIMARY KEY, sess_timestamp TEXT NULL, sess_ip_address VARCHAR(48) NULL, sess_data TEXT NOT NULL)');
        $adapter = new PDOAdapter(['pdo' => $pdo, 'table' => 'sessions']);

        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });
        $pdo->exec('DROP TABLE sessions');

        // Records which ids the session machinery tries to read.
        $recording = new class (['pdo' => $pdo, 'table' => 'sessions']) extends PDOAdapter {
            /** @var list<string> */
            public array $reads = [];

            public function read(string $id): string|false
            {
                $this->reads[] = $id;

                return parent::read($id);
            }
        };

        session_id($first);
        $_COOKIE[session_name()] = $first;
        Session::createImmutable($recording);

        set_error_handler(static fn(): bool => true);

        try {
            $started = Session::start(self::OPTIONS);
        } finally {
            restore_error_handler();
        }

        // The session does not start (read fails), but the id the user holds
        // was not rejected and swapped for a fresh one.
        self::assertFalse($started);
        self::assertSame([$first], $recording->reads);
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterKeepsRegenerateIdAndCreateIdWorking(): void
    {
        $this->requireEncryption();
        $_COOKIE['payload'] = $this->payloadCookie('user|s:5:"admin";');
        $knownId = 'issuedbytheserver0123456789abcdef';

        $regenerated = '';
        $created = null;
        $id = $this->request($this->cookieAdapter(), $knownId, static function () use (&$regenerated, &$created): void {
            // Ids PHP generates are collision-checked through validateId(); an
            // adapter that reports every id as taken would make both fail.
            $created = session_create_id('x-');
            self::assertTrue(Session::regenerateId(true));
            $regenerated = Session::getID();
        });

        self::assertSame($knownId, $id);
        self::assertIsString($created);
        self::assertNotSame('', $regenerated);
        self::assertNotSame($knownId, $regenerated);

        // The next request presents the regenerated id together with the payload.
        $seen = null;
        $next = $this->request($this->cookieAdapter(), $regenerated, static function () use (&$seen): void {
            $seen = Session::get('user');
        });
        self::assertSame($regenerated, $next);
        self::assertSame('admin', $seen);
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterKeepsTheIdWhenTheSessionIsReopenedInTheSameRequest(): void
    {
        $this->requireEncryption();
        $adapter = $this->cookieAdapter();

        $first = $this->request($adapter, null, static function (): void {
            Session::set('user', 'admin');
        });

        // Same request, session closed and opened again: PHP reuses the id.
        $seen = null;
        $second = $this->request($adapter, null, static function () use (&$seen): void {
            $seen = Session::get('user');
        });

        self::assertSame($first, $second);
        self::assertSame('admin', $seen);
    }

    #[RequiresPhpExtension('openssl')]
    public function testCookieAdapterKeepsTheRegeneratedIdWhenTheSessionIsReopened(): void
    {
        $this->requireEncryption();
        $_COOKIE['payload'] = $this->payloadCookie('user|s:5:"admin";');
        $adapter = $this->cookieAdapter();

        $regenerated = '';
        $this->request($adapter, 'issuedbytheserver0123456789abcdef', static function () use (&$regenerated): void {
            Session::regenerateId(true);
            $regenerated = Session::getID();
        });

        $seen = null;
        $reopened = $this->request($adapter, null, static function () use (&$seen): void {
            $seen = Session::get('user');
        });

        self::assertNotSame('', $regenerated);
        self::assertSame($regenerated, $reopened);
        self::assertSame('admin', $seen);
    }
}
