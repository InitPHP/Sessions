<?php

/**
 * FileAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_dir;
use function is_writable;
use function preg_match;
use function time;
use function touch;
use function unlink;

/**
 * Stores sessions as plain files in a directory of your choosing.
 *
 * Useful when you want a custom save path (and prefix) without touching PHP's
 * global `session.save_path` ini setting.
 *
 * Supported options:
 *  - `path`   (string, required) Writable directory the files live in.
 *  - `prefix` (string, optional) File name prefix. Defaults to `sess_`.
 *
 * Session ids are used as file names, so only ids made of letters, digits,
 * `,`, `-` and `_` (at most 256 characters) are accepted. Any other id (path
 * separators, `..`, NUL bytes, ...) is treated as "no such session": it is
 * never validated, read, written or deleted, so an id cannot point outside
 * the configured directory.
 *
 * With `session.use_strict_mode` on, a session file counts as existing only
 * while it is younger than `session.gc_maxlifetime`; the file's modification
 * time is renewed by {@see updateTimestamp()}.
 */
class FileAdapter extends AbstractAdapter
{
    private string $path;

    private string $prefix = 'sess_';

    /**
     * @param array<string, mixed> $options
     * @throws SessionInvalidArgumentException When the path is missing or not a
     *         writable directory.
     */
    public function __construct(array $options)
    {
        if (!isset($options['path'])) {
            throw new SessionInvalidArgumentException('The "path" option is required.');
        }

        $path = (string) $options['path'];
        if (!is_dir($path) || !is_writable($path)) {
            throw new SessionInvalidArgumentException('The given path must be an existing, writable directory: ' . $path);
        }

        $this->path = $path;

        if (isset($options['prefix'])) {
            $this->prefix = (string) $options['prefix'];
        }
    }

    public function validateId(string $id): bool
    {
        $file = $this->filename($id);
        if ($file === null) {
            return false;
        }

        $mtime = file_exists($file) ? @filemtime($file) : false;

        return $mtime !== false && $mtime >= time() - $this->sessionLifetime();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $file = $this->filename($id);
        if ($file === null) {
            return false;
        }

        // Only renew an existing session; never create a file here.
        return file_exists($file) && @touch($file);
    }

    public function read(string $id): string|false
    {
        $file = $this->filename($id);
        if ($file === null || !file_exists($file)) {
            return '';
        }

        return (string) @file_get_contents($file);
    }

    public function write(string $id, string $data): bool
    {
        $file = $this->filename($id);
        if ($file === null) {
            return false;
        }

        return file_put_contents($file, $data) !== false;
    }

    public function destroy(string $id): bool
    {
        $file = $this->filename($id);
        if ($file !== null && file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $files = glob($this->path . '/' . $this->prefix . '*');
        if ($files === false) {
            return false;
        }

        $deleted = 0;
        $threshold = time() - $max_lifetime;
        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < $threshold && unlink($file)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * Maps a session id to its file, or null when the id is not safe to use
     * as a file name.
     */
    private function filename(string $id): ?string
    {
        if (preg_match('/^[A-Za-z0-9,_-]{1,256}$/D', $id) !== 1) {
            return null;
        }

        return $this->path . '/' . $this->prefix . $id;
    }
}
