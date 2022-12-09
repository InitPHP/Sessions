<?php
/**
 * GetterSetter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions\Classes;

class GetterSetter
{

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function set(string $key, $value): self
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public function remove(string ...$keys): self
    {
        foreach ($keys as $key) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }

        return $this;
    }

    public function delete(string ...$keys): self
    {
        return $this->remove(...$keys);
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    public function push(string $key, $value)
    {
        $_SESSION[$key] = $value;

        return $value;
    }

    public function pull(string $key, $default = null)
    {
        if (array_key_exists($key, $_SESSION)) {
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);
        }

        return $value ?? $default;
    }

    public function setAssoc(array $assoc, bool $reset = false): self
    {
        $set = [];
        foreach ($assoc as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $set[$key] = $value;
        }

        if (!empty($set)) {
            $_SESSION = $reset ? $set : array_merge($_SESSION, $set);
        }

        return $this;
    }

}
