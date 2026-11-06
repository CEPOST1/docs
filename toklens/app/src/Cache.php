<?php

declare(strict_types=1);

namespace App;

/** File cache storing JSON only (never unserialize() untrusted data). */
final class Cache
{
    private static function path(string $key): string
    {
        $h = hash('sha256', $key);
        return ROOT . '/storage/cache/' . substr($h, 0, 2) . '/' . $h . '.json';
    }

    public static function get(string $key): mixed
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data) || !isset($data['e']) || $data['e'] < time()) {
            return null;
        }
        return $data['v'] ?? null;
    }

    public static function set(string $key, mixed $value, int $ttl): void
    {
        $file = self::path($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode(['e' => time() + $ttl, 'v' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false && @file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
        if (random_int(1, 500) === 1) {
            self::gc();
        }
    }

    /** Remove expired entries (runs occasionally). */
    public static function gc(): void
    {
        $now = time();
        foreach (glob(ROOT . '/storage/cache/*/*.json') ?: [] as $f) {
            if (@filemtime($f) < $now - 86400) {
                @unlink($f);
            }
        }
        foreach (glob(ROOT . '/storage/media/*') ?: [] as $f) {
            if (@filemtime($f) < $now - 86400) {
                @unlink($f);
            }
        }
    }
}
