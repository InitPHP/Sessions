<?php

/**
 * SessionInvalidArgumentException.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an adapter or the manager receives invalid configuration or
 * arguments (missing required options, wrong types, ...).
 */
class SessionInvalidArgumentException extends InvalidArgumentException
{
}
