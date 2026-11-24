<?php

declare(strict_types=1);

namespace InitPHP\Sessions\Tests\Support;

/**
 * In-memory stand-in for `\Memcached` covering the calls MemCacheAdapter
 * makes. Time is a manual clock so expiry can be asserted without sleeping.
 */
final class FakeMemcached
{
    public const OPT_BINARY_PROTOCOL = 18;
    public const RES_SUCCESS = 0;
    public const RES_NOTFOUND = 16;
    public const RES_FAILURE = 1;
    public const RES_BAD_KEY_PROVIDED = 33;

    public int $clock = 0;

    /** @var array<string, array{0: string, 1: int|null}> value and absolute expiry */
    public array $items = [];

    /** When true, get() and touch() fail with a server error instead of answering. */
    public bool $failing = false;

    private int $resultCode = self::RES_SUCCESS;

    /** @var array<string, bool> */
    private array $servers = [];

    public function advance(int $seconds): void
    {
        $this->clock += $seconds;
        foreach ($this->items as $key => [, $expiresAt]) {
            if ($expiresAt !== null && $expiresAt <= $this->clock) {
                unset($this->items[$key]);
            }
        }
    }

    public function setOption(int $option, mixed $value): bool
    {
        return true;
    }

    public function addServer(string $host, int $port, int $weight = 0): bool
    {
        $this->servers[$host . ':' . $port] = true;

        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStats(): array
    {
        return array_map(static fn(): array => ['pid' => 1], $this->servers);
    }

    public function getResultCode(): int
    {
        return $this->resultCode;
    }

    public function get(string $key): mixed
    {
        if ($this->isBadKey($key)) {
            $this->resultCode = self::RES_BAD_KEY_PROVIDED;

            return false;
        }

        if ($this->failing) {
            $this->resultCode = self::RES_FAILURE;

            return false;
        }

        if (!isset($this->items[$key])) {
            $this->resultCode = self::RES_NOTFOUND;

            return false;
        }

        $this->resultCode = self::RES_SUCCESS;

        return $this->items[$key][0];
    }

    public function set(string $key, mixed $value, int $expiration = 0): bool
    {
        $this->items[$key] = [(string) $value, $expiration > 0 ? $this->clock + $expiration : null];

        return true;
    }

    public function touch(string $key, int $expiration = 0): bool
    {
        if ($this->isBadKey($key)) {
            $this->resultCode = self::RES_BAD_KEY_PROVIDED;

            return false;
        }

        if ($this->failing) {
            $this->resultCode = self::RES_FAILURE;

            return false;
        }

        if (!isset($this->items[$key])) {
            $this->resultCode = self::RES_NOTFOUND;

            return false;
        }

        $this->items[$key][1] = $expiration > 0 ? $this->clock + $expiration : null;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function quit(): bool
    {
        return true;
    }

    /** php-memcached refuses keys that are empty, longer than 250 bytes or contain a space. */
    private function isBadKey(string $key): bool
    {
        return $key === '' || strlen($key) > 250 || str_contains($key, ' ');
    }
}
