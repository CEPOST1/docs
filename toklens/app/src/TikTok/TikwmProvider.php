<?php

declare(strict_types=1);

namespace App\TikTok;

use App\Config;
use App\Http;
use App\Security;

/**
 * tikwm-format API. Two modes:
 *  - free public tikwm.com (~1 request/second, may block some hosting IPs with Cloudflare)
 *  - RapidAPI (set providers.rapidapi_key) — same JSON format, works from any server
 */
final class TikwmProvider implements Provider
{
    private string $base;
    private string $prefix;
    private array $headers = ['Accept: application/json'];
    private bool $rapid;

    public function __construct()
    {
        $key = trim((string) Config::get('providers.rapidapi_key', ''));
        $this->rapid = $key !== '' && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $key) === 1;
        if ($this->rapid) {
            $host = (string) Config::get('providers.rapidapi_host', 'tiktok-scraper7.p.rapidapi.com');
            $host = preg_match('/^[a-z0-9.-]+\.rapidapi\.com$/', $host) ? $host : 'tiktok-scraper7.p.rapidapi.com';
            $this->base = 'https://' . $host;
            $this->prefix = '';
            $this->headers[] = 'X-RapidAPI-Key: ' . $key;
            $this->headers[] = 'X-RapidAPI-Host: ' . $host;
        } else {
            $this->base = rtrim((string) Config::get('providers.tikwm_base', 'https://www.tikwm.com'), '/');
            $this->prefix = '/api';
        }
    }

    private function throttle(): void
    {
        if ($this->rapid) {
            return;
        }
        $fp = @fopen(ROOT . '/storage/tikwm.lock', 'c+');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_EX);
        $last = (float) stream_get_contents($fp);
        $wait = 1.05 - (microtime(true) - $last);
        if ($wait > 5) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new UpstreamError('tikwm busy');
        }
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) microtime(true));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function call(string $endpoint, array $params, string $method = 'GET'): array
    {
        $url = str_starts_with($endpoint, 'https://') ? $endpoint : $this->base . $endpoint;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->throttle();
            $opt = ['headers' => $this->headers, 'timeout' => 10, 'max_bytes' => 3_000_000];
            if ($method === 'POST') {
                $opt['body'] = http_build_query($params);
                $opt['headers'][] = 'Content-Type: application/x-www-form-urlencoded';
                $r = Http::request('POST', $url, Security::API_HOSTS, $opt);
            } else {
                $r = Http::get($url . '?' . http_build_query($params), Security::API_HOSTS, $opt);
            }
            if ($r['status'] !== 200) {
                $hint = str_contains($r['body'], 'Just a moment') ? ' (Cloudflare blocks this server — use a RapidAPI key)' : '';
                throw new UpstreamError(($this->rapid ? 'rapidapi' : 'tikwm') . ' HTTP ' . $r['status'] . $hint);
            }
            $j = json_decode($r['body'], true);
            if (!is_array($j)) {
                throw new UpstreamError('tikwm invalid JSON');
            }
            $msg = strtolower((string) ($j['msg'] ?? ''));
            if (($j['code'] ?? 0) !== 0 && str_contains($msg, 'limit') && $attempt === 0) {
                usleep(1_200_000);
                continue;
            }
            return $j;
        }
        throw new UpstreamError('tikwm rate limited');
    }

    private static function isNotFound(array $j): bool
    {
        $msg = strtolower((string) ($j['msg'] ?? ''));
        foreach (['not exist', "doesn't exist", 'not found', 'unavailable', 'invalid', 'url parsing'] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }
        return false;
    }

    public function profile(string $username): ?array
    {
        $j = $this->call($this->prefix . '/user/info', ['unique_id' => $username]);
        if (($j['code'] ?? -1) !== 0) {
            if (self::isNotFound($j)) {
                return null;
            }
            throw new UpstreamError('tikwm: ' . ($j['msg'] ?? 'error'));
        }
        $u = $j['data']['user'] ?? null;
        if (!is_array($u) || empty($u['id'])) {
            return null;
        }
        return Service::normUser($u, (array) ($j['data']['stats'] ?? []), 'tikwm');
    }

    public function videos(string $username, string $cursor): array
    {
        $j = $this->call($this->prefix . '/user/posts', ['unique_id' => $username, 'count' => 12, 'cursor' => $cursor]);
        if (($j['code'] ?? -1) !== 0) {
            if (self::isNotFound($j)) {
                return ['items' => [], 'cursor' => '0', 'hasMore' => false, 'region' => ''];
            }
            throw new UpstreamError('tikwm: ' . ($j['msg'] ?? 'error'));
        }
        $items = [];
        foreach ((array) ($j['data']['videos'] ?? []) as $v) {
            if (is_array($v)) {
                $items[] = Service::normVideo($v, $this->base);
            }
        }
        return [
            'items' => $items,
            'cursor' => (string) ($j['data']['cursor'] ?? '0'),
            'hasMore' => (bool) ($j['data']['hasMore'] ?? false),
            'region' => $items[0]['region'] ?? '',
        ];
    }

    public function stories(string $username, string $userId): array
    {
        $endpoint = $this->rapid ? $this->prefix . '/user/story'
            : (string) Config::get('providers.stories_endpoint', $this->base . '/api/user/story');
        $params = ['unique_id' => $username];
        if ($userId !== '') {
            $params['user_id'] = $userId;
        }
        $j = $this->call($endpoint, $params);
        if (($j['code'] ?? -1) !== 0) {
            // A 'not found' style answer simply means there are no active stories.
            return ['items' => [], 'available' => self::isNotFound($j)];
        }
        $data = $j['data'] ?? [];
        $list = $data['videos'] ?? $data['stories'] ?? $data['list'] ?? $data['itemList'] ?? (array_is_list((array) $data) ? $data : []);
        $items = [];
        foreach ((array) $list as $v) {
            if (is_array($v)) {
                $items[] = Service::normVideo($v, $this->base);
            }
        }
        return ['items' => $items, 'available' => true];
    }

    public function video(string $url): array
    {
        $j = $this->call($this->prefix . '/', ['url' => $url, 'hd' => 1]);
        if (($j['code'] ?? -1) !== 0 || !is_array($j['data'] ?? null)) {
            if (self::isNotFound($j)) {
                throw new NotFound();
            }
            throw new UpstreamError('tikwm: ' . ($j['msg'] ?? 'error'));
        }
        return Service::normVideo($j['data'], $this->base);
    }
}
