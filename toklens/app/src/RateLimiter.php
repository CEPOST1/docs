<?php

declare(strict_types=1);

namespace App;

/** Fixed-window rate limiter backed by locked files. IPs are stored only as salted hashes. */
final class RateLimiter
{
    public static function hit(string $bucket): bool
    {
        [$max, $window] = Config::get('rate_limits.' . $bucket, [60, 60]);
        $id = hash_hmac('sha256', $bucket . '|' . Security::clientIp(), Security::key());
        $file = ROOT . '/storage/ratelimit/' . $id;
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return true; // fail open if storage is unavailable
        }
        try {
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            $now = time();
            [$start, $count] = array_map('intval', explode(':', $raw ?: '0:0') + [0, 0]);
            if ($now - $start >= $window) {
                $start = $now;
                $count = 0;
            }
            $count++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $start . ':' . $count);
            fflush($fp);
            if ($count > $max) {
                header('Retry-After: ' . max(1, $window - ($now - $start)));
                return false;
            }
            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            if (random_int(1, 1000) === 1) {
                self::gc();
            }
        }
    }

    private static function gc(): void
    {
        foreach (glob(ROOT . '/storage/ratelimit/*') ?: [] as $f) {
            if (@filemtime($f) < time() - 7200) {
                @unlink($f);
            }
        }
    }
}
