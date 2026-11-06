<?php

declare(strict_types=1);

use App\Config;
use App\I18n;
use App\Security;

/** Escape for HTML text and attributes. Use for EVERY dynamic value. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function t(string $key, array $vars = []): string
{
    return I18n::t($key, $vars);
}

function ta(string $key): array
{
    return I18n::arr($key);
}

/** Site-relative URL with language prefix, e.g. url('/account-age') => /ar/account-age */
function url(string $path = '/', ?string $lang = null): string
{
    $lang ??= I18n::current();
    return '/' . $lang . ($path === '' ? '/' : $path);
}

function base_url(): string
{
    $base = rtrim((string) Config::get('base_url', ''), '/');
    if ($base !== '') {
        return $base;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    return (Security::isHttps() ? 'https' : 'http') . '://' . $host;
}

function abs_url(string $path): string
{
    return base_url() . $path;
}

function asset(string $path): string
{
    $file = ROOT . '/public/assets/' . $path;
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return '/assets/' . $path . '?v=' . $v;
}

function site_name(): string
{
    return (string) Config::get('site_name', 'TokLens');
}

function icon(string $name, string $class = ''): string
{
    return '<svg class="ic ' . e($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
}

function fmt_num(int|float|null $n): string
{
    if ($n === null) {
        return '—';
    }
    return number_format((float) $n, 0, '.', ',');
}

function fmt_compact(int|float|null $n): string
{
    if ($n === null) {
        return '—';
    }
    $n = (float) $n;
    foreach ([[1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$div, $suffix]) {
        if (abs($n) >= $div) {
            $v = $n / $div;
            return rtrim(rtrim(number_format($v, $v < 10 ? 2 : 1, '.', ''), '0'), '.') . $suffix;
        }
    }
    return number_format($n, 0, '.', ',');
}

function fmt_date(?int $ts, bool $withTime = true): string
{
    if (!$ts) {
        return t('na');
    }
    if (class_exists(IntlDateFormatter::class)) {
        $locale = I18n::meta()['locale'];
        $f = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::LONG,
            $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
            'UTC',
            IntlDateFormatter::GREGORIAN
        );
        $out = $f->format($ts);
        if (is_string($out) && $out !== '') {
            return $out . ($withTime ? ' UTC' : '');
        }
    }
    return gmdate($withTime ? 'Y-m-d H:i' : 'Y-m-d', $ts) . ($withTime ? ' UTC' : '');
}

function fmt_age(?int $ts): string
{
    if (!$ts) {
        return t('na');
    }
    $from = (new DateTimeImmutable('@' . $ts));
    $diff = $from->diff(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    return t('age_fmt', ['y' => $diff->y, 'm' => $diff->m, 'd' => $diff->d]);
}

/** JSON safe to embed inside <script> tags. */
function json_script(mixed $data): string
{
    return (string) json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}
