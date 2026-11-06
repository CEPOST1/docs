<?php

declare(strict_types=1);

namespace App;

/**
 * Anti-abuse layer. Nothing on the public web is impossible to automate, so the goal is to make
 * scraping expensive and easy to detect:
 *
 *  1. Signed session cookie (tl_s) — every lookup / API call is tied to a browser session.
 *  2. Browser check — the browser solves a proof-of-work puzzle (and optionally Cloudflare
 *     Turnstile) before any lookup. The pass (tl_v) is bound to the session AND the network prefix.
 *  3. API tokens bound to the session (useless when copied elsewhere).
 *  4. Blocks known automation clients (curl, python, postman, headless browsers, webdriver...).
 *  5. Per-IP and per-session quotas; repeated violations → automatic temporary ban.
 *  6. Real search-engine crawlers (verified by reverse + forward DNS) skip the browser check (SEO).
 */
final class Guard
{
    private const SID_COOKIE = 'tl_s';
    private const PASS_COOKIE = 'tl_v';

    private static ?string $sid = null;
    private static ?bool $crawler = null;

    private const BAD_UA = '~curl|wget|python|httpx|aiohttp|requests|urllib|go-http|java/|okhttp|axios|node-fetch|undici|'
        . 'postman|insomnia|scrapy|libwww|lwp|php/|guzzle|httpie|headlesschrome|phantomjs|puppeteer|playwright|'
        . 'selenium|webdriver|electron|nightmare|zgrab|masscan|nikto|sqlmap|nmap|bot\.html|crawler|spider~i';

    private const CRAWLERS = [
        'googlebot' => ['.googlebot.com', '.google.com', '.googleusercontent.com'],
        'google-inspectiontool' => ['.googlebot.com', '.google.com'],
        'adsbot-google' => ['.googlebot.com', '.google.com'],
        'bingbot' => ['.search.msn.com'],
        'yandex' => ['.yandex.ru', '.yandex.net', '.yandex.com'],
        'applebot' => ['.applebot.apple.com'],
        'baiduspider' => ['.baidu.com', '.baidu.jp'],
    ];

    public static function enabled(): bool
    {
        return (bool) Config::get('guard.enabled', true) && (!Config::get('mock') || Config::get('guard_force'));
    }

    // ------------------------------------------------------------------ session

    /** Current session id from the signed cookie; creates one when $create and headers not sent. */
    public static function sid(bool $create = true): ?string
    {
        if (self::$sid !== null) {
            return self::$sid;
        }
        $c = (string) ($_COOKIE[self::SID_COOKIE] ?? '');
        if (preg_match('/^([a-f0-9]{32})\.([a-f0-9]{20})$/', $c, $m)
            && hash_equals(substr(Security::hmac('sid|' . $m[1]), 0, 20), $m[2])) {
            return self::$sid = $m[1];
        }
        if (!$create || headers_sent()) {
            return null;
        }
        $sid = bin2hex(random_bytes(16));
        self::cookie(self::SID_COOKIE, $sid . '.' . substr(Security::hmac('sid|' . $sid), 0, 20), 86400 * 30);
        return self::$sid = $sid;
    }

    private static function cookie(string $name, string $value, int $ttl): void
    {
        setcookie($name, $value, [
            'expires' => time() + $ttl,
            'path' => '/',
            'secure' => Security::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }

    /** /24 for IPv4, /48 for IPv6: tolerant of small changes, but a pass can't be shared across networks. */
    public static function netPrefix(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $bin = @inet_pton($ip);
            return $bin === false ? $ip : bin2hex(substr($bin, 0, 6));
        }
        $p = explode('.', $ip);
        return count($p) === 4 ? $p[0] . '.' . $p[1] . '.' . $p[2] : $ip;
    }

    // ------------------------------------------------------------------ checks

    public static function ua(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
    }

    public static function badUa(): bool
    {
        $ua = self::ua();
        // Link-preview bots (WhatsApp, Telegram, Facebook…) are not attackers: they just get the check page.
        if (preg_match('~facebookexternalhit|twitterbot|telegrambot|whatsapp|discordbot|slackbot|linkedinbot|skypeuripreview|viber~i', $ua)) {
            return false;
        }
        if (strlen($ua) < 20 || !str_contains($ua, 'Mozilla/')) {
            return true;
        }
        return (bool) preg_match(self::BAD_UA, $ua) && !self::isCrawler();
    }

    /** Real search engine crawler (reverse DNS + forward confirmation, cached). */
    public static function isCrawler(): bool
    {
        if (self::$crawler !== null) {
            return self::$crawler;
        }
        $ua = strtolower(self::ua());
        $suffixes = null;
        foreach (self::CRAWLERS as $needle => $hosts) {
            if (str_contains($ua, $needle)) {
                $suffixes = $hosts;
                break;
            }
        }
        if ($suffixes === null) {
            return self::$crawler = false;
        }
        $ip = Security::clientIp();
        $row = Db::one('SELECT ok, ts FROM crawlers WHERE ip = ?', [$ip]);
        if ($row && $row['ts'] > time() - 86400) {
            return self::$crawler = (bool) $row['ok'];
        }
        $host = strtolower((string) @gethostbyaddr($ip));
        $ok = false;
        foreach ($suffixes as $s) {
            if ($host !== $ip && str_ends_with($host, $s)) {
                $ips = [];
                foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $r) {
                    $ips[] = $r['ip'] ?? $r['ipv6'] ?? '';
                }
                $ok = in_array($ip, $ips, true)
                    || (str_contains($ip, ':') && in_array(@inet_ntop((string) @inet_pton($ip)), array_map(static fn($x) => @inet_ntop((string) @inet_pton($x)), $ips), true));
                break;
            }
        }
        Db::run('INSERT OR REPLACE INTO crawlers(ip, ok, host, ts) VALUES(?,?,?,?)', [$ip, $ok ? 1 : 0, substr($host, 0, 120), time()]);
        return self::$crawler = $ok;
    }

    public static function banned(): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $until = Db::val('SELECT until FROM bans WHERE ip = ?', [Security::clientIp()]);
        return $until !== null && (int) $until > time();
    }

    /** Has this browser passed the browser check (on this network) recently? */
    public static function verified(): bool
    {
        $sid = self::sid(false);
        $c = (string) ($_COOKIE[self::PASS_COOKIE] ?? '');
        if ($sid === null || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $c, $m)) {
            return false;
        }
        $ttl = (int) Config::get('guard.pass_ttl', 6 * 3600);
        if ((int) $m[1] < time() - $ttl) {
            return false;
        }
        $expect = substr(Security::hmac('pass|' . $sid . '|' . $m[1] . '|' . self::netPrefix(Security::clientIp())), 0, 32);
        return hash_equals($expect, $m[2]);
    }

    /**
     * Gate for pages/APIs that hit TikTok data.
     * @return string ok | challenge | deny | quota
     */
    public static function check(): string
    {
        if (!self::enabled()) {
            return 'ok';
        }
        if (self::banned()) {
            return 'deny';
        }
        if (self::isCrawler()) {
            return RateLimiter::hit('crawler') ? 'ok' : 'quota';
        }
        if (self::badUa()) {
            self::block('bad_user_agent');
            return 'deny';
        }
        if (!self::verified()) {
            return 'challenge';
        }
        $sid = (string) self::sid(false);
        if (!RateLimiter::hit('session', $sid) || !RateLimiter::hit('session_day', $sid)) {
            self::block('session_quota');
            return 'quota';
        }
        return 'ok';
    }

    /** Record a security event; too many in a short time → automatic ban. */
    public static function block(string $reason): void
    {
        if (!self::enabled()) {
            return;
        }
        $ip = Security::clientIp();
        $path = substr((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), 0, 200);
        Db::run('INSERT INTO blocks(ts, ip, reason, path, ua) VALUES(?,?,?,?,?)', [time(), $ip, $reason, $path, substr(self::ua(), 0, 200)]);
        $limit = (int) Config::get('guard.auto_ban_after', 40);
        $n = (int) Db::val('SELECT COUNT(*) FROM blocks WHERE ip = ? AND ts > ?', [$ip, time() - 600]);
        if ($limit > 0 && $n >= $limit) {
            self::ban($ip, 'auto: ' . $reason, (int) Config::get('guard.auto_ban_hours', 24) * 3600);
        }
    }

    public static function ban(string $ip, string $reason, int $seconds): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            Db::run('INSERT OR REPLACE INTO bans(ip, reason, ts, until) VALUES(?,?,?,?)', [$ip, substr($reason, 0, 120), time(), time() + $seconds]);
        }
    }

    // ------------------------------------------------------------------ API token (bound to session)

    public static function apiToken(): string
    {
        $ts = (string) time();
        return $ts . '.' . substr(Security::hmac('api|' . $ts . '|' . (self::sid() ?? '')), 0, 32);
    }

    public static function verifyApiToken(?string $token): bool
    {
        $sid = self::sid(false);
        if ($sid === null || !is_string($token) || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $token, $m)) {
            return false;
        }
        $ts = (int) $m[1];
        if ($ts > time() + 300 || time() - $ts > 24 * 3600) {
            return false;
        }
        return hash_equals(substr(Security::hmac('api|' . $m[1] . '|' . $sid), 0, 32), $m[2]);
    }

    // ------------------------------------------------------------------ browser check (PoW + Turnstile)

    public static function powBits(): int
    {
        return max(10, min(24, (int) Config::get('guard.pow_bits', 17)));
    }

    public static function challenge(): string
    {
        $payload = (self::sid() ?? '') . '|' . (int) (microtime(true) * 1000) . '|' . bin2hex(random_bytes(8));
        $b = Security::b64($payload);
        return $b . '.' . substr(Security::hmac('pow|' . $b), 0, 24);
    }

    public static function turnstileKey(): string
    {
        $k = (string) Config::get('guard.turnstile_site_key', '');
        return preg_match('/^[A-Za-z0-9_-]{10,100}$/', $k) && Config::get('guard.turnstile_secret') ? $k : '';
    }

    private static function leadingZeroBits(string $raw): int
    {
        $bits = 0;
        foreach (str_split($raw) as $ch) {
            $b = ord($ch);
            if ($b === 0) {
                $bits += 8;
                continue;
            }
            for ($mask = 0x80; ($b & $mask) === 0; $mask >>= 1) {
                $bits++;
            }
            break;
        }
        return $bits;
    }

    /** POST /api/verify — called by the browser-check page. */
    public static function verifyEndpoint(): void
    {
        Security::baseHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $fail = static function (string $code, int $status = 403): void {
            http_response_code($status);
            echo json_encode(['ok' => false, 'error' => $code]);
        };
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Security::sameOrigin()
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'toklens'
            || !str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $fail('bad_request', 400);
            return;
        }
        if (self::banned()) {
            $fail('banned');
            return;
        }
        if (!RateLimiter::hit('verify')) {
            self::block('verify_flood');
            $fail('rate', 429);
            return;
        }
        $body = (string) file_get_contents('php://input', false, null, 0, 8192);
        $j = json_decode($body, true);
        $sid = self::sid(false);
        if (!is_array($j) || $sid === null) {
            $fail('no_session', 400);
            return;
        }
        $c = (string) ($j['c'] ?? '');
        $n = (string) ($j['n'] ?? '');
        if (!preg_match('/^([A-Za-z0-9_-]{20,200})\.([a-f0-9]{24})$/', $c, $m) || !preg_match('/^\d{1,12}$/', $n)
            || !hash_equals(substr(Security::hmac('pow|' . $m[1]), 0, 24), $m[2])) {
            self::block('verify_forged');
            $fail('invalid');
            return;
        }
        [$csid, $ms] = array_pad(explode('|', (string) Security::unb64($m[1])), 3, '');
        $age = (int) (microtime(true) * 1000) - (int) $ms;
        if ($csid !== $sid || $age > 600_000 || $age < 0) {
            $fail('expired');
            return;
        }
        if (self::leadingZeroBits(hash('sha256', $c . ':' . $n, true)) < self::powBits()) {
            self::block('verify_bad_pow');
            $fail('invalid');
            return;
        }
        if (!empty($j['wd']) || self::badUa()) {
            self::block('automation_detected');
            $fail('automation');
            return;
        }
        if (self::turnstileKey() !== '') {
            $tok = (string) ($j['ts'] ?? '');
            if ($tok === '' || strlen($tok) > 4096 || !self::turnstileOk($tok)) {
                self::block('turnstile_failed');
                $fail('turnstile');
                return;
            }
        }
        $ts = (string) time();
        $sig = substr(Security::hmac('pass|' . $sid . '|' . $ts . '|' . self::netPrefix(Security::clientIp())), 0, 32);
        self::cookie(self::PASS_COOKIE, $ts . '.' . $sig, (int) Config::get('guard.pass_ttl', 6 * 3600));
        Db::run('UPDATE sessions SET verified_ts = ? WHERE sid = ?', [time(), $sid]);
        echo json_encode(['ok' => true]);
    }

    private static function turnstileOk(string $token): bool
    {
        try {
            $r = Http::request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', ['challenges.cloudflare.com'], [
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'secret' => (string) Config::get('guard.turnstile_secret'),
                    'response' => $token,
                    'remoteip' => Security::clientIp(),
                ]),
                'timeout' => 8,
            ]);
            $j = json_decode($r['body'], true);
            return is_array($j) && !empty($j['success']);
        } catch (\Throwable $e) {
            error_log('[toklens] turnstile: ' . $e->getMessage());
            return false;
        }
    }

    // ------------------------------------------------------------------ analytics

    public static function device(string $ua): string
    {
        if ($ua === '') {
            return 'unknown';
        }
        if (preg_match('~bot|crawl|spider|slurp|facebookexternalhit|preview|curl|python|wget~i', $ua)) {
            return 'bot';
        }
        if (preg_match('~iPad|Tablet~i', $ua)) {
            return 'tablet';
        }
        return preg_match('~Mobi|Android|iPhone~i', $ua) ? 'mobile' : 'desktop';
    }

    public static function agent(string $ua): string
    {
        $os = match (true) {
            (bool) preg_match('~Android~i', $ua) => 'Android',
            (bool) preg_match('~iPhone|iPad|iOS~i', $ua) => 'iOS',
            (bool) preg_match('~Windows~i', $ua) => 'Windows',
            (bool) preg_match('~Mac OS X|Macintosh~i', $ua) => 'macOS',
            (bool) preg_match('~Linux~i', $ua) => 'Linux',
            default => '?',
        };
        $br = match (true) {
            (bool) preg_match('~Edg/~', $ua) => 'Edge',
            (bool) preg_match('~SamsungBrowser~', $ua) => 'Samsung',
            (bool) preg_match('~OPR/|Opera~', $ua) => 'Opera',
            (bool) preg_match('~Firefox/~', $ua) => 'Firefox',
            (bool) preg_match('~Chrome/~', $ua) => 'Chrome',
            (bool) preg_match('~Safari/~', $ua) => 'Safari',
            default => '?',
        };
        return $br . ' · ' . $os;
    }

    public static function country(string $ip): ?string
    {
        if (Config::get('trust_proxy')) {
            $cc = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
            if (preg_match('/^[A-Z]{2}$/', $cc) && $cc !== 'XX') {
                return $cc;
            }
        }
        $cc = Db::val('SELECT cc FROM geo WHERE ip = ?', [$ip]);
        return is_string($cc) && $cc !== '' ? $cc : null;
    }

    /** One row per HTML page view + session upsert. */
    public static function logVisit(int $status): void
    {
        if (!Config::get('guard.analytics', true) || Db::pdo() === null) {
            return;
        }
        $now = time();
        $sid = self::sid(false) ?? '';
        $ip = Security::clientIp();
        $ua = self::ua();
        $dev = self::isCrawler() ? 'crawler' : self::device($ua);
        $cc = self::country($ip);
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = substr((string) parse_url($uri, PHP_URL_PATH) . (($q = parse_url($uri, PHP_URL_QUERY)) ? '?' . $q : ''), 0, 250);
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = (string) parse_url($ref, PHP_URL_HOST);
        $myHost = (string) parse_url(base_url(), PHP_URL_HOST);
        $ref = ($refHost !== '' && $refHost !== $myHost) ? substr($refHost, 0, 120) : '';
        $lang = I18n::current();

        Db::run('INSERT INTO visits(ts, sid, ip, country, path, lang, status, device, ref) VALUES(?,?,?,?,?,?,?,?,?)',
            [$now, $sid, $ip, $cc, $path, $lang, $status, $dev, $ref]);
        if ($sid !== '') {
            Db::run('INSERT INTO sessions(sid, ip, country, ua, device, agent, lang, first_ts, last_ts, last_path, hits)
                      VALUES(?,?,?,?,?,?,?,?,?,?,1)
                      ON CONFLICT(sid) DO UPDATE SET ip = excluded.ip, last_ts = excluded.last_ts, last_path = excluded.last_path,
                        hits = sessions.hits + 1, lang = excluded.lang, country = COALESCE(excluded.country, sessions.country)',
                [$sid, $ip, $cc, substr($ua, 0, 300), $dev, self::agent($ua), $lang, $now, $now, $path]);
        }
        if (random_int(1, 300) === 1) {
            Db::purge();
        }
    }

    public static function logSearch(string $kind, string $query, bool $ok): void
    {
        if (!Config::get('guard.analytics', true)) {
            return;
        }
        $sid = self::sid(false) ?? '';
        $ip = Security::clientIp();
        Db::run('INSERT INTO searches(ts, sid, ip, country, kind, query, ok) VALUES(?,?,?,?,?,?,?)',
            [time(), $sid, $ip, self::country($ip), $kind, mb_substr($query, 0, 200), $ok ? 1 : 0]);
        if ($sid !== '') {
            Db::run('UPDATE sessions SET lookups = lookups + 1 WHERE sid = ?', [$sid]);
        }
    }
}
