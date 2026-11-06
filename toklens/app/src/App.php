<?php

declare(strict_types=1);

namespace App;

use App\TikTok\NotFound;
use App\TikTok\Service;
use App\TikTok\UpstreamError;

final class App
{
    private static ?Service $svc = null;

    private static function svc(): Service
    {
        return self::$svc ??= new Service();
    }

    // ================================================================== routing

    public static function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            header('Allow: GET, HEAD');
            self::plain(405, 'Method Not Allowed');
            return;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if (strlen($uri) > 2048) {
            self::plain(414, 'URI Too Long');
            return;
        }
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? (preg_replace('~/{2,}~', '/', $path) ?? '/') : '/';

        switch ($path) {
            case '/':
                self::redirect(url('/', I18n::detect()), 302);
                return;
            case '/robots.txt':
                self::robots();
                return;
            case '/sitemap.xml':
                self::sitemap();
                return;
            case '/api/videos':
                self::apiVideos();
                return;
            case '/api/stories':
                self::apiStories();
                return;
            case '/media':
                self::media();
                return;
            case '/stream':
                self::stream();
                return;
            case '/diag':
                self::diag();
                return;
        }

        if (!preg_match('~^/([a-z]{2,3})(/.*)?$~', $path, $m) || !I18n::supported($m[1])) {
            I18n::set(I18n::detect());
            self::notFound();
            return;
        }
        $lang = $m[1];
        $sub = $m[2] ?? '';
        I18n::set($lang);
        I18n::rememberCookie($lang);

        if ($sub === '') {
            self::redirect(url('/'), 301);
            return;
        }
        if ($sub !== '/' && str_ends_with($sub, '/')) {
            self::redirect(url(rtrim($sub, '/')), 301);
            return;
        }
        if (preg_match('~^/user/([^/]{1,100})$~', $sub, $mm)) {
            self::user($mm[1]);
            return;
        }
        if (preg_match('~^/@([^/]{1,100})$~', $sub, $mm)) {
            $u = Security::normalizeUsername(rawurldecode($mm[1]));
            $u ? self::redirect(url('/user/' . $u), 301) : self::notFound();
            return;
        }
        if (preg_match('~^/articles/([a-z0-9-]{1,100})$~', $sub, $mm)) {
            self::article($mm[1]);
            return;
        }
        match ($sub) {
            '/' => self::home(),
            '/lookup' => self::lookup(),
            '/video-downloader' => self::downloader(),
            '/story-viewer' => self::stories(),
            '/account-age' => self::age(),
            '/articles' => self::articles(),
            '/about', '/privacy', '/terms' => self::staticPage(ltrim($sub, '/')),
            default => self::notFound(),
        };
    }

    // ================================================================== pages

    private static function home(?string $error = null, string $input = ''): void
    {
        $faq = ta('faq_home');
        $seo = Seo::page(t('home_title'), t('home_desc'), '/', [
            'robots' => $error ? 'noindex,follow' : null,
            'jsonld' => [
                Seo::organization(),
                Seo::website(),
                Seo::webApp(t('nav_profile'), '/', t('home_desc')),
                Seo::faq($faq),
            ],
        ]);
        if ($error) {
            $seo['robots'] = 'noindex,follow';
        }
        View::render('home', compact('seo', 'faq', 'error', 'input'), $error ? 422 : 200);
    }

    private static function lookup(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (Security::isTikTokUrl($q) && preg_match('~/(video|photo)/\d+|//(vm|vt)\.tiktok\.com/~i', $q)) {
            self::redirect(url('/video-downloader') . '?url=' . rawurlencode($q), 302);
            return;
        }
        $u = Security::normalizeUsername($q);
        if ($u === null) {
            self::home(t('err_invalid'), mb_substr($q, 0, 100));
            return;
        }
        self::redirect(url('/user/' . $u), 302);
    }

    private static function user(string $raw): void
    {
        $decoded = rawurldecode($raw);
        $u = Security::normalizeUsername($decoded);
        if ($u === null) {
            self::notFound();
            return;
        }
        if ($u !== $decoded) {
            self::redirect(url('/user/' . $u), 301);
            return;
        }
        if (!RateLimiter::hit('page')) {
            self::error(429, t('err_rate_t'), t('err_rate'));
            return;
        }
        try {
            $p = self::presentProfile(self::svc()->profile($u));
        } catch (NotFound) {
            self::error(404, t('err_not_found_t'), t('err_not_found_d', ['user' => $u]), $u);
            return;
        } catch (UpstreamError) {
            header('Retry-After: 30');
            self::error(503, t('err_upstream_t'), t('err_upstream_d'), $u);
            return;
        }

        $vars = [
            'name' => $p['nickname'] !== '' ? $p['nickname'] : '@' . $p['uniqueId'],
            'user' => $p['uniqueId'],
            'followers' => fmt_compact($p['followers']),
            'likes' => fmt_compact($p['likes']),
            'videos' => fmt_num($p['videos']),
        ];
        $index = Config::get('index_profiles') && !$p['private'];
        $person = array_filter([
            '@type' => 'Person',
            'name' => $p['nickname'] ?: $p['uniqueId'],
            'alternateName' => '@' . $p['uniqueId'],
            'identifier' => $p['id'],
            'description' => $p['signature'] ?: null,
            'url' => $p['tiktokUrl'],
            'sameAs' => [$p['tiktokUrl']],
            'interactionStatistic' => [
                ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/FollowAction', 'userInteractionCount' => $p['followers'] ?? 0],
                ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/LikeAction', 'userInteractionCount' => $p['likes'] ?? 0],
            ],
        ]);
        $seo = Seo::page(t('profile_title', $vars), t('profile_desc', $vars), '/user/' . $u, [
            'type' => 'profile',
            'robots' => $index ? null : 'noindex,follow',
            'jsonld' => [
                ['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'mainEntity' => $person, 'dateModified' => gmdate('c')],
                Seo::breadcrumbs([[t('breadcrumb_home'), '/'], ['@' . $u, '/user/' . $u]]),
            ],
        ]);
        View::render('user', ['seo' => $seo, 'p' => $p, 'apiToken' => Security::apiToken()]);
    }

    private static function downloader(): void
    {
        $input = trim((string) ($_GET['url'] ?? ''));
        $result = null;
        $error = null;
        if ($input !== '') {
            if (!Security::isTikTokUrl($input)) {
                $error = t('err_url');
            } elseif (!RateLimiter::hit('page')) {
                http_response_code(429);
                $error = t('err_rate');
            } else {
                try {
                    $result = self::presentVideo(self::svc()->video($input));
                } catch (NotFound) {
                    $error = t('err_video_nf');
                } catch (UpstreamError) {
                    $error = t('err_upstream_d');
                }
            }
        }
        $faq = ta('faq_dl');
        $seo = Seo::page(t('dl_title'), t('dl_desc'), '/video-downloader', [
            'robots' => $input !== '' ? 'noindex,follow' : null,
            'jsonld' => [
                Seo::webApp(t('nav_downloader'), '/video-downloader', t('dl_desc')),
                Seo::faq($faq),
                Seo::breadcrumbs([[t('breadcrumb_home'), '/'], [t('nav_downloader'), '/video-downloader']]),
            ],
        ]);
        if ($input !== '') {
            $seo['robots'] = 'noindex,follow';
        }
        View::render('downloader', compact('seo', 'faq', 'input', 'result', 'error'));
    }

    private static function stories(): void
    {
        $input = trim((string) ($_GET['u'] ?? ''));
        $profile = null;
        $stories = null;
        $error = null;
        if ($input !== '') {
            $u = Security::normalizeUsername($input);
            if ($u === null) {
                $error = t('err_invalid');
            } elseif (!RateLimiter::hit('page')) {
                $error = t('err_rate');
            } else {
                try {
                    $profile = self::presentProfile(self::svc()->profile($u));
                    if (!$profile['private']) {
                        $s = self::svc()->stories($u, $profile['id']);
                        $stories = ['available' => $s['available'], 'items' => array_map([self::class, 'presentVideo'], $s['items'])];
                    }
                } catch (NotFound) {
                    $error = t('err_not_found_d', ['user' => $u]);
                } catch (UpstreamError) {
                    $error = t('err_upstream_d');
                }
            }
        }
        $faq = ta('faq_st');
        $seo = Seo::page(t('st_title'), t('st_desc'), '/story-viewer', [
            'jsonld' => [
                Seo::webApp(t('nav_stories'), '/story-viewer', t('st_desc')),
                Seo::faq($faq),
                Seo::breadcrumbs([[t('breadcrumb_home'), '/'], [t('nav_stories'), '/story-viewer']]),
            ],
        ]);
        if ($input !== '') {
            $seo['robots'] = 'noindex,follow';
        }
        View::render('stories', compact('seo', 'faq', 'input', 'profile', 'stories', 'error'));
    }

    private static function age(): void
    {
        $input = trim((string) ($_GET['q'] ?? ''));
        $result = null;
        $error = null;
        if ($input !== '') {
            if (Security::isNumericId($input)) {
                $ts = Service::idToTime($input);
                $result = ['ts' => $ts, 'id' => $input, 'profile' => null];
                if ($ts === null) {
                    $error = t('age_old');
                }
            } elseif (($u = Security::normalizeUsername($input)) === null) {
                $error = t('err_invalid');
            } elseif (!RateLimiter::hit('page')) {
                $error = t('err_rate');
            } else {
                try {
                    $p = self::presentProfile(self::svc()->profile($u));
                    $result = ['ts' => $p['created'], 'id' => $p['id'], 'profile' => $p];
                    if ($p['created'] === null) {
                        $error = t('age_old');
                    }
                } catch (NotFound) {
                    $error = t('err_not_found_d', ['user' => $u]);
                } catch (UpstreamError) {
                    $error = t('err_upstream_d');
                }
            }
        }
        $faq = ta('faq_age');
        $seo = Seo::page(t('age_title'), t('age_desc'), '/account-age', [
            'jsonld' => [
                Seo::webApp(t('nav_age'), '/account-age', t('age_desc')),
                Seo::faq($faq),
                Seo::breadcrumbs([[t('breadcrumb_home'), '/'], [t('nav_age'), '/account-age']]),
            ],
        ]);
        if ($input !== '') {
            $seo['robots'] = 'noindex,follow';
        }
        View::render('age', compact('seo', 'faq', 'input', 'result', 'error'));
    }

    private static function articles(): void
    {
        $list = Articles::forLang(I18n::current());
        $seo = Seo::page(t('art_title'), t('art_desc'), '/articles', [
            'jsonld' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => t('art_h1'),
                    'url' => abs_url(url('/articles')),
                    'hasPart' => array_map(static fn($a) => [
                        '@type' => 'BlogPosting',
                        'headline' => $a['title'],
                        'url' => abs_url(url('/articles/' . $a['slug'], $a['lang'])),
                    ], $list),
                ],
                Seo::breadcrumbs([[t('breadcrumb_home'), '/'], [t('nav_articles'), '/articles']]),
            ],
        ]);
        View::render('articles', compact('seo', 'list'));
    }

    private static function article(string $slug): void
    {
        $a = Articles::find($slug);
        $lang = I18n::current();
        if ($a === null) {
            self::notFound();
            return;
        }
        if (!isset($a['i18n'][$lang])) {
            // Not translated: send readers to the English version (no duplicate content).
            self::redirect(url('/articles/' . $slug, isset($a['i18n']['en']) ? 'en' : array_key_first($a['i18n'])), 302);
            return;
        }
        $c = $a['i18n'][$lang];
        $langs = array_values(array_intersect(I18n::codes(), array_keys($a['i18n'])));
        $seo = Seo::page($c['title'] . ' — ' . site_name(), $c['description'], '/articles/' . $slug, [
            'type' => 'article',
            'langs' => $langs,
            'published' => $a['date'],
            'modified' => $a['updated'] ?? $a['date'],
            'jsonld' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BlogPosting',
                    'headline' => $c['title'],
                    'description' => $c['description'],
                    'datePublished' => $a['date'],
                    'dateModified' => $a['updated'] ?? $a['date'],
                    'inLanguage' => I18n::meta()['hreflang'],
                    'image' => abs_url('/assets/img/og.png'),
                    'mainEntityOfPage' => abs_url(url('/articles/' . $slug)),
                    'author' => ['@type' => 'Organization', 'name' => site_name(), 'url' => base_url() . '/'],
                    'publisher' => ['@id' => base_url() . '/#org'],
                    'wordCount' => count(preg_split('/\s+/u', trim(strip_tags($c['body']))) ?: []),
                ],
                Seo::organization(),
                Seo::breadcrumbs([[t('breadcrumb_home'), '/'], [t('nav_articles'), '/articles'], [$c['title'], '/articles/' . $slug]]),
            ],
        ]);
        $related = array_values(array_filter(Articles::forLang($lang), static fn($x) => $x['slug'] !== $slug));
        View::render('article', ['seo' => $seo, 'a' => $a, 'c' => $c, 'slug' => $slug, 'langs' => $langs, 'related' => array_slice($related, 0, 3)]);
    }

    private static function staticPage(string $name): void
    {
        $seo = Seo::page(t($name . '_title') . ' — ' . site_name(), t($name . '_desc'), '/' . $name);
        $html = strtr(t($name . '_html'), [
            '{site}' => e(site_name()),
            '{email}' => e((string) Config::get('contact_email', '')),
        ]);
        View::render('page', ['seo' => $seo, 'title' => t($name . '_title'), 'html' => $html]);
    }

    private static function notFound(): void
    {
        self::error(404, t('err_404_t'), t('err_404_d'));
    }

    private static function error(int $status, string $title, string $message, ?string $username = null): void
    {
        $seo = Seo::noindex($title . ' — ' . site_name());
        View::render('error', compact('seo', 'title', 'message', 'status', 'username'), $status);
    }

    // ================================================================== presenters

    public static function presentProfile(array $p): array
    {
        $p['avatarSrc'] = Security::signMedia($p['avatar'] ?: null);
        $p['tiktokUrl'] = 'https://www.tiktok.com/@' . rawurlencode($p['uniqueId']);
        $link = trim($p['link']);
        if ($link !== '' && !preg_match('~^https?://~i', $link)) {
            $link = 'https://' . $link;
        }
        $p['linkHref'] = ($link !== '' && filter_var($link, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $link)) ? $link : null;
        $p['linkText'] = $p['linkHref'] ? (string) preg_replace('~^https?://(www\.)?~i', '', $p['linkHref']) : null;
        return $p;
    }

    public static function presentVideo(array $v): array
    {
        $id = preg_replace('/\D/', '', $v['id']) ?: 'video';
        $ck = (string) ($v['cookie'] ?? '');
        $dl = array_filter([
            'hd' => Security::signMedia($v['hdplay'], 'stream', "toklens_{$id}_hd.mp4", $ck),
            'nowm' => Security::signMedia($v['play'], 'stream', "toklens_{$id}.mp4", $ck),
            'wm' => Security::signMedia($v['wmplay'], 'stream', "toklens_{$id}_wm.mp4", $ck),
            'mp3' => Security::signMedia($v['music'], 'stream', "toklens_{$id}.mp3", $ck),
        ]);
        $images = [];
        foreach ($v['images'] as $i => $img) {
            $src = Security::signMedia($img);
            if ($src) {
                $images[] = ['src' => $src, 'dl' => Security::signMedia($img, 'media', "toklens_{$id}_" . ($i + 1) . '.jpg')];
            }
        }
        $author = $v['author']['uniqueId'] ?? '';
        return [
            'id' => $v['id'],
            'desc' => $v['desc'],
            'duration' => $v['duration'],
            'plays' => $v['plays'],
            'likes' => $v['likes'],
            'comments' => $v['comments'],
            'shares' => $v['shares'],
            'created' => $v['created'],
            'pinned' => $v['pinned'],
            'region' => $v['region'],
            'cover' => Security::signMedia($v['cover']),
            'stream' => Security::signMedia($v['hdplay'] ?? $v['play'], 'stream', '', $ck),
            'dl' => $dl,
            'images' => $images,
            'musicTitle' => $v['musicTitle'],
            'author' => [
                'uniqueId' => $author,
                'nickname' => $v['author']['nickname'] ?? '',
                'avatar' => Security::signMedia($v['author']['avatar'] ?? null),
            ],
            'url' => $author !== '' ? 'https://www.tiktok.com/@' . rawurlencode($author) . '/video/' . rawurlencode($v['id']) : null,
        ];
    }

    // ================================================================== JSON API

    private static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        Security::baseHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        echo json_script($data);
    }

    private static function apiGuard(): bool
    {
        if (!Security::sameOrigin() || !Security::verifyApiToken($_SERVER['HTTP_X_TOKEN'] ?? null)) {
            self::json(['error' => 'forbidden'], 403);
            return false;
        }
        if (!RateLimiter::hit('api')) {
            self::json(['error' => 'rate_limited'], 429);
            return false;
        }
        return true;
    }

    private static function apiVideos(): void
    {
        if (!self::apiGuard()) {
            return;
        }
        $u = Security::normalizeUsername((string) ($_GET['u'] ?? ''));
        $cursor = (string) ($_GET['cursor'] ?? '0');
        if ($u === null || !preg_match('/^\d{1,20}$/', $cursor)) {
            self::json(['error' => 'bad_request'], 400);
            return;
        }
        try {
            $r = self::svc()->videos($u, $cursor);
        } catch (UpstreamError | HttpException $e) {
            error_log('[toklens] videos: ' . $e->getMessage());
            self::json(['error' => 'upstream'], 502);
            return;
        }
        self::json([
            'items' => array_map([self::class, 'presentVideo'], $r['items']),
            'cursor' => $r['cursor'],
            'hasMore' => $r['hasMore'],
            'region' => $r['region'] ?? '',
        ]);
    }

    private static function apiStories(): void
    {
        if (!self::apiGuard()) {
            return;
        }
        $u = Security::normalizeUsername((string) ($_GET['u'] ?? ''));
        $id = (string) ($_GET['id'] ?? '');
        if ($u === null || ($id !== '' && !Security::isNumericId($id))) {
            self::json(['error' => 'bad_request'], 400);
            return;
        }
        try {
            $r = self::svc()->stories($u, $id);
        } catch (UpstreamError | HttpException $e) {
            error_log('[toklens] stories: ' . $e->getMessage());
            self::json(['error' => 'upstream'], 502);
            return;
        }
        self::json(['items' => array_map([self::class, 'presentVideo'], $r['items']), 'available' => $r['available']]);
    }

    // ================================================================== media proxy

    private static function media(): void
    {
        Security::baseHeaders();
        $v = Security::verifyMedia('media');
        if ($v === null) {
            self::plain(403, 'Forbidden');
            return;
        }
        if (!RateLimiter::hit('media')) {
            self::plain(429, 'Too Many Requests');
            return;
        }
        [$url, $name, $cookie] = $v;
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
        $file = ROOT . '/storage/media/' . hash('sha256', $url);
        $type = null;
        $body = null;
        if (is_file($file) && is_file($file . '.type') && filemtime($file) > time() - 86400) {
            $type = trim((string) file_get_contents($file . '.type'));
            $body = (string) file_get_contents($file);
        }
        if (!in_array($type, $allowed, true)) {
            try {
                $imgHeaders = ['Referer: https://www.tiktok.com/', 'Accept: image/avif,image/webp,image/*;q=0.8'];
                if ($cookie !== '') {
                    $imgHeaders[] = 'Cookie: ' . $cookie;
                }
                $r = Http::get($url, Security::MEDIA_HOSTS, [
                    'headers' => $imgHeaders,
                    'timeout' => 12,
                    'max_bytes' => 8_000_000,
                ]);
            } catch (HttpException) {
                self::plain(502, 'Bad Gateway');
                return;
            }
            $type = strtolower(trim(explode(';', $r['headers']['content-type'] ?? '')[0]));
            if ($r['status'] !== 200 || !in_array($type, $allowed, true)) {
                self::plain(502, 'Bad Gateway');
                return;
            }
            $body = $r['body'];
            @file_put_contents($file, $body, LOCK_EX);
            @file_put_contents($file . '.type', $type, LOCK_EX);
        }
        header('Content-Type: ' . $type);
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: public, max-age=86400, immutable');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cross-Origin-Resource-Policy: same-origin');
        if ($name !== '') {
            header('Content-Disposition: attachment; filename="' . $name . '"');
        }
        echo $body;
    }

    private static function stream(): void
    {
        Security::baseHeaders();
        $v = Security::verifyMedia('stream');
        if ($v === null) {
            self::plain(403, 'Forbidden');
            return;
        }
        [$url, $name, $cookie] = $v;
        if (!RateLimiter::hit($name !== '' ? 'download' : 'media')) {
            self::plain(429, 'Too Many Requests');
            return;
        }
        $fallback = str_ends_with($name, '.mp3') ? 'audio/mpeg' : 'video/mp4';
        Http::stream($url, Security::MEDIA_HOSTS, $name !== '' ? $name : null, $fallback, 400_000_000, $cookie);
    }

    // ================================================================== diagnostics

    /** Password-protected self-test: /diag?key=... (disabled while 'diag_key' is empty). */
    private static function diag(): void
    {
        $secret = (string) Config::get('diag_key', '');
        $given = (string) ($_GET['key'] ?? '');
        if (strlen($secret) < 12 || !hash_equals($secret, $given) || !RateLimiter::hit('page')) {
            self::plain(404, 'Not Found');
            return;
        }
        Security::baseHeaders();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        @set_time_limit(90);
        $ok = static fn(bool $b) => $b ? 'OK  ' : 'FAIL';
        $out = [];
        $out[] = 'TokLens diagnostics — ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $out[] = str_repeat('=', 60);
        $out[] = $ok(PHP_VERSION_ID >= 80100) . ' PHP ' . PHP_VERSION;
        foreach (['curl', 'json', 'mbstring', 'intl', 'openssl'] as $ext) {
            $out[] = $ok(extension_loaded($ext)) . ' ext ' . $ext;
        }
        $cv = function_exists('curl_version') ? curl_version() : [];
        $out[] = '     curl ' . ($cv['version'] ?? '?') . ' / ' . ($cv['ssl_version'] ?? '?');
        foreach (['cache', 'ratelimit', 'logs', 'media'] as $d) {
            $out[] = $ok(is_writable(ROOT . '/storage/' . $d)) . ' storage/' . $d . ' writable';
        }
        $out[] = '     base_url: ' . base_url() . ' | outbound_proxy: ' . (Config::get('outbound_proxy') ? 'set' : 'none');
        $out[] = '';

        $tests = [
            'tiktok.com profile page' => ['https://www.tiktok.com/@tiktok?lang=en', ['Accept: text/html', 'Accept-Language: en-US,en;q=0.9']],
            'tikwm user/info' => [rtrim((string) Config::get('providers.tikwm_base', 'https://www.tikwm.com'), '/') . '/api/user/info?unique_id=tiktok', ['Accept: application/json']],
            'tikwm user/posts' => [rtrim((string) Config::get('providers.tikwm_base', 'https://www.tikwm.com'), '/') . '/api/user/posts?unique_id=tiktok&count=1&cursor=0', ['Accept: application/json']],
        ];
        foreach ($tests as $label => [$url, $headers]) {
            $t = microtime(true);
            try {
                $r = Http::get($url, Security::API_HOSTS, ['headers' => $headers, 'timeout' => 15, 'max_bytes' => 6_000_000]);
                $ms = (int) ((microtime(true) - $t) * 1000);
                $body = $r['body'];
                $info = 'HTTP ' . $r['status'] . ', ' . strlen($body) . ' bytes, ' . $ms . ' ms';
                if (str_contains($label, 'tiktok.com')) {
                    $has = str_contains($body, '__UNIVERSAL_DATA_FOR_REHYDRATION__');
                    $code = preg_match('~"statusCode":(\d+)~', $body, $m) ? $m[1] : '?';
                    $user = str_contains($body, '"uniqueId":"tiktok"');
                    $out[] = $ok($r['status'] === 200 && $user) . " $label: $info | state-json=" . ($has ? 'yes' : 'no') . " statusCode=$code user-found=" . ($user ? 'yes' : 'no');
                } else {
                    $j = json_decode($body, true);
                    $code = is_array($j) ? (string) ($j['code'] ?? '?') : 'not-json';
                    $msg = is_array($j) ? (string) ($j['msg'] ?? '') : substr(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '', 0, 120);
                    $out[] = $ok($r['status'] === 200 && $code === '0') . " $label: $info | code=$code msg=" . mb_substr($msg, 0, 120);
                }
            } catch (\Throwable $e) {
                $out[] = 'FAIL ' . $label . ': ' . $e->getMessage() . ' (' . (int) ((microtime(true) - $t) * 1000) . ' ms)';
            }
        }
        if (trim((string) Config::get('providers.rapidapi_key', '')) !== '') {
            $t = microtime(true);
            try {
                $p = (new \App\TikTok\TikwmProvider())->profile('tiktok');
                $out[] = $ok($p !== null) . ' rapidapi user/info: ' . ($p ? 'followers=' . ($p['followers'] ?? '?') : 'not found') . ', ' . (int) ((microtime(true) - $t) * 1000) . ' ms';
            } catch (\Throwable $e) {
                $out[] = 'FAIL rapidapi user/info: ' . $e->getMessage();
            }
        } else {
            $out[] = '     rapidapi_key: not set';
        }
        $out[] = '';
        foreach (array_unique(array_merge((array) Config::get('providers.profile', []), (array) Config::get('providers.video', []))) as $n) {
            $out[] = '     circuit-breaker ' . $n . ': ' . (Cache::get('cb:' . $n) ? 'OPEN (skipped for 5 min after a failure)' : 'closed');
        }
        $log = ROOT . '/storage/logs/php-error.log';
        if (is_file($log)) {
            $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -15);
            $out[] = '';
            $out[] = 'Last log lines:';
            foreach ($lines as $l) {
                $out[] = '  ' . mb_substr($l, 0, 300);
            }
        }
        echo implode("\n", $out), "\n";
    }

    // ================================================================== robots & sitemap

    private static function robots(): void
    {
        Security::baseHeaders();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /media\n";
        echo "Disallow: /stream\n";
        echo "Disallow: /*/lookup\n";
        echo "Disallow: /*?*url=\n";
        echo "\nSitemap: " . base_url() . "/sitemap.xml\n";
    }

    private static function sitemap(): void
    {
        Security::baseHeaders();
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $codes = I18n::codes();
        $entries = [];
        $static = ['/' => '1.0', '/video-downloader' => '0.9', '/story-viewer' => '0.9', '/account-age' => '0.9', '/articles' => '0.7', '/about' => '0.3', '/privacy' => '0.2', '/terms' => '0.2'];
        foreach ($static as $path => $prio) {
            $entries[] = [$path, $codes, $prio, null];
        }
        foreach (Articles::all() as $slug => $a) {
            $langs = array_values(array_intersect($codes, array_keys($a['i18n'])));
            $entries[] = ['/articles/' . $slug, $langs, '0.8', $a['updated'] ?? $a['date']];
        }
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($entries as [$path, $langs, $prio, $mod]) {
            foreach ($langs as $l) {
                $x .= "  <url>\n    <loc>" . e(abs_url(url($path, $l))) . "</loc>\n";
                if ($mod) {
                    $x .= '    <lastmod>' . e($mod) . "</lastmod>\n";
                }
                $x .= "    <priority>$prio</priority>\n";
                foreach ($langs as $alt) {
                    $x .= '    <xhtml:link rel="alternate" hreflang="' . e(I18n::meta($alt)['hreflang']) . '" href="' . e(abs_url(url($path, $alt))) . "\"/>\n";
                }
                $x .= "  </url>\n";
            }
        }
        $x .= "</urlset>\n";
        echo $x;
    }

    // ================================================================== helpers

    private static function redirect(string $to, int $status = 302): void
    {
        Security::baseHeaders();
        header('Location: ' . $to, true, $status);
    }

    private static function plain(int $status, string $msg): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $msg;
    }

    public static function handleException(\Throwable $e): void
    {
        error_log('[toklens] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (headers_sent()) {
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        try {
            if (I18n::current() === '' || !I18n::supported(I18n::current())) {
                I18n::set('en');
            }
            I18n::set(I18n::current());
            self::error(500, t('err_500_t'), t('err_generic'));
        } catch (\Throwable) {
            self::plain(500, 'Internal Server Error');
        }
    }
}
