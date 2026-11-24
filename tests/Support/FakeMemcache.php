<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

/**
 * In-memory stand-in for the legacy `\Memcache` driver covering the calls
 * MemCacheAdapter makes. Time is a manual clock.
 */
final class FakeMemcache
{
    public int $clock = 0;

    /** @var array<string, array{0: string, 1: int|null}> value and absolute expiry */
    public array $items = [];

    public function advance(int $seconds): void
    {
        $this->clock += $seconds;
        foreach ($this->items as $key => [, $expiresAt]) {
            if ($expiresAt !== null && $expiresAt <= $this->clock) {
                unset($this->items[$key]);
            }
        }
    }

    public function connect(string $host, int $port = 11211): bool
    {
        return true;
    }

    public function addServer(string $host, int $port = 11211, bool $persistent = true, int $weight = 0): bool
    {
        return true;
    }

    public function get(string $key): mixed
    {
        return isset($this->items[$key]) ? $this->items[$key][0] : false;
    }

    public function set(string $key, mixed $value, int $flag = 0, int $expire = 0): bool
    {
        $this->items[$key] = [(string) $value, $expire > 0 ? $this->clock + $expire : null];

        return true;
    }

    public function replace(string $key, mixed $value, int $flag = 0, int $expire = 0): bool
    {
        return isset($this->items[$key]) && $this->set($key, $value, $flag, $expire);
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function close(): bool
    {
        return true;
    }
}
