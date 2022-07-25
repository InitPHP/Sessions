<?php
/**
 * Session.php
 *
 * This file is part of Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Facede;

use InitPHP\Sessions\Session as SessionInstance;

/**
 * @mixin SessionInstance
 * @method static string getName()
 * @method static SessionInstance setName(string $name)
 * @method static bool isStarted()
 * @method static bool start(array $options = [])
 * @method static bool regenerateId(bool $deleteOldSession = false)
 * @method static string getID()
 * @method static bool setID(string $sessionId)
 * @method static array all()
 * @method static bool destroy()
 * @method static bool flush()
 * @method static bool has(string $key)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static SessionInstance set(string $key, mixed $value, null|int $ttl = null)
 * @method static mixed push(string $key, mixed $value, null|int $ttl = null)
 * @method static SessionInstance setAssoc(array $assoc, null|int $ttl = null)
 * @method static SessionInstance remove(string ...$key)
 */
class Session
{

    /** @var SessionInstance */
    private static $sessionInstance;

    private static function getInstance(): SessionInstance
    {
        if(!isset(self::$sessionInstance)){
            self::$sessionInstance = new SessionInstance();
        }
        return self::$sessionInstance;
    }

    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

}
