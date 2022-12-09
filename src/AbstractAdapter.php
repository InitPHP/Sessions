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
    public function close()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    abstract public function destroy($id);

    /**
     * @inheritDoc
     */
    public function gc($max_lifetime)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function open($path, $name)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    abstract public function read($id);

    /**
     * @inheritDoc
     */
    abstract public function write($id, $data);


}
