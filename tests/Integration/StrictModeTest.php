<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Integration;

use InitPHP\Encryption\Encrypt;
use InitPHP\Encryption\OpenSSL;
use InitPHP\Sessions\Adapters\CookieAdapter;
use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Session;
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
}
