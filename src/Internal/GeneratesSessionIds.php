<?php

/**
 * GeneratesSessionIds.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Internal;

use function ini_get;
use function max;
use function ord;
use function random_bytes;
use function strlen;

/**
 * Gives the bundled adapters PHP's `SessionIdInterface::create_sid()`, which
 * PHP 8.6 asks every session handler to provide (it becomes mandatory in
 * PHP 9.0).
 *
 * The id has exactly the shape PHP's own generator produces: random bits
 * rendered with `session.sid_bits_per_character` bits per character (4, 5 or 6)
 * and `session.sid_length` characters, 32 hexadecimal characters by default. PHP
 * checks an id from `session_create_id()` (and from `session_regenerate_id()`
 * under strict mode) for collisions through `validateId()`, but not the id of a
 * brand-new session, so the randomness here is what protects it. PHP applies
 * any `session_create_id()` prefix itself. Behaviour matches the previous
 * default.
 *
 * @internal Not part of the public API; do not use it in your own adapters.
 */
trait GeneratesSessionIds
{
    /**
     * Creates a new, random session id.
     *
     * @return string The new session id.
     */
    public function create_sid(): string
    {
        return $this->generateSessionId();
    }

    /**
     * The name is deliberately specific and the method private, so it cannot
     * clash with a method of a subclass.
     */
    private function generateSessionId(): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,-';

        $bits = (int) ini_get('session.sid_bits_per_character');
        if ($bits < 4 || $bits > 6) {
            $bits = 4;
        }
        $length = (int) ini_get('session.sid_length');
        if ($length < 22 || $length > 256) {
            $length = 32;
        }

        $bytes = random_bytes(max(1, (int) ceil($length * $bits / 8)));
        $mask = (1 << $bits) - 1;
        $position = 0;
        $window = 0;
        $have = 0;
        $id = '';

        // The same bit-to-character conversion PHP uses.
        while (strlen($id) < $length) {
            if ($have < $bits) {
                $window |= ord($bytes[$position++]) << $have;
                $have += 8;
            }

            $id .= $alphabet[$window & $mask];
            $window >>= $bits;
            $have -= $bits;
        }

        return $id;
    }
}
