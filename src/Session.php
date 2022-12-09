<?php
/**
 * Session.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions;

use InitPHP\Sessions\Exceptions\SessionException;
use \InitPHP\Sessions\Classes\{Manager, GetterSetter};

/**
 * @property-read Manager $manager
 * @property-read GetterSetter $session
 * @method static bool has(string $key)
 * @method bool has(string $key)
 * @method static GetterSetter set(string $key, mixed $value)
 * @method GetterSetter set(string $key, mixed $value)
 * @method static mixed push(string $key, mixed $value)
 * @method mixed push(string $key, mixed $value)
 * @method static mixed get(string $key, mixed $default = null)
 * @method mixed get(string $key, mixed $default = null)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method mixed pull(string $key, mixed $default = null)
 * @method static GetterSetter remove(string ...$keys)
 * @method GetterSetter remove(string ...$keys)
 * @method static GetterSetter delete(string ...$keys)
 * @method GetterSetter delete(string ...$keys)
 * @method static array all()
 * @method array all()
 * @method static GetterSetter setAssoc(array $assoc, bool $reset = false)
 * @method GetterSetter setAssoc(array $assoc, bool $reset = false)
 * @method static bool isStarted()
 * @method bool isStarted()
 * @method static string getName()
 * @method string getName()
 * @method static Manager setName(string $name)
 * @method Manager setName(string $name)
 * @method static bool start(array $options = [])
 * @method bool start(array $options = [])
 * @method static bool destroy()
 * @method bool destroy()
 * @method static bool regenerateId(bool $deleteOldSession = false)
 * @method bool regenerateId(bool $deleteOldSession = false)
 * @method static string getID()
 * @method string getID()
 * @method static bool setID(string $sessionId)
 * @method bool setID(string $sessionId)
 * @method static bool flush()
 * @method bool flush()
 * @method static bool unset()
 * @method bool unset()
 */
class Session
{

    private static Manager $manager;

    private static GetterSetter $session;

    public static function createImmutable(?\SessionHandlerInterface $sessionHandlerOrAdapter = null)
    {
        self::$manager = new Manager($sessionHandlerOrAdapter);
        self::$session = new GetterSetter();
    }

    public function __get($name)
    {
        switch ($name) {
            case 'manager':
                return self::$manager;
            case 'session':
                return self::$session;
        }
    }

    public function __call($name, $arguments)
    {
        return self::getObject($name)->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getObject($name)->{$name}(...$arguments);
    }

    /**
     * @param string $method
     * @return GetterSetter|Manager
     * @throws SessionException
     */
    protected static function getObject(string $method): object
    {
        switch ($method) {
            case 'has':
            case 'set':
            case 'get':
            case 'remove':
            case 'delete':
            case 'all':
            case 'push':
            case 'pull':
            case 'setAssoc':
                return self::$session;
            case 'isStart':
            case 'getName':
            case 'setName':
            case 'start':
            case 'destroy':
            case 'regenerateId':
            case 'getID':
            case 'setID':
            case 'flush':
            case 'unset':
                return self::$manager;
            default:
                throw new SessionException('"' . $method . '" method is not found.');
        }
    }

}