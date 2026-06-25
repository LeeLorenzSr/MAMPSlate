<?php
declare(strict_types=1);

/**
 * File-backed sliding-window rate limiter.
 *
 * File-backed (under cache/ratelimit/) so that a flood of failed login or
 * password-reset attempts does not turn into a flood of database writes. Each
 * key keeps a list of recent hit timestamps; once the count within the window
 * reaches $max, further attempts are refused until the oldest timestamp ages
 * out.
 */
final class RateLimiter
{
    public function __construct(private string $cacheDir)
    {
    }

    /**
     * Record a hit and return true if the action is allowed, false if the limit
     * has been reached.
     */
    public function attempt(string $key, int $max, int $windowSeconds): bool
    {
        $file = $this->path($key);
        $now = time();
        $cutoff = $now - $windowSeconds;

        $entries = $this->read($file);
        $entries = array_values(array_filter($entries, fn($t) => $t > $cutoff));

        if (count($entries) >= $max) {
            return false;
        }

        $entries[] = $now;
        $this->write($file, $entries);

        return true;
    }

    /**
     * Seconds to wait before the next attempt would succeed.
     */
    public function retryAfter(string $key, int $windowSeconds): int
    {
        $file = $this->path($key);
        $entries = $this->read($file);
        $entries = array_values(array_filter($entries, fn($t) => $t > time() - $windowSeconds));
        if ($entries === []) {
            return 0;
        }

        return max(0, min($entries) + $windowSeconds - time());
    }

    private function path(string $key): string
    {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        return $this->cacheDir . '/' . hash('sha256', $key) . '.json';
    }

    private function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? array_map('intval', $data) : [];
    }

    private function write(string $file, array $entries): void
    {
        @file_put_contents($file, json_encode($entries), LOCK_EX);
    }
}
