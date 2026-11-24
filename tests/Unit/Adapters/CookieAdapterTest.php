<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit\Adapters;

use InitPHP\Encryption\Encrypt;
use InitPHP\Encryption\OpenSSL;
use InitPHP\Sessions\Adapters\CookieAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Read/decode behaviour is covered without invoking setcookie() so the suite
 * stays header- and output-safe; cookie emission is exercised separately.
 */
#[RequiresPhpExtension('openssl')]
final class CookieAdapterTest extends TestCase
{
    private const KEY = 'cookie-adapter-test-key';
    private const NAME = 'sess_cookie';

    protected function setUp(): void
    {
        if (!class_exists(Encrypt::class)) {
            self::markTestSkipped('initphp/encryption is not installed.');
        }
        unset($_COOKIE[self::NAME]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[self::NAME]);
    }

    private function encryptWith(string $key, string $payload): string
    {
        $handler = Encrypt::use(OpenSSL::class, [
            'algo'      => 'SHA256',
            'cipher'    => 'AES-256-CTR',
            'key'       => $key,
            'blocksize' => 16,
        ]);

        return base64_encode($handler->encrypt($payload));
    }

    public function testConstructorRequiresNameAndKey(): void
    {
        $this->expectException(SessionInvalidArgumentException::class);

        new CookieAdapter(['name' => self::NAME]);
    }

    public function testReadReturnsEmptyStringWithoutCookie(): void
    {
        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertSame('', $adapter->read('sid'));
    }

    public function testReadDecryptsExistingCookie(): void
    {
        $_COOKIE[self::NAME] = $this->encryptWith(self::KEY, 'serialized-session');

        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertSame('serialized-session', $adapter->read('sid'));
    }

    public function testTamperedCookieIsTreatedAsEmpty(): void
    {
        $_COOKIE[self::NAME] = 'not-a-valid-ciphertext';

        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertSame('', $adapter->read('sid'));
    }

    public function testCookieEncryptedWithAnotherKeyIsTreatedAsEmpty(): void
    {
        $_COOKIE[self::NAME] = $this->encryptWith('a-totally-different-key', 'secret');

        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertSame('', $adapter->read('sid'));
    }

    public function testValidateIdIsFalseWithoutAPayloadCookie(): void
    {
        $_COOKIE[session_name()] = 'client-chosen-id';
        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertFalse($adapter->validateId('client-chosen-id'));

        unset($_COOKIE[session_name()]);
    }

    public function testValidateIdIsTrueOnlyForTheIdTheClientPresented(): void
    {
        $_COOKIE[self::NAME] = $this->encryptWith(self::KEY, 'serialized-session');
        $_COOKIE[session_name()] = 'presented-id';

        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertTrue($adapter->validateId('presented-id'));
        // An id PHP just generated (session_create_id(), regenerate) is never "taken".
        self::assertFalse($adapter->validateId('freshly-generated-id'));

        unset($_COOKIE[session_name()]);
    }

    public function testValidateIdIsFalseWhenTheRequestCarriesNoSessionCookie(): void
    {
        $_COOKIE[self::NAME] = $this->encryptWith(self::KEY, 'serialized-session');
        unset($_COOKIE[session_name()]);

        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        self::assertFalse($adapter->validateId('any-id'));
    }

    public function testValidateIdIsFalseForATamperedOrForeignKeyCookie(): void
    {
        $_COOKIE[session_name()] = 'presented-id';

        $_COOKIE[self::NAME] = 'not-a-valid-ciphertext';
        self::assertFalse((new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]))->validateId('presented-id'));

        $_COOKIE[self::NAME] = $this->encryptWith('a-totally-different-key', 'secret');
        self::assertFalse((new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]))->validateId('presented-id'));

        unset($_COOKIE[session_name()]);
    }

    public function testUpdateTimestampWithoutAPayloadCookieSendsNothing(): void
    {
        $adapter = new CookieAdapter(['name' => self::NAME, 'key' => self::KEY]);

        // No session to renew: succeeds without calling setcookie().
        self::assertTrue($adapter->updateTimestamp('sid', ''));
    }
}
