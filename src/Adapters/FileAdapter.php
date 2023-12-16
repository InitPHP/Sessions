<?php
/**
 * FileAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Interfaces\AdapterInterface;

class FileAdapter extends AbstractAdapter implements AdapterInterface
{

    private $path;

    private string $prefix = 'sess_';

    public function __construct(array $options)
    {
        $path = $options['path'];
        if (!is_dir($path) || !is_writable($path)) {
            throw new \InvalidArgumentException("Belirtilen dizin geçerli ve yazılabilir olmalıdır.");
        }
        $this->path = $path;
        isset($options['prefix']) && $this->prefix = $options['prefix'];
    }


    public function read(string $id): string|false
    {
        $id = $this->prefix . $id;

        return (string) @file_get_contents("{$this->path}/$id");
    }

    public function write(string $id, string $data): bool
    {
        $id = $this->prefix . $id;

        return file_put_contents("{$this->path}/$id", $data) !== false;
    }

    public function destroy(string $id): bool
    {
        $id = $this->prefix . $id;

        $session = "{$this->path}/$id";
        if (file_exists($session)) {
            unlink($session);
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $files = glob("{$this->path}/{$this->prefix}*");
        $currentTime = time();

        foreach ($files as $file) {
            if (filemtime($file) + $max_lifetime < $currentTime && file_exists($file)) {
                unlink($file);
            }
        }

        return $max_lifetime;
    }

}