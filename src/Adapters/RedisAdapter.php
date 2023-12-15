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
     * @param array $options
     * @throws SessionException
     */
    public function __construct(array $options)
    {
        if (!extension_loaded('redis')) {
            throw new SessionNotSupportedAdapter();
        }

        $this->ttl = $options['ttl'] ?? 864000;
        $this->database = $options['database'] ?? 0;
        $this->prefix = $options['prefix'] ?? null;

        if ($options['redis'] instanceof \Redis) {
            $this->redis = $options['redis'];
        } elseif (is_array($options['redis'])) {
            try {
                $this->redis = new \Redis();

                if(!$this->redis->connect($options['redis']['host'], $options['redis']['port'], ($options['redis']['timeout'] ?? 0))){
                    throw new \Exception('Redis Cache connection failed.');
                }
                $password = $options['redis']['password'] ?? null;
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
