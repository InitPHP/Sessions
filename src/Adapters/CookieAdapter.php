<?php

/**
 * CookieAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Exceptions\SessionNotSupportedAdapter;
use InitPHP\Sessions\Internal\GeneratesSessionIds;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

use function base64_decode;
use function base64_encode;
use function class_exists;
use function session_name;
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
 * The session id is not a lookup key here (the data travels with the
 * request), so strict mode is answered from the request: {@see validateId()}
 * accepts an id only when it is the session id the client sent in its session
 * cookie (or the one this session was last read or written under, so that
 * re-opening a session within a request keeps its id) *and* the request carries a payload cookie that decrypts with the
 * configured key. A first visit, a missing, tampered or foreign-key payload
 * cookie, or an id the client chose without the matching payload therefore gets
 * a fresh id. Ids PHP generates itself (`session_create_id()`,
 * `session_regenerate_id()`) are never reported as taken. Strict mode needs the
 * id to travel in the session cookie (`session.use_only_cookies`).
 * {@see updateTimestamp()} re-sends the payload cookie so its client-side
 * expiry slides with `session.lazy_write`. The payload carries no timestamp, so
 * the lifetime is enforced by the browser only.
 *
 * @requires initphp/encryption
 */
class CookieAdapter extends AbstractAdapter implements SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    use GeneratesSessionIds;

    private string $name;

    private int $ttl;

    private string $data;

    /** Whether the request carried a payload cookie that decrypted. */
    private bool $hasPayload = false;

    /** The id of the session that was last read or written (for re-opening it). */
    private ?string $currentId = null;

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

    public function validateId(string $id): bool
    {
        if (!isset($this->data)) {
            $this->decode();
        }

        // PHP also calls validateId() to check ids it has just generated for
        // collisions; those never equal the id the client presented.
        $presented = $_COOKIE[session_name()] ?? null;

        // The id this very session was last read/written under is accepted
        // too, so closing and re-opening a session in one request keeps its id.
        return $this->hasPayload
            && ((\is_string($presented) && $presented === $id) || $id === $this->currentId);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        // Without a payload cookie there is no session to renew; do not
        // create one (an unchanged empty session must not set a cookie).
        if (!$this->hasPayload) {
            return true;
        }

        return $this->write($id, $data);
    }

    public function read(string $id): string|false
    {
        if (!isset($this->data)) {
            $this->decode();
        }
        $this->currentId = $id;

        return $this->data;
    }

    public function write(string $id, string $data): bool
    {
        $this->data = $data;
        $this->hasPayload = true;
        $this->currentId = $id;
        $value = base64_encode($this->encrypt->encrypt($data));

        return setcookie($this->name, $value, [
            'expires' => time() + $this->ttl,
        ] + $this->cookieOptions);
    }

    public function destroy(string $id): bool
    {
        $this->data = '';
        $this->hasPayload = false;
        $this->currentId = null;

        return setcookie($this->name, '', [
            'expires' => time() - 86400,
        ] + $this->cookieOptions);
    }

    private function decode(): void
    {
        $this->data = '';
        $this->hasPayload = false;

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
            $this->hasPayload = true;
        }
    }
}
