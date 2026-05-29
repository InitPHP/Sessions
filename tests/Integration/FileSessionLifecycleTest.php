<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Integration;

use InitPHP\Sessions\Adapters\FileAdapter;
use InitPHP\Sessions\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Drives a real PHP session through the {@see Session} facade and the
 * {@see FileAdapter} save handler to prove the open/read/write/close/destroy
 * cycle is wired correctly end to end.
 */
final class FileSessionLifecycleTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionPersistsAndReloadsThroughTheFileAdapter(): void
    {
        $dir = sys_get_temp_dir() . '/initphp-sessions-it-' . uniqid('', true);
        mkdir($dir);

        $options = ['use_cookies' => '0', 'cache_limiter' => ''];

        Session::createImmutable(new FileAdapter(['path' => $dir]));
        self::assertTrue(Session::start($options));

        $id = Session::getID();
        self::assertNotSame('', $id);

        Session::set('user', 'admin');
        Session::set('roles', ['a', 'b']);
        session_write_close();

        self::assertFileExists($dir . '/sess_' . $id);

        // Simulate a second request reusing the same id.
        session_id($id);
        Session::createImmutable(new FileAdapter(['path' => $dir]));
        self::assertTrue(Session::start($options));

        self::assertSame('admin', Session::get('user'));
        self::assertSame(['a', 'b'], Session::get('roles'));

        self::assertTrue(Session::destroy());
        session_write_close();

        self::assertFileDoesNotExist($dir . '/sess_' . $id);

        rmdir($dir);
    }
}
