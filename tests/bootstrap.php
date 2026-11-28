<?php

declare(strict_types=1);

use InitPHP\Sessions\Tests\Support\AbstractAdapterLoadFilter;

require __DIR__ . '/../vendor/autoload.php';

// PHP 8.6 warns, once per process, while it links AbstractAdapter (it does not
// implement create_sid()/validateId()). Load the class under a filter that
// swallows only those two messages, before PHPUnit installs its own handler.
AbstractAdapterLoadFilter::load();
