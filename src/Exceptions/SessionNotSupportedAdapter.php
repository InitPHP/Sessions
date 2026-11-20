<?php

/**
 * SessionNotSupportedAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Exceptions;

use RuntimeException;

/**
 * Raised when an adapter cannot be used in the current environment because a
 * required PHP extension or third-party library is missing.
 */
class SessionNotSupportedAdapter extends RuntimeException
{
    public function __construct(string $message = 'The requested session adapter is not supported in this environment.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
