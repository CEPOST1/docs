<?php

declare(strict_types=1);

namespace App;

/**
 * Articles live in content/articles/<slug>.php and return:
 * ['date' => 'Y-m-d', 'updated' => 'Y-m-d', 'tool' => '/account-age', 'i18n' => ['en' => ['title','description','body'], ...]]
 * The body is trusted HTML written by the site owner.
 */
final class Articles
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $list = [];
        foreach (glob(ROOT . '/content/articles/*.php') ?: [] as $file) {
            $slug = basename($file, '.php');
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                continue;
            }
            $a = require $file;
            if (is_array($a) && !empty($a['i18n'])) {
                $a['slug'] = $slug;
                $list[$slug] = $a;
            }
        }
        uasort($list, static fn($a, $b) => strcmp($b['date'], $a['date']));
        return self::$all = $list;
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** Articles for a language; falls back to English versions for languages without translations. */
    public static function forLang(string $lang): array
    {
        $out = [];
        foreach (self::all() as $slug => $a) {
            $l = isset($a['i18n'][$lang]) ? $lang : (isset($a['i18n']['en']) ? 'en' : array_key_first($a['i18n']));
            $out[] = ['slug' => $slug, 'lang' => $l, 'date' => $a['date'], 'tool' => $a['tool'] ?? null] + $a['i18n'][$l];
        }
        return $out;
    }

    public static function readingMinutes(string $html): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags($html))) ?: [];
        return max(1, (int) ceil(count($words) / 200));
    }
}
