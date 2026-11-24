<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

/**
 * In-memory stand-in for `\Redis` covering the calls RedisAdapter makes.
 * Time is a manual clock so expiry can be asserted without sleeping.
 */
final class FakeRedis
{
    public int $clock = 0;

    /** When true, every read and expiry command throws, like a lost connection. */
    public bool $failing = false;

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

    /** Remaining lifetime in seconds, null for a key without expiry or a missing key. */
    public function ttlOf(string $key): ?int
    {
        $expiresAt = $this->items[$key][1] ?? null;

        return $expiresAt === null ? null : $expiresAt - $this->clock;
    }

    public function select(int $index): bool
    {
        return true;
    }

    public function get(string $key): mixed
    {
        $this->failIfDown();

        return isset($this->items[$key]) ? $this->items[$key][0] : false;
    }

    public function set(string $key, mixed $value): bool
    {
        $this->items[$key] = [(string) $value, null];

        return true;
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        $this->items[$key] = [(string) $value, $this->clock + $ttl];

        return true;
    }

    public function del(string ...$keys): int
    {
        $deleted = 0;
        foreach ($keys as $key) {
            if (isset($this->items[$key])) {
                unset($this->items[$key]);
                ++$deleted;
            }
        }

        return $deleted;
    }

    public function exists(string $key): int
    {
        $this->failIfDown();

        return isset($this->items[$key]) ? 1 : 0;
    }

    public function expire(string $key, int $timeout): bool
    {
        $this->failIfDown();

        if (!isset($this->items[$key])) {
            return false;
        }

        $this->items[$key][1] = $this->clock + $timeout;

        return true;
    }

    private function failIfDown(): void
    {
        if ($this->failing) {
            throw new \RuntimeException('Connection lost');
        }
    }
}
