<?php

/**
 * Session.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions;

use InitPHP\Sessions\Classes\GetterSetter;
use InitPHP\Sessions\Classes\Manager;
use InitPHP\Sessions\Exceptions\SessionException;
use SessionHandlerInterface;

/**
 * Facade over {@see Manager} (session lifecycle) and {@see GetterSetter}
 * (`$_SESSION` access).
 *
 * Bootstrap the facade once with {@see createImmutable()}, then call any method
 * statically (e.g. `Session::set(...)`). Every method is also callable on the
 * instance returned by {@see createImmutable()} — both styles are routed to the
 * same underlying objects — which is what makes `createImmutable()->start()`
 * chaining work.
 *
 * @method static bool          has(string $key)
 * @method static GetterSetter  set(string $key, mixed $value)
 * @method static mixed         push(string $key, mixed $value)
 * @method static mixed         get(string $key, mixed $default = null)
 * @method static mixed         pull(string $key, mixed $default = null)
 * @method static GetterSetter  remove(string ...$keys)
 * @method static GetterSetter  delete(string ...$keys)
 * @method static array         all()
 * @method static GetterSetter  setAssoc(array $assoc, bool $reset = false)
 * @method static bool          isStarted()
 * @method static string        getName()
 * @method static Manager       setName(string $name)
 * @method static bool          start(array $options = [])
 * @method static bool          destroy()
 * @method static bool          regenerateId(bool $deleteOldSession = false)
 * @method static string        getID()
 * @method static bool          setID(string $sessionId)
 * @method static bool          flush()
 * @method static bool          unset()
 *
 * @property-read Manager      $manager
 * @property-read GetterSetter $session
 */
final class Session
{
    private static Manager $managerInstance;

    private static GetterSetter $sessionInstance;

    /**
     * Bootstraps the facade and returns an instance for fluent chaining.
     *
     * @param SessionHandlerInterface|null $sessionHandlerOrAdapter Optional
     *        custom save handler (e.g. one of the bundled adapters). When null,
     *        PHP's configured default handler is used.
     * @return self
     */
    public static function createImmutable(?SessionHandlerInterface $sessionHandlerOrAdapter = null): self
    {
        self::$managerInstance = new Manager($sessionHandlerOrAdapter);
        self::$sessionInstance = new GetterSetter();

        return new self();
    }

    /**
     * @param string $name
     * @return Manager|GetterSetter|null
     */
    public function __get(string $name)
    {
        return match ($name) {
            'manager' => self::$managerInstance,
            'session' => self::$sessionInstance,
            default   => null,
        };
    }

    /**
     * @param string       $name
     * @param array<mixed> $arguments
     * @return mixed
     * @throws SessionException
     */
    public function __call(string $name, array $arguments)
    {
        return self::getObject($name)->{$name}(...$arguments);
    }

    /**
     * @param string       $name
     * @param array<mixed> $arguments
     * @return mixed
     * @throws SessionException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return self::getObject($name)->{$name}(...$arguments);
    }

    /**
     * Resolves which collaborator handles the given method.
     *
     * @param string $method
     * @return GetterSetter|Manager
     * @throws SessionException When the method is not part of the public API.
     */
    protected static function getObject(string $method): object
    {
        return match ($method) {
            'has', 'set', 'get', 'remove', 'delete', 'all', 'push', 'pull', 'setAssoc' => self::$sessionInstance,
            'isStarted', 'getName', 'setName', 'start', 'destroy', 'regenerateId', 'getID', 'setID', 'flush', 'unset' => self::$managerInstance,
            default => throw new SessionException('"' . $method . '" method is not found.'),
        };
    }
}
