<?php
/**
 * MemCacheAdapter.php
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

use \InitPHP\Sessions\Exceptions\{SessionException, SessionNotSupportedAdapter};

use const CASE_LOWER;

use function extension_loaded;
use function class_exists;
use function array_merge;
use function array_change_key_case;
use function is_string;
class MemCacheAdapter extends \InitPHP\Sessions\AbstractAdapter implements \InitPHP\Sessions\Interfaces\AdapterInterface
{

    protected const DRIVER_MEMCACHE = 1;
    protected const DRIVER_MEMCACHED = 2;

    /** @var \Memcache|\Memcached */
    private $memcache;

    private int $driver;

    private array $credentials = [
        'host'          => '127.0.0.1',
        'port'          => 11211,
        'weight'        => 1,
        'raw'           => false,
        'prefix'        => null,
        'ttl'           => 86400,
    ];

    public function __construct(?array $credentials = null)
    {
        if(FALSE === (extension_loaded('memcache') || extension_loaded('memcached'))){
            throw new SessionNotSupportedAdapter();
        }
        if (class_exists("\\Memcache")) {
            $this->driver = self::DRIVER_MEMCACHE;
        } elseif (class_exists("\\Memcached")) {
            $this->driver = self::DRIVER_MEMCACHED;
        } else {
            throw new SessionNotSupportedAdapter();
        }
        $this->credentials = array_merge($this->credentials, array_change_key_case($credentials, CASE_LOWER));
    }

    public function __destruct()
    {
        if (!isset($this->memcache)) {
            return;
        }

        switch ($this->driver) {
            case self::DRIVER_MEMCACHE:
                $this->memcache->close();
                break;
            case self::DRIVER_MEMCACHED:
                $this->memcache->quit();
                break;
        }

        unset($this->memcache);
    }

    /**
     * @inheritDoc
     */
    public function destroy($id)
    {
        try {
            $session = $this->credentials['prefix'] . $id;

            return $this->getMemcache()->delete($session) !== FALSE;
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
            $session = $this->credentials['prefix'] . $id;
            switch ($this->driver) {
                case self::DRIVER_MEMCACHE:
                    $data = $this->getMemcache()->get($session, false);
                    return is_string($data) ? $data : false;
                case self::DRIVER_MEMCACHED:
                    $data = $this->getMemcache()->get($session);
                    return $this->getMemcache()->getResultCode() === \Memcached::RES_NOTFOUND ? false : $data;
                default:
                    return false;
            }
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
            $session = $this->credentials['prefix'] . $id;

            switch ($this->driver) {
                case self::DRIVER_MEMCACHE:
                    $res = $this->getMemcache()->set($session, $data, 0, $this->credentials['ttl']);
                    break;
                case self::DRIVER_MEMCACHED:
                    $res = $this->getMemcache()->set($session, $data, $this->credentials['ttl']);
                    break;
                default:
                    $res = false;
            }
            return $res;
        } catch (\Exception $e) {
            return false;
        }
    }


    private function getMemcache()
    {
        if (isset($this->memcache)) {
            return $this->memcache;
        }

        try {
            switch ($this->driver) {
                case self::DRIVER_MEMCACHE:
                    $this->memcache = new \Memcache();
                    $connection = $this->memcache->connect($this->credentials['host'], $this->credentials['port']);
                    if ($connection === FALSE) {
                        throw new \Exception("Memcache connection failed");
                    }

                    $this->memcache->addServer($this->credentials['host'], $this->credentials['port'], true, $this->credentials['weight']);

                    return $this->memcache;
                case self::DRIVER_MEMCACHED:
                    $this->memcache = new \Memcached();

                    ($this->credentials['raw'] ?? FALSE) !== FALSE && $this->memcache->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

                    $this->memcache->addServer($this->credentials['host'], $this->credentials['port'], $this->credentials['weight']);
                    $stats = $this->memcache->getStats();
                    $statKey = $this->credentials['host'] . ':' . $this->credentials['port'];

                    if (!isset($stats[$statKey])) {
                        throw new \Exception("Memcached connection failed");
                    }

                    return $this->memcache;
                default:
                    throw new \Exception("Memcached not supported.");
            }
        } catch (\Exception $e) {
            throw new SessionException($e->getMessage(), (int)$e->getCode());
        }
    }

}
