<?php

declare(strict_types=1);

namespace App;

/**
 * On/off switches for site sections, editable from the admin panel (stored in SQLite `settings`).
 * Defaults come from config 'features' (all on when not set).
 */
final class Features
{
    /** key => [Arabic label for the admin panel, description] */
    public const LIST = [
        'downloader' => ['قسم تحميل الفيديو', 'صفحة "تحميل الفيديو" بالرابط، ورابطها في القوائم.'],
        'stories' => ['قسم الستوري', 'صفحة "عارض الستوري" وتبويب الستوري داخل صفحة الحساب.'],
        'downloads' => ['أزرار التحميل', 'أزرار تحميل الفيديو / MP3 / الصور داخل المشغّل وصفحة الحساب (يبقى التشغيل متاحاً).'],
        'age' => ['قسم عمر الحساب', 'صفحة "عمر الحساب".'],
        'articles' => ['قسم المقالات', 'صفحات المقالات ورابطها في القوائم.'],
    ];

    /** Which feature a site path belongs to. */
    public const PATHS = [
        '/video-downloader' => 'downloader',
        '/story-viewer' => 'stories',
        '/account-age' => 'age',
        '/articles' => 'articles',
    ];

    private static ?array $values = null;

    private static function load(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }
        $v = [];
        foreach (Db::all("SELECT k, v FROM settings WHERE k LIKE 'feature.%'") as $row) {
            $v[substr((string) $row['k'], 8)] = $row['v'] === '1';
        }
        return self::$values = $v;
    }

    public static function on(string $key): bool
    {
        $v = self::load();
        if (array_key_exists($key, $v)) {
            return $v[$key];
        }
        return (bool) Config::get('features.' . $key, true);
    }

    /** True when the site path (without language prefix) is enabled. */
    public static function pathOn(string $path): bool
    {
        foreach (self::PATHS as $prefix => $key) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return self::on($key);
            }
        }
        return true;
    }

    public static function set(string $key, bool $on): void
    {
        if (isset(self::LIST[$key])) {
            Db::run('INSERT OR REPLACE INTO settings(k, v) VALUES(?, ?)', ['feature.' . $key, $on ? '1' : '0']);
            self::$values = null;
        }
    }
}
