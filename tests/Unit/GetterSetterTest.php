<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Unit;

use InitPHP\Sessions\Classes\GetterSetter;
use PHPUnit\Framework\TestCase;

final class GetterSetterTest extends TestCase
{
    private GetterSetter $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new GetterSetter();
    }

    protected function tearDown(): void
    {
        unset($_SESSION);
    }

    public function testSetIsFluentAndStoresValue(): void
    {
        $return = $this->session->set('name', 'admin');

        self::assertSame($this->session, $return);
        self::assertSame('admin', $_SESSION['name']);
    }

    public function testGetReturnsStoredValue(): void
    {
        $_SESSION['name'] = 'admin';

        self::assertSame('admin', $this->session->get('name'));
    }

    public function testGetReturnsDefaultWhenAbsent(): void
    {
        self::assertSame('fallback', $this->session->get('missing', 'fallback'));
        self::assertNull($this->session->get('missing'));
    }

    public function testGetReturnsStoredNullRatherThanDefault(): void
    {
        $_SESSION['nullable'] = null;

        self::assertNull($this->session->get('nullable', 'default'));
    }

    public function testHas(): void
    {
        $_SESSION['present'] = 0;

        self::assertTrue($this->session->has('present'));
        self::assertFalse($this->session->has('absent'));
    }

    public function testRemoveAndDelete(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->session->remove('a', 'b');
        self::assertSame(['c' => 3], $_SESSION);

        $this->session->delete('c');
        self::assertSame([], $_SESSION);
    }

    public function testRemoveIgnoresUnknownKeys(): void
    {
        $_SESSION = ['a' => 1];

        $this->session->remove('does-not-exist');

        self::assertSame(['a' => 1], $_SESSION);
    }

    public function testAllReturnsWholePayload(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];

        self::assertSame(['a' => 1, 'b' => 2], $this->session->all());
    }

    public function testPushStoresAndReturnsValue(): void
    {
        $return = $this->session->push('token', 'abc');

        self::assertSame('abc', $return);
        self::assertSame('abc', $_SESSION['token']);
    }

    public function testPullReturnsAndRemovesValue(): void
    {
        $_SESSION['flash'] = 'once';

        self::assertSame('once', $this->session->pull('flash'));
        self::assertArrayNotHasKey('flash', $_SESSION);
    }

    public function testPullReturnsDefaultWhenAbsent(): void
    {
        self::assertSame('default', $this->session->pull('missing', 'default'));
    }

    public function testSetAssocMerges(): void
    {
        $_SESSION = ['keep' => 1];

        $this->session->setAssoc(['a' => 2, 'b' => 3]);

        self::assertSame(['keep' => 1, 'a' => 2, 'b' => 3], $_SESSION);
    }

    public function testSetAssocResetsWhenRequested(): void
    {
        $_SESSION = ['old' => 1];

        $this->session->setAssoc(['new' => 2], true);

        self::assertSame(['new' => 2], $_SESSION);
    }

    public function testSetAssocSkipsNumericKeys(): void
    {
        $_SESSION = [];

        $this->session->setAssoc(['valid' => 1, 0 => 'dropped', 7 => 'dropped']);

        self::assertSame(['valid' => 1], $_SESSION);
    }

    public function testReadsAreNullSafeWhenSessionNotStarted(): void
    {
        unset($_SESSION);

        self::assertFalse($this->session->has('anything'));
        self::assertNull($this->session->get('anything'));
        self::assertSame([], $this->session->all());
        self::assertSame('default', $this->session->pull('anything', 'default'));
    }
}
