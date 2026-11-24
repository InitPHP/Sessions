<?php

/**
 * ResolvesSessionLifetime.php
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

/**
 * Shared by the bundled adapters that expire sessions by age (file, PDO,
 * MongoDB): returns the lifetime PHP itself uses for garbage collection.
 *
 * @internal Not part of the public API; do not use it in your own adapters.
 */
trait ResolvesSessionLifetime
{
    /**
     * The `session.gc_maxlifetime` setting in seconds (PHP's default of 1440
     * when it is unset).
     *
     * The name is deliberately specific and the method private, so it cannot
     * clash with a method of a subclass.
     */
    private function resolveSessionLifetime(): int
    {
        $lifetime = ini_get('session.gc_maxlifetime');

        return $lifetime === false || $lifetime === '' ? 1440 : (int) $lifetime;
    }
}
