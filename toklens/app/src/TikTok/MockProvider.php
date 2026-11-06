<?php

declare(strict_types=1);

namespace App\TikTok;

/** Fake data for local development & design work. Enable with 'mock' => true in config. */
final class MockProvider implements Provider
{
    public function profile(string $username): ?array
    {
        if ($username === 'notfound') {
            return null;
        }
        $seed = crc32($username);
        $id = (string) ((1580000000 + $seed % 150000000) << 32 | ($seed & 0xffff));
        return Service::normUser([
            'id' => $id,
            'uniqueId' => $username,
            'nickname' => ucfirst(str_replace(['.', '_'], ' ', $username)) . ' ✨',
            'signature' => "Creator • Travel & food 🌍\nNew video every Friday",
            'verified' => $seed % 2 === 0,
            'privateAccount' => $username === 'private.user',
            'secUid' => 'MS4wLjABAAAA' . substr(hash('sha256', $username), 0, 40),
            'region' => 'IQ',
            'language' => 'ar',
            'nickNameModifyTime' => time() - 86400 * 40,
            'bioLink' => ['link' => 'example.com/links'],
        ], [
            'followerCount' => 1_250_000 + $seed % 900000,
            'followingCount' => 312,
            'heartCount' => 48_900_000,
            'videoCount' => 214,
            'friendCount' => 87,
            'diggCount' => 1200,
        ], 'mock');
    }

    public function videos(string $username, string $cursor): array
    {
        $page = (int) $cursor;
        $items = [];
        for ($i = 0; $i < 12; $i++) {
            $n = $page * 12 + $i;
            $items[] = Service::normVideo([
                'video_id' => (string) ((1700000000 + $n * 86400) << 32),
                'title' => 'Sample video #' . ($n + 1) . ' — a day in Erbil 🇮🇶 #travel #food',
                'duration' => 15 + $n % 45,
                'play_count' => 120000 * ($n + 1),
                'digg_count' => 8000 * ($n + 1),
                'comment_count' => 300 + $n,
                'share_count' => 120 + $n,
                'create_time' => time() - 86400 * $n * 3,
                'is_top' => $n < 2,
                'region' => 'IQ',
            ], '');
        }
        return ['items' => $items, 'cursor' => (string) ($page + 1), 'hasMore' => $page < 2, 'region' => 'IQ'];
    }

    public function stories(string $username, string $userId): array
    {
        $r = $this->videos($username, '0');
        return ['items' => array_slice($r['items'], 0, 3), 'available' => true];
    }

    public function video(string $url): array
    {
        $v = $this->videos('demo', '0')['items'][0];
        $v['author'] = ['uniqueId' => 'demo.creator', 'nickname' => 'Demo Creator', 'avatar' => null];
        return $v;
    }
}
