<?php

declare(strict_types=1);

namespace App;

final class I18n
{
    /** To add a language: copy lang/en.php to lang/<code>.php, translate it, and add a line here. */
    public const LANGS = [
        'en'  => ['name' => 'English',          'dir' => 'ltr', 'locale' => 'en_US', 'hreflang' => 'en',    'og' => 'en_US'],
        'ar'  => ['name' => 'العربية',           'dir' => 'rtl', 'locale' => 'ar',    'hreflang' => 'ar',    'og' => 'ar_AR'],
        'ckb' => ['name' => 'کوردی (سۆرانی)',    'dir' => 'rtl', 'locale' => 'ckb',   'hreflang' => 'ku-IQ', 'og' => 'ku_IQ'],
        'kmr' => ['name' => 'Kurdî (Kurmancî)',  'dir' => 'ltr', 'locale' => 'ku',    'hreflang' => 'ku-TR', 'og' => 'ku_TR'],
        'tr'  => ['name' => 'Türkçe',            'dir' => 'ltr', 'locale' => 'tr_TR', 'hreflang' => 'tr',    'og' => 'tr_TR'],
        'fa'  => ['name' => 'فارسی',             'dir' => 'rtl', 'locale' => 'fa_IR', 'hreflang' => 'fa',    'og' => 'fa_IR'],
        'fr'  => ['name' => 'Français',          'dir' => 'ltr', 'locale' => 'fr_FR', 'hreflang' => 'fr',    'og' => 'fr_FR'],
        'es'  => ['name' => 'Español',           'dir' => 'ltr', 'locale' => 'es_ES', 'hreflang' => 'es',    'og' => 'es_ES'],
    ];

    private static string $lang = 'en';
    private static array $strings = [];
    private static array $fallback = [];

    public static function supported(string $code): bool
    {
        return isset(self::LANGS[$code]) && is_file(ROOT . '/lang/' . $code . '.php');
    }

    public static function codes(): array
    {
        return array_values(array_filter(array_keys(self::LANGS), [self::class, 'supported']));
    }

    public static function set(string $code): void
    {
        if (!self::supported($code)) {
            $code = (string) Config::get('default_lang', 'en');
        }
        self::$lang = $code;
        self::$fallback = require ROOT . '/lang/en.php';
        self::$strings = $code === 'en' ? self::$fallback : (require ROOT . '/lang/' . $code . '.php');
    }

    public static function current(): string
    {
        return self::$lang;
    }

    public static function meta(?string $code = null): array
    {
        return self::LANGS[$code ?? self::$lang];
    }

    public static function dir(): string
    {
        return self::LANGS[self::$lang]['dir'];
    }

    public static function t(string $key, array $vars = []): string
    {
        $s = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        if (!is_string($s)) {
            return $key;
        }
        if ($vars) {
            $rep = [];
            foreach ($vars as $k => $v) {
                $rep['{' . $k . '}'] = (string) $v;
            }
            $s = strtr($s, $rep);
        }
        return $s;
    }

    public static function arr(string $key): array
    {
        $a = self::$strings[$key] ?? self::$fallback[$key] ?? [];
        return is_array($a) ? $a : [];
    }

    /** Pick a language from the cookie or the Accept-Language header. */
    public static function detect(): string
    {
        $c = (string) ($_COOKIE['lang'] ?? '');
        if (self::supported($c)) {
            return $c;
        }
        $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $aliases = ['ku' => 'ckb', 'sdh' => 'ckb', 'ckb' => 'ckb', 'kmr' => 'kmr'];
        foreach (explode(',', $header) as $part) {
            $tag = trim(explode(';', $part)[0]);
            $tag = str_replace('_', '-', $tag);
            if ($tag === '') {
                continue;
            }
            if (str_starts_with($tag, 'ku-tr') || str_starts_with($tag, 'ku-latn') || str_starts_with($tag, 'ku-sy')) {
                return self::supported('kmr') ? 'kmr' : 'en';
            }
            $base = explode('-', $tag)[0];
            $code = $aliases[$base] ?? $base;
            if (self::supported($code)) {
                return $code;
            }
        }
        return (string) Config::get('default_lang', 'en');
    }

    public static function rememberCookie(string $code): void
    {
        if (($_COOKIE['lang'] ?? '') === $code) {
            return;
        }
        setcookie('lang', $code, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => Security::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
