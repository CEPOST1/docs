<?php

declare(strict_types=1);

namespace App\TikTok;

use App\Cache;
use App\Config;
use App\HttpException;

/**
 * Orchestrates data providers, caching and normalisation.
 * Every method returns plain arrays with RAW upstream URLs; App::present*() signs them for the browser.
 */
final class Service
{
    /** @var array<string, Provider> */
    private array $providers = [];

    private function provider(string $name): Provider
    {
        if (Config::get('mock')) {
            $name = 'mock';
        }
        return $this->providers[$name] ??= match ($name) {
            'web' => new WebProvider(),
            'tikwm' => new TikwmProvider(),
            'mock' => new MockProvider(),
            default => throw new \InvalidArgumentException('Unknown provider ' . $name),
        };
    }

    private function ttl(string $k, int $d): int
    {
        return (int) Config::get('cache_ttl.' . $k, $d);
    }

    /** @throws NotFound|UpstreamError */
    public function profile(string $username): array
    {
        $key = 'profile:' . $username;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            if (!empty($cached['nf'])) {
                throw new NotFound();
            }
            return $cached;
        }

        $notFound = false;
        $names = Config::get('mock') ? ['mock'] : (array) Config::get('providers.profile', ['web', 'tikwm']);
        // Circuit breaker: a provider that just failed goes to the end of the queue for 5 minutes and is
        // only tried if no healthy provider answered — visitors don't wait on a known-bad source.
        $healthy = array_values(array_filter($names, static fn($n) => !Cache::get('cb:' . $n)));
        $broken = array_values(array_diff($names, $healthy));
        $attempted = 0;
        foreach (array_merge($healthy, $broken) as $name) {
            if (in_array($name, $broken, true) && $attempted > 0) {
                break;
            }
            $attempted++;
            try {
                $p = $this->provider((string) $name)->profile($username);
            } catch (UpstreamError | HttpException $e) {
                error_log('[toklens] profile provider ' . $name . ' failed: ' . $e->getMessage());
                Cache::set('cb:' . $name, 1, 300);
                continue;
            }
            if ($p === null) {
                $notFound = true;
                continue;
            }
            $p['created'] = $p['createTime'] > 0 ? $p['createTime'] : self::idToTime($p['id']);
            $p['createdFromId'] = $p['createTime'] <= 0 && $p['created'] !== null;
            Cache::set($key, $p, $this->ttl('profile', 600));
            return $p;
        }
        if ($notFound) {
            Cache::set($key, ['nf' => true], $this->ttl('not_found', 120));
            throw new NotFound();
        }
        throw new UpstreamError('All profile providers failed');
    }

    /** Healthy providers first; providers that failed recently (circuit open) last. */
    private function ordered(array $names): array
    {
        $healthy = array_values(array_filter($names, static fn($n) => !Cache::get('cb:' . $n)));
        return array_merge($healthy, array_values(array_diff($names, $healthy)));
    }

    /** @return array{items:array, cursor:string, hasMore:bool} */
    public function videos(string $username, string $cursor = '0'): array
    {
        $key = 'videos:' . $username . ':' . $cursor;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $r = $this->provider('tikwm')->videos($username, $cursor);
        Cache::set($key, $r, $this->ttl('videos', 300));
        return $r;
    }

    public function stories(string $username, string $userId = ''): array
    {
        $key = 'stories:' . $username;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $r = $this->provider('tikwm')->stories($username, $userId);
        Cache::set($key, $r, $this->ttl('stories', 180));
        return $r;
    }

    /** @throws NotFound|UpstreamError */
    public function video(string $url): array
    {
        $key = 'video:' . $url;
        $cached = Cache::get($key);
        if (is_array($cached)) {
            if (!empty($cached['nf'])) {
                throw new NotFound();
            }
            return $cached;
        }
        $names = Config::get('mock') ? ['mock'] : (array) Config::get('providers.video', ['web', 'tikwm']);
        $notFound = false;
        foreach ($this->ordered($names) as $name) {
            try {
                $r = $this->provider((string) $name)->video($url);
            } catch (NotFound) {
                $notFound = true;
                continue;
            } catch (UpstreamError | HttpException $e) {
                error_log('[toklens] video provider ' . $name . ' failed: ' . $e->getMessage());
                Cache::set('cb:' . $name, 1, 300);
                continue;
            }
            Cache::set($key, $r, $this->ttl('video', 900));
            return $r;
        }
        if ($notFound) {
            Cache::set($key, ['nf' => true], $this->ttl('not_found', 120));
            throw new NotFound();
        }
        throw new UpstreamError('All video providers failed');
    }

    /**
     * TikTok user and video IDs are "snowflake" style: the top 32 bits are the Unix creation time.
     * Very old accounts (musical.ly era short IDs) do not carry a timestamp → null.
     */
    public static function idToTime(string|int|null $id): ?int
    {
        $id = (string) $id;
        if (!preg_match('/^\d{15,19}$/', $id) || PHP_INT_SIZE < 8) {
            return null;
        }
        if (strlen($id) === 19 && strcmp($id, '9223372036854775807') > 0) {
            return null;
        }
        $ts = ((int) $id) >> 32;
        if ($ts < 1388534400 || $ts > time() + 86400) { // before 2014 or in the future
            return null;
        }
        return $ts;
    }

    // ------------------------------------------------------------------ normalisers

    public static function int(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v)) {
            return (int) $v;
        }
        if (is_string($v) && preg_match('/^\d{1,19}$/', $v)) {
            return (int) $v;
        }
        return null;
    }

    public static function normUser(array $u, array $s, string $source): array
    {
        $link = $u['bioLink']['link'] ?? '';
        return [
            'id' => (string) ($u['id'] ?? $u['uid'] ?? ''),
            'uniqueId' => (string) ($u['uniqueId'] ?? $u['unique_id'] ?? ''),
            'nickname' => (string) ($u['nickname'] ?? ''),
            'avatar' => (string) ($u['avatarLarger'] ?? $u['avatarMedium'] ?? $u['avatarThumb'] ?? $u['avatar'] ?? ''),
            'signature' => (string) ($u['signature'] ?? ''),
            'verified' => (bool) ($u['verified'] ?? false),
            'private' => (bool) ($u['privateAccount'] ?? $u['secret'] ?? false),
            'secUid' => (string) ($u['secUid'] ?? $u['sec_uid'] ?? ''),
            'region' => strtoupper((string) ($u['region'] ?? '')),
            'language' => (string) ($u['language'] ?? ''),
            'createTime' => (int) ($u['createTime'] ?? 0),
            'nickModified' => (int) ($u['nickNameModifyTime'] ?? 0),
            'uniqueModified' => (int) ($u['uniqueIdModifyTime'] ?? 0),
            'link' => is_string($link) ? $link : '',
            'followers' => self::int($s['followerCount'] ?? null),
            'following' => self::int($s['followingCount'] ?? null),
            'likes' => self::int($s['heartCount'] ?? $s['heart'] ?? null),
            'videos' => self::int($s['videoCount'] ?? null),
            'friends' => self::int($s['friendCount'] ?? null),
            'diggs' => self::int($s['diggCount'] ?? null),
            'source' => $source,
        ];
    }

    /** Normalise a tikwm-style video object. */
    public static function normVideo(array $v, string $base): array
    {
        $abs = static function ($x) use ($base): ?string {
            if (!is_string($x) || $x === '') {
                return null;
            }
            return str_starts_with($x, '/') ? $base . $x : $x;
        };
        $images = [];
        foreach ((array) ($v['images'] ?? []) as $img) {
            $u = $abs(is_array($img) ? ($img['url'] ?? null) : $img);
            if ($u) {
                $images[] = $u;
            }
        }
        $author = (array) ($v['author'] ?? []);
        return [
            'id' => (string) ($v['video_id'] ?? $v['id'] ?? $v['aweme_id'] ?? ''),
            'desc' => (string) ($v['title'] ?? $v['desc'] ?? ''),
            'cover' => $abs($v['cover'] ?? $v['origin_cover'] ?? null),
            'duration' => (int) ($v['duration'] ?? 0),
            'play' => $abs($v['play'] ?? null),
            'hdplay' => $abs($v['hdplay'] ?? null),
            'wmplay' => $abs($v['wmplay'] ?? null),
            'music' => $abs($v['music'] ?? ($v['music_info']['play'] ?? null)),
            'musicTitle' => (string) ($v['music_info']['title'] ?? ''),
            'plays' => self::int($v['play_count'] ?? null),
            'likes' => self::int($v['digg_count'] ?? null),
            'comments' => self::int($v['comment_count'] ?? null),
            'shares' => self::int($v['share_count'] ?? null),
            'created' => (int) ($v['create_time'] ?? 0) ?: self::idToTime((string) ($v['video_id'] ?? $v['id'] ?? '')),
            'pinned' => (bool) ($v['is_top'] ?? false),
            'region' => strtoupper((string) ($v['region'] ?? '')),
            'images' => $images,
            'author' => [
                'uniqueId' => (string) ($author['unique_id'] ?? $author['uniqueId'] ?? ''),
                'nickname' => (string) ($author['nickname'] ?? ''),
                'avatar' => $abs($author['avatar'] ?? null),
            ],
        ];
    }
}
