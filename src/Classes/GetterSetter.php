<?php

/**
 * GetterSetter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Classes;

/**
 * Thin, fluent wrapper around the `$_SESSION` superglobal.
 *
 * Every method is null-safe: if the session has not been started yet (so
 * `$_SESSION` is not populated), reads return their defaults and writes
 * initialise the array, instead of raising a type error.
 */
class GetterSetter
{
    /**
     * Determines whether the given key exists in the session.
     *
     * @param string $key The session key.
     * @return bool True when the key is set.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION) && \array_key_exists($key, $_SESSION);
    }

    /**
     * Stores a value under the given key.
     *
     * @param string $key   The session key.
     * @param mixed  $value The value to store.
     * @return $this
     */
    public function set(string $key, mixed $value): self
    {
        $_SESSION[$key] = $value;

        return $this;
    }

    /**
     * Returns the value stored under the given key.
     *
     * @param string $key     The session key.
     * @param mixed  $default Returned when the key is absent.
     * @return mixed The stored value, or $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $_SESSION[$key] : $default;
    }

    /**
     * Removes one or more keys from the session.
     *
     * @param string ...$keys The session keys to remove.
     * @return $this
     */
    public function remove(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }

        return $this;
    }

    /**
     * Alias of {@see remove()}.
     *
     * @param string ...$keys The session keys to remove.
     * @return $this
     */
    public function delete(string ...$keys): self
    {
        return $this->remove(...$keys);
    }

    /**
     * Returns the whole session payload.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Stores a value and returns the value itself (rather than `$this`).
     *
     * Handy when the caller wants to set and immediately use the value in a
     * single expression.
     *
     * @param string $key   The session key.
     * @param mixed  $value The value to store.
     * @return mixed The stored value.
     */
    public function push(string $key, mixed $value): mixed
    {
        $_SESSION[$key] = $value;

        return $value;
    }

    /**
     * Returns the value stored under the given key and removes it.
     *
     * @param string $key     The session key.
     * @param mixed  $default Returned when the key is absent.
     * @return mixed The stored value, or $default.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        $value = $_SESSION[$key];
        unset($_SESSION[$key]);

        return $value;
    }

    /**
     * Merges (or replaces) the session with an associative array.
     *
     * Only string keys are considered; numeric keys are skipped.
     *
     * @param array<array-key, mixed> $assoc The values to store.
     * @param bool                    $reset When true, the session is replaced
     *                                        by $assoc instead of merged.
     * @return $this
     */
    public function setAssoc(array $assoc, bool $reset = false): self
    {
        $set = [];
        foreach ($assoc as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }
            $set[$key] = $value;
        }

        if ($reset) {
            $_SESSION = $set;
        } elseif ($set !== []) {
            $_SESSION = array_merge($_SESSION ?? [], $set);
        }

        return $this;
    }
}
