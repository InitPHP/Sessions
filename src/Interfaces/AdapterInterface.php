<?php

/**
 * AdapterInterface.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Interfaces;

use SessionHandlerInterface;

/**
 * Marker interface for the package's session save handlers.
 *
 * It extends PHP's native {@see \SessionHandlerInterface}, so any adapter can
 * be passed straight to {@see session_set_save_handler()}. The package adds no
 * extra methods on top of the native contract; the interface exists only to
 * give the adapters a common, package-owned type to hint against.
 */
interface AdapterInterface extends SessionHandlerInterface {}
