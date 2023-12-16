<?php
/**
 * AbstractAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions;

abstract class AbstractAdapter implements Interfaces\AdapterInterface
{

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    abstract public function destroy(string $id): bool;

    /**
     * @inheritDoc
     */
    public function gc(int $max_lifetime): int|false
    {
        return $max_lifetime;
    }

    /**
     * @inheritDoc
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    abstract public function read(string $id): string|false;

    /**
     * @inheritDoc
     */
    abstract public function write(string $id, string $data): bool;


}
