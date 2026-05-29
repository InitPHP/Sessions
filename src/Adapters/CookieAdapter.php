<?php

/**
 * CookieAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;

use function base64_decode;
use function base64_encode;
use function class_exists;
use function setcookie;
use function time;

/**
 * Stores the entire session payload in an encrypted client-side cookie.
 *
 * The payload is encrypted with `initphp/encryption` (AES-256-CTR + SHA-256)
 * before being base64-encoded into the cookie, so the client can hold but not
 * read or tamper with the data. No server-side storage is used.
 *
 * Supported options:
 *  - `name`     (string, required) Cookie name.
 *  - `key`      (string, required) Encryption key.
 *  - `ttl`      (int)    Cookie lifetime in seconds. Defaults to `86400`.
 *  - `path`     (string) Cookie path. Defaults to `/`.
 *  - `domain`   (string) Cookie domain. Defaults to none.
 *  - `secure`   (bool)   Send only over HTTPS. Defaults to false.
 *  - `httponly` (bool)   Hide from JavaScript. Defaults to true.
 *  - `samesite` (string) `Lax`, `Strict` or `None`. Defaults to `Lax`.
 *
 * @requires initphp/encryption
 */
class CookieAdapter extends AbstractAdapter
{
    private string $name;

    private int $ttl;

    private string $data;

    /** @var array<string, mixed> */
    private array $cookieOptions;

    /** @var \InitPHP\Encryption\HandlerInterface */
    private object $encrypt;

    /**
     * @param array<string, mixed> $options
     * @throws SessionInvalidArgumentException When required options are missing.
     * @throws SessionNotSupportedAdapter When initphp/encryption is not installed.
     */
    public function __construct(array $options)
    {
        if (!isset($options['name'], $options['key'])) {
            throw new SessionInvalidArgumentException('The "name" and "key" options are required.');
        }

        if (!class_exists('\\InitPHP\\Encryption\\Encrypt')) {
            throw new SessionNotSupportedAdapter('This adapter depends on the InitPHP Encryption library. Run: composer require initphp/encryption');
        }

        $this->name = (string) $options['name'];
        $this->ttl = (int) ($options['ttl'] ?? 86400);

        $this->cookieOptions = [
            'path'     => (string) ($options['path'] ?? '/'),
            'domain'   => (string) ($options['domain'] ?? ''),
            'secure'   => (bool) ($options['secure'] ?? false),
            'httponly' => (bool) ($options['httponly'] ?? true),
            'samesite' => (string) ($options['samesite'] ?? 'Lax'),
        ];

        $this->encrypt = \InitPHP\Encryption\Encrypt::use(\InitPHP\Encryption\OpenSSL::class, [
            'algo'      => 'SHA256',
            'cipher'    => 'AES-256-CTR',
            'key'       => $options['key'],
            'blocksize' => 16,
        ]);

        $this->decode();
    }

    public function read(string $id): string|false
    {
        if (!isset($this->data)) {
            $this->decode();
        }

        return $this->data;
    }

    public function write(string $id, string $data): bool
    {
        $this->data = $data;
        $value = base64_encode($this->encrypt->encrypt($data));

        return setcookie($this->name, $value, [
            'expires' => time() + $this->ttl,
        ] + $this->cookieOptions);
    }

    public function destroy(string $id): bool
    {
        $this->data = '';

        return setcookie($this->name, '', [
            'expires' => time() - 86400,
        ] + $this->cookieOptions);
    }

    private function decode(): void
    {
        $this->data = '';

        if (!isset($_COOKIE[$this->name])) {
            return;
        }

        $raw = base64_decode($_COOKIE[$this->name], true);
        if ($raw === false) {
            return;
        }

        try {
            $data = $this->encrypt->decrypt($raw);
        } catch (\Throwable) {
            // A tampered, corrupt, or foreign-key cookie: treat as no session.
            return;
        }

        if (\is_string($data)) {
            $this->data = $data;
        }
    }
}
