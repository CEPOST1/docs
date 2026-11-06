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

    /**
     * Single video / photo post read from its public tiktok.com page.
     * TikTok's CDN only serves the file to the session that loaded the page, so the
     * cookies we receive are kept with the item and replayed by our signed media proxy.
     */
    public function video(string $url): array
    {
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ];
        $url = (string) preg_replace('~^http://~i', 'https://', trim($url));
        $r = Http::get($url, Security::API_HOSTS, ['headers' => $headers, 'timeout' => 10, 'max_bytes' => 6_000_000]);
        $item = self::extractItem($r['body']);

        // Short links / mobile pages: retry on the canonical www page using the numeric ID.
        if ($item === false && preg_match('~/(?:video|photo|v)/(\d{8,20})~', $r['url'] . ' ' . $url, $m)) {
            $r = Http::get('https://www.tiktok.com/@i/video/' . $m[1], Security::API_HOSTS, ['headers' => $headers, 'timeout' => 10, 'max_bytes' => 6_000_000]);
            $item = self::extractItem($r['body']);
        }
        if ($item === null) {
            throw new NotFound();
        }
        if ($item === false) {
            throw new UpstreamError('video-detail missing in tiktok.com page');
        }
        $v = self::normItem($item);
        $v['cookie'] = Http::cookieHeader($r['cookies'] ?? []);
        return $v;
    }

    /** @return array|null|false item array, null = not found, false = page without data */
    private static function extractItem(string $html): array|null|false
    {
        if (!preg_match('~<script[^>]+id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)</script>~s', $html, $m)) {
            return false;
        }
        $j = json_decode($m[1], true);
        $d = is_array($j) ? ($j['__DEFAULT_SCOPE__']['webapp.video-detail'] ?? null) : null;
        if (!is_array($d)) {
            return false;
        }
        $it = $d['itemInfo']['itemStruct'] ?? null;
        if (is_array($it) && !empty($it['id'])) {
            return $it;
        }
        return ((int) ($d['statusCode'] ?? 0)) !== 0 ? null : false;
    }

    /** Convert a tiktok.com itemStruct into our normalised video array. */
    public static function normItem(array $it): array
    {
        $video = (array) ($it['video'] ?? []);
        $stats = (array) ($it['statsV2'] ?? $it['stats'] ?? []);
        $author = (array) ($it['author'] ?? []);
        $music = (array) ($it['music'] ?? []);
        $str = static fn($x) => is_string($x) && str_starts_with($x, 'https://') ? $x : null;

        // Highest-bitrate rendition = "HD".
        $hd = null;
        $best = -1;
        foreach ((array) ($video['bitrateInfo'] ?? []) as $b) {
            $u = $str($b['PlayAddr']['UrlList'][0] ?? null);
            if ($u && (int) ($b['Bitrate'] ?? 0) > $best) {
                $best = (int) $b['Bitrate'];
                $hd = $u;
            }
        }
        $play = $str($video['playAddr'] ?? null);
        $images = [];
        foreach ((array) ($it['imagePost']['images'] ?? []) as $img) {
            $u = $str($img['imageURL']['urlList'][0] ?? null);
            if ($u) {
                $images[] = $u;
            }
        }
        return [
            'id' => (string) $it['id'],
            'desc' => (string) ($it['desc'] ?? ''),
            'cover' => $str($video['cover'] ?? null) ?? $str($video['originCover'] ?? null),
            'duration' => (int) ($video['duration'] ?? 0),
            'play' => $play,
            'hdplay' => $hd !== $play ? $hd : null,
            'wmplay' => $str($video['downloadAddr'] ?? null),
            'music' => $str($music['playUrl'] ?? null),
            'musicTitle' => (string) ($music['title'] ?? ''),
            'plays' => Service::int($stats['playCount'] ?? null),
            'likes' => Service::int($stats['diggCount'] ?? null),
            'comments' => Service::int($stats['commentCount'] ?? null),
            'shares' => Service::int($stats['shareCount'] ?? null),
            'created' => (int) ($it['createTime'] ?? 0) ?: Service::idToTime((string) $it['id']),
            'pinned' => (bool) ($it['isPinnedItem'] ?? false),
            'region' => strtoupper((string) ($it['locationCreated'] ?? '')),
            'images' => $images,
            'author' => [
                'uniqueId' => (string) ($author['uniqueId'] ?? ''),
                'nickname' => (string) ($author['nickname'] ?? ''),
                'avatar' => $str($author['avatarMedium'] ?? null) ?? $str($author['avatarThumb'] ?? null),
            ],
        ];
    }
}
