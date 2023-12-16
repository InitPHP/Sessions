<?php
/**
 * CookieAdapter.php
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

use \InitPHP\Sessions\AbstractAdapter;
use \InitPHP\Sessions\Exceptions\{SessionException, SessionNotSupportedAdapter};
use \InitPHP\Sessions\Interfaces\AdapterInterface;

use function serialize;
use function base64_decode;
use function base64_encode;
use function setcookie;
use function is_int;
use function time;

class CookieAdapter extends AbstractAdapter implements AdapterInterface
{

    private string $name;

    private int $ttl;

    private string $data;

    /** @var \InitPHP\Encryption\HandlerInterface */
    private $encrypt;

    public function __construct(array $options)
    {
        $this->name = $options['name'];

        if (!class_exists("\\InitPHP\\Encryption\\Encrypt")) {
            throw new SessionNotSupportedAdapter('This adapter depends on the InitPHP Encryption library to work. Run the command : "composer require initphp/encryption"');
        }

        $this->encrypt = \InitPHP\Encryption\Encrypt::use(\InitPHP\Encryption\OpenSSL::class, [
            'algo'      => 'SHA256',
            'cipher'    => 'AES-256-CTR',
            'key'       => $options['key'],
            'blocksize' => 16,
        ]);

        $this->ttl = $options['ttl'] ?? 86400;

        $this->_decode();
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $id): bool
    {
        return setcookie($this->name, '', (time() - 86400)) !== false;
    }

    /**
     * @inheritDoc
     */
    public function gc(int $max_lifetime): int|false
    {
        if ($this->ttl != $max_lifetime) {
            $this->ttl = $max_lifetime;
        }
        return $max_lifetime;
    }

    /**
     * @inheritDoc
     */
    public function read(string $id): string|false
    {
        if(!isset($this->data)){
            $this->_decode();
        }
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function write(string $id, string $data): bool
    {
        $this->data = $data;
        $value = base64_encode($this->encrypt->encrypt($data));
        return setcookie($this->name, $value, (time() + $this->ttl)) !== false;
    }

    private function _decode(): void
    {
        if (!isset($_COOKIE[$this->name])) {
            $this->data = serialize([]);
            return;
        }
        $data = $this->encrypt->decrypt(base64_decode($_COOKIE[$this->name]));
        if ($data === FALSE) {
            $this->data = serialize([]);
            return;
        }
        $this->data = $data;
    }

}
