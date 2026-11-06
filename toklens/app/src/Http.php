<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal hardened HTTP client.
 *  - HTTPS only, certificate verification on
 *  - every hop (including redirects) must match a host allow-list (SSRF protection)
 *  - response size and time limits
 */
final class Http
{
    public const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    public static function hostAllowed(string $url, array $suffixes): bool
    {
        $p = parse_url($url);
        if (!is_array($p) || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || empty($p['host'])) {
            return false;
        }
        if (isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
            return false;
        }
        $host = strtolower(rtrim((string) $p['host'], '.'));
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach ($suffixes as $s) {
            if ($host === $s || str_ends_with($host, '.' . $s)) {
                return true;
            }
        }
        return false;
    }

    /** @return \CurlHandle */
    private static function handle(string $url, array $headers, int $timeout, bool $decode)
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => true,
        ];
        if ($decode) {
            $opts[CURLOPT_ENCODING] = '';
        }
        $proxy = (string) Config::get('outbound_proxy', '');
        if ($proxy !== '') {
            $opts[CURLOPT_PROXY] = $proxy;
        }
        curl_setopt_array($ch, $opts);
        return $ch;
    }

    public static function resolve(string $base, string $loc): string
    {
        if (preg_match('~^https?://~i', $loc)) {
            return $loc;
        }
        $p = parse_url($base);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        if (str_starts_with($loc, '//')) {
            return ($p['scheme'] ?? 'https') . ':' . $loc;
        }
        if (str_starts_with($loc, '/')) {
            return $origin . $loc;
        }
        $dir = rtrim(dirname($p['path'] ?? '/'), '/');
        return $origin . $dir . '/' . $loc;
    }

    /**
     * @return array{status:int, headers:array<string,string>, body:string, url:string}
     * @throws HttpException
     */
    public static function request(string $method, string $url, array $allowedHosts, array $opt = []): array
    {
        $headers = $opt['headers'] ?? [];
        $timeout = (int) ($opt['timeout'] ?? 12);
        $max = (int) ($opt['max_bytes'] ?? 4_000_000);
        $body = $opt['body'] ?? null;

        for ($hop = 0; $hop <= 5; $hop++) {
            if (!self::hostAllowed($url, $allowedHosts)) {
                throw new HttpException('Host not allowed: ' . (parse_url($url, PHP_URL_HOST) ?: '?'));
            }
            $ch = self::handle($url, $headers, $timeout, true);
            $buf = '';
            $rh = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($c, string $line) use (&$rh): int {
                $len = strlen($line);
                $line = trim($line);
                if (str_starts_with($line, 'HTTP/')) {
                    $rh = [];
                } elseif (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $rh[strtolower(trim($k))] = trim($v);
                }
                return $len;
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($c, string $chunk) use (&$buf, $max): int {
                $buf .= $chunk;
                return strlen($buf) > $max ? 0 : strlen($chunk);
            });
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
            }
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($ok === false) {
                throw new HttpException('Transport error: ' . $err);
            }
            if ($status >= 300 && $status < 400 && isset($rh['location'])) {
                $url = self::resolve($url, $rh['location']);
                if ($method === 'POST' && $status !== 307 && $status !== 308) {
                    $method = 'GET';
                    $body = null;
                }
                continue;
            }
            return ['status' => $status, 'headers' => $rh, 'body' => $buf, 'url' => $url];
        }
        throw new HttpException('Too many redirects');
    }

    public static function get(string $url, array $allowedHosts, array $opt = []): array
    {
        return self::request('GET', $url, $allowedHosts, $opt);
    }

    /**
     * Stream a remote video/audio file to the client without buffering it in memory.
     * Supports Range requests (seeking) and enforces content-type and size limits.
     */
    public static function stream(string $url, array $allowedHosts, ?string $attachment, string $fallbackType, int $maxBytes): void
    {
        $reqHeaders = ['Referer: https://www.tiktok.com/', 'Accept: */*'];
        $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
        if ($range !== '' && preg_match('/^bytes=\d{0,15}-\d{0,15}$/', $range)) {
            $reqHeaders[] = 'Range: ' . $range;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @set_time_limit(0);

        for ($hop = 0; $hop <= 6; $hop++) {
            if (!self::hostAllowed($url, $allowedHosts)) {
                http_response_code(403);
                return;
            }
            $ch = self::handle($url, $reqHeaders, 0, false);
            curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 512);
            curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 30);
            $st = ['status' => 0, 'h' => [], 'started' => false, 'bytes' => 0, 'blocked' => false];

            curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($c, string $line) use (&$st, $attachment, $fallbackType): int {
                $len = strlen($line);
                $t = trim($line);
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $t, $m)) {
                    $st['status'] = (int) $m[1];
                    $st['h'] = [];
                    return $len;
                }
                if ($t !== '') {
                    if (str_contains($t, ':')) {
                        [$k, $v] = explode(':', $t, 2);
                        $st['h'][strtolower(trim($k))] = trim($v);
                    }
                    return $len;
                }
                // End of headers.
                if ($st['status'] !== 200 && $st['status'] !== 206) {
                    return $len;
                }
                $type = strtolower(trim(explode(';', $st['h']['content-type'] ?? '')[0]));
                $okType = str_starts_with($type, 'video/') || str_starts_with($type, 'audio/');
                if (!$okType && !in_array($type, ['application/octet-stream', 'binary/octet-stream', ''], true)) {
                    $st['blocked'] = true;
                    return -1;
                }
                http_response_code($st['status']);
                header('Content-Type: ' . ($okType ? $type : $fallbackType));
                foreach (['content-length' => 'Content-Length', 'content-range' => 'Content-Range'] as $k => $name) {
                    if (isset($st['h'][$k]) && preg_match('~^[\w */-]+$~', $st['h'][$k])) {
                        header($name . ': ' . $st['h'][$k]);
                    }
                }
                header('Accept-Ranges: bytes');
                header('Cache-Control: private, max-age=3600');
                header("Content-Security-Policy: default-src 'none'; sandbox");
                header('Cross-Origin-Resource-Policy: same-origin');
                if ($attachment !== null && $attachment !== '') {
                    header('Content-Disposition: attachment; filename="' . $attachment . '"');
                }
                $st['started'] = true;
                return $len;
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($c, string $chunk) use (&$st, $maxBytes): int {
                if (!$st['started']) {
                    return strlen($chunk);
                }
                $st['bytes'] += strlen($chunk);
                if ($st['bytes'] > $maxBytes || connection_aborted()) {
                    return 0;
                }
                echo $chunk;
                flush();
                return strlen($chunk);
            });
            curl_exec($ch);
            curl_close($ch);

            if ($st['blocked']) {
                http_response_code(415);
                return;
            }
            if ($st['started']) {
                return;
            }
            if ($st['status'] >= 300 && $st['status'] < 400 && isset($st['h']['location'])) {
                $url = self::resolve($url, $st['h']['location']);
                continue;
            }
            http_response_code(502);
            return;
        }
        http_response_code(508);
    }
}
