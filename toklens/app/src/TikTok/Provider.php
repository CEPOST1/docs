<?php

declare(strict_types=1);

namespace App\TikTok;

interface Provider
{
    /** @return array|null normalised profile, or null when the account does not exist */
    public function profile(string $username): ?array;

    /** @return array{items:array, cursor:string, hasMore:bool, region:string} */
    public function videos(string $username, string $cursor): array;

    /** @return array{items:array, available:bool} */
    public function stories(string $username, string $userId): array;

    /** Single video from a TikTok URL. */
    public function video(string $url): array;
}
