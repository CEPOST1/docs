<?php

declare(strict_types=1);

namespace App;

final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        $data = require $file;
        self::$data = is_array($data) ? $data : [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Test helper: override a top-level key at runtime. */
    public static function set(string $key, mixed $value): void
    {
        self::$data[$key] = $value;
    }
}
