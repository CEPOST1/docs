<?php

declare(strict_types=1);

namespace App;

final class Security
{
    private static ?string $nonce = null;
    private static ?string $key = null;

    /** Hosts our server is allowed to talk to for API calls. */
    public const API_HOSTS = ['tiktok.com', 'tikwm.com', 'tikwmapi.com', 'rapidapi.com'];

    /** Hosts allowed for proxied media (images, video, audio). */
    public const MEDIA_HOSTS = [
        'tiktokcdn.com', 'tiktokcdn-us.com', 'tiktokcdn-eu.com', 'tiktokv.com', 'tiktokv.us',
        'tiktokv.eu', 'tiktok.com', 'ibyteimg.com', 'ibytedtos.com', 'byteoversea.com',
        'muscdn.com', 'tikwm.com', 'tikwmapi.com',
    ];

    public static function nonce(): string
    {
        return self::$nonce ??= self::b64(random_bytes(18));
    }

    public static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        $key = (string) Config::get('app_key', '');
        if (strlen($key) < 32) {
            $file = ROOT . '/storage/.app_key';
            $key = is_file($file) ? trim((string) file_get_contents($file)) : '';
            if (strlen($key) < 32) {
                $key = bin2hex(random_bytes(32));
                if (@file_put_contents($file, $key, LOCK_EX) !== false) {
                    @chmod($file, 0600);
                }
            }
        }
        return self::$key = $key;
    }

    public static function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, self::key());
    }

    public static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function unb64(string $s): ?string
    {
        if ($s === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $s)) {
            return null;
        }
        $raw = base64_decode(strtr($s, '-_', '+/'), true);
        return $raw === false ? null : $raw;
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        if (Config::get('trust_proxy')) {
            $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            $cf = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
            return $proto === 'https' || str_contains($cf, '"https"');
        }
        $base = (string) Config::get('base_url', '');
        return str_starts_with($base, 'https://');
    }

    /** Common security headers for every response. */
    public static function baseHeaders(): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /** Headers for HTML documents, including a strict nonce-based CSP. */
    public static function htmlHeaders(): void
    {
        self::baseHeaders();
        $n = self::nonce();
        $ts = Guard::turnstileKey() !== '';
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$n'" . ($ts ? ' https://challenges.cloudflare.com' : ''),
            "style-src 'self' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "connect-src 'self'" . ($ts ? ' https://challenges.cloudflare.com' : ''),
            "worker-src 'self'",
            "manifest-src 'self'",
            $ts ? 'frame-src https://challenges.cloudflare.com' : "frame-src 'none'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
        ];
        if (self::isHttps()) {
            $csp[] = 'upgrade-insecure-requests';
        }
        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('Content-Type: text/html; charset=utf-8');
        // Pages embed a short-lived API token: tell LiteSpeed / CDN page caches not to store them.
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('CDN-Cache-Control: no-store');
    }

    public static function clientIp(): string
    {
        if (Config::get('trust_proxy')) {
            $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
            if (filter_var($cf, FILTER_VALIDATE_IP)) {
                return $cf;
            }
            $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                $first = trim(explode(',', $xff)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    // ---------------------------------------------------------------- API token


    /** Stateless token embedded in pages; required by the JSON API so it cannot be hot-linked. */
    public static function apiToken(): string
    {
        $ts = (string) time();
        return $ts . '.' . substr(self::hmac('api|' . $ts), 0, 32);
    }

    public static function verifyApiToken(?string $token): bool
    {
        if (!is_string($token) || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $token, $m)) {
            return false;
        }
        $ts = (int) $m[1];
        $now = time();
        if ($ts > $now + 300 || $now - $ts > 24 * 3600) {
            return false;
        }
        // Not bound to the User-Agent: CDNs / page caches can serve a page generated for another browser.
        return hash_equals(substr(self::hmac('api|' . $m[1]), 0, 32), $m[2]);
    }

    /** Reject cross-site requests to the JSON API. */
    public static function sameOrigin(): bool
    {
        $site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $oh = parse_url($origin, PHP_URL_HOST);
            $host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
            return is_string($oh) && strtolower($oh) === $host;
        }
        return true;
    }

    // ---------------------------------------------------------------- Signed media URLs

    /**
     * Build a signed URL to our media proxy so it can only fetch URLs our server produced.
     * $kind: 'media' (images) or 'stream' (video/audio). $name non-empty => attachment download.
     */
    public static function signMedia(?string $url, string $kind = 'media', string $name = '', string $cookie = ''): ?string
    {
        if ($url === null || $url === '' || !Http::hostAllowed($url, self::MEDIA_HOSTS)) {
            return null;
        }
        // Expiry rounded to 6h windows so the same media keeps the same URL (browser cache friendly).
        $exp = (intdiv(time(), 21600) + 5) * 21600;
        $name = self::safeFilename($name);
        $cookie = Http::safeCookie($cookie);
        $sig = substr(self::hmac("$kind|$exp|$url|$name|$cookie"), 0, 40);
        $q = 'u=' . self::b64($url) . '&e=' . $exp . '&s=' . $sig;
        if ($name !== '') {
            $q .= '&n=' . rawurlencode($name);
        }
        if ($cookie !== '') {
            $q .= '&c=' . self::b64($cookie);
        }
        return '/' . $kind . '?' . $q;
    }

    /** @return array{0:string,1:string,2:string}|null [url, filename, cookie] */
    public static function verifyMedia(string $kind): ?array
    {
        $u = self::unb64((string) ($_GET['u'] ?? ''));
        $exp = (string) ($_GET['e'] ?? '');
        $sig = (string) ($_GET['s'] ?? '');
        $name = self::safeFilename((string) ($_GET['n'] ?? ''));
        $c = (string) ($_GET['c'] ?? '');
        $cookie = $c === '' ? '' : Http::safeCookie((string) self::unb64($c));
        if ($u === null || !ctype_digit($exp) || (int) $exp < time() || !preg_match('/^[a-f0-9]{40}$/', $sig)) {
            return null;
        }
        if (!hash_equals(substr(self::hmac("$kind|$exp|$u|$name|$cookie"), 0, 40), $sig)) {
            return null;
        }
        if (!Http::hostAllowed($u, self::MEDIA_HOSTS)) {
            return null;
        }
        return [$u, $name, $cookie];
    }

    public static function safeFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? '';
        $name = trim($name, '._');
        return substr($name, 0, 80);
    }

    // ---------------------------------------------------------------- Input validation

    /** Accepts "user", "@user", "tiktok.com/@user", full profile/video URLs. Returns lowercase username or null. */
    /**
     * Clean text typed on phones: removes invisible direction marks / zero-width characters,
     * odd spaces, and converts full-width and Arabic/Persian digits to plain ASCII.
     */
    public static function cleanInput(string $s): string
    {
        if (!mb_check_encoding($s, 'UTF-8')) {
            return '';
        }
        $s = (string) preg_replace('/[\p{Cf}\p{Cc}]+/u', '', $s);          // RLM, LRM, ZWSP, BOM, controls
        $s = (string) preg_replace('/[\p{Z}\x{3000}]+/u', ' ', $s);        // NBSP & other spaces → space
        $s = strtr($s, [
            '＠' => '@', '﹫' => '@', '．' => '.', '。' => '.', '＿' => '_', '٫' => '.', '۔' => '.',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        if (class_exists(\Normalizer::class)) {
            $s = (string) \Normalizer::normalize($s, \Normalizer::FORM_KC); // full-width letters → ASCII
        }
        return trim($s);
    }

    public static function normalizeUsername(string $input): ?string
    {
        $input = self::cleanInput($input);
        $input = (string) preg_replace('/\s+/', '', $input);
        if ($input === '' || strlen($input) > 300) {
            return null;
        }
        if (preg_match('~tiktok\.com/@([A-Za-z0-9._]{2,24})(?:[/?#]|$)~i', $input, $m)) {
            $input = $m[1];
        }
        $input = ltrim($input, '@');
        if (!preg_match('/^[A-Za-z0-9._]{2,24}$/', $input)) {
            return null;
        }
        return strtolower($input);
    }

    public static function isTikTokUrl(string $url): bool
    {
        $url = self::cleanInput($url);
        if (strlen($url) > 400 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $p = parse_url($url);
        $scheme = strtolower((string) ($p['scheme'] ?? ''));
        $host = strtolower((string) ($p['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || isset($p['user']) || isset($p['port'])) {
            return false;
        }
        return $host === 'tiktok.com' || str_ends_with($host, '.tiktok.com');
    }

    /** Numeric TikTok user/video ID. */
    public static function isNumericId(string $s): bool
    {
        return (bool) preg_match('/^\d{5,20}$/', $s);
    }
}
