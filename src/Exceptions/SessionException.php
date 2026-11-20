<?php

/**
 * SessionException.php
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

use Exception;

/**
 * Base exception for every error raised by the package.
 *
 * Catching this type catches all package-level runtime failures, including
 * {@see SessionAdapterException}.
 */
class SessionException extends Exception
{
}
