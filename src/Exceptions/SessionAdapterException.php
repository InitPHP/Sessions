<?php

/**
 * SessionAdapterException.php
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

/**
 * Raised when a save handler fails to talk to its backing store, e.g. a
 * connection, authentication, or driver-level error.
 *
 * Extends {@see SessionException} so a single catch block can handle every
 * package failure.
 */
class SessionAdapterException extends SessionException
{
}
