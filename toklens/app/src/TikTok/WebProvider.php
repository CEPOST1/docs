<?php

declare(strict_types=1);

namespace App\TikTok;

use App\Http;
use App\Security;

/**
 * Reads the public profile page on tiktok.com and extracts the embedded JSON state.
 * Gives the richest profile data (incl. nickname change time) without any API key.
 */
final class WebProvider implements Provider
{
    public function profile(string $username): ?array
    {
        $r = Http::get('https://www.tiktok.com/@' . rawurlencode($username) . '?lang=en', Security::API_HOSTS, [
            'headers' => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
            ],
            'timeout' => 8,
            'max_bytes' => 6_000_000,
        ]);
        if ($r['status'] === 404) {
            return null;
        }
        if ($r['status'] !== 200) {
            throw new UpstreamError('tiktok.com HTTP ' . $r['status']);
        }
        $html = $r['body'];

        // Current layout.
        if (preg_match('~<script[^>]+id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)</script>~s', $html, $m)) {
            $j = json_decode($m[1], true);
            $detail = is_array($j) ? ($j['__DEFAULT_SCOPE__']['webapp.user-detail'] ?? null) : null;
            if (!is_array($detail)) {
                throw new UpstreamError('user-detail missing (captcha/login wall?)');
            }
            $info = $detail['userInfo'] ?? [];
            if (!empty($info['user']['id'])) {
                $stats = $info['statsV2'] ?? $info['stats'] ?? [];
                return Service::normUser($info['user'], is_array($stats) ? $stats : [], 'web');
            }
            $code = (int) ($detail['statusCode'] ?? 0);
            if (in_array($code, [10202, 10221, 10222, 10223], true)) {
                return null;
            }
            throw new UpstreamError('tiktok.com statusCode ' . $code);
        }

        // Older layout.
        if (preg_match('~<script[^>]+id="SIGI_STATE"[^>]*>(.*?)</script>~s', $html, $m)) {
            $j = json_decode($m[1], true);
            $users = $j['UserModule']['users'] ?? [];
            $stats = $j['UserModule']['stats'] ?? [];
            foreach ($users as $k => $u) {
                if (strcasecmp((string) $k, $username) === 0 && is_array($u)) {
                    return Service::normUser($u, (array) ($stats[$k] ?? []), 'web');
                }
            }
            return null;
        }
        throw new UpstreamError('No embedded state in tiktok.com response');
    }

    public function videos(string $username, string $cursor): array
    {
        throw new UpstreamError('not supported');
    }

    public function stories(string $username, string $userId): array
    {
        throw new UpstreamError('not supported');
    }

    public function video(string $url): array
    {
        throw new UpstreamError('not supported');
    }
}
