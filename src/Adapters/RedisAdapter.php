<?php
/**
 * Redis.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions\Adapters;

use \InitPHP\Sessions\Exceptions\{SessionException, SessionInvalidArgumentException, SessionNotSupportedAdapter};

use function is_array;

class RedisAdapter extends \InitPHP\Sessions\AbstractAdapter implements \InitPHP\Sessions\Interfaces\AdapterInterface
{

    /** @var \Redis $redis */
    private $redis;

    private int $database;

    private int $ttl;

    private ?string $prefix;

    /**
     * @param \Redis|array $redis
     * @param int $database
     * @param int $ttl
     * @param string|null $prefix
     */
    public function __construct($redis, int $database = 0, int $ttl = 864000, ?string $prefix = null)
    {
        if (!extension_loaded('redis')) {
            throw new SessionNotSupportedAdapter();
        }

        $this->ttl = $ttl;
        $this->database = $database;
        $this->prefix = $prefix;

        if ($redis instanceof \Redis) {
            $this->redis = $redis;
        } elseif (is_array($redis)) {
            try {
                $this->redis = new \Redis();

                if(!$this->redis->connect($redis['host'], $redis['port'], ($redis['timeout'] ?? 0))){
                    throw new \Exception('Redis Cache connection failed.');
                }
                $password = $redis['password'] ?? null;
                if($password !== null && !$this->redis->auth($password)){
                    throw new \Exception('Redis Cache authentication failed.');
                }
            } catch (\Exception $e) {
                throw new SessionException('Redis connection failed. ' . $e->getMessage());
            }
        } else {
            throw new SessionInvalidArgumentException();
        }

        try {
            $this->redis->select($this->database);
        } catch (\Exception $e) {
            throw new SessionException("Redis database select failed : " . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function destroy($id)
    {
        try {
            return $this->redis->del($this->prefix . $id) !== FALSE;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function read($id)
    {
        try {
            return (string)$this->redis->get($this->prefix . $id);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function write($id, $data)
    {
        try {
            $set = $this->redis->set($this->prefix . $id, $data);
            if($set === FALSE){
                return false;
            }
            $this->redis->expireAt($this->prefix . $id, time() + $this->ttl);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
