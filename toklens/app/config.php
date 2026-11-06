<?php

/*
 * TokLens configuration.
 * Edit the values below before going live. Never commit real secrets.
 */

return [
    // Brand name shown everywhere on the site.
    'site_name' => 'TokLens',

    // Full public URL of the site WITHOUT trailing slash, e.g. 'https://toklens.example'.
    // Strongly recommended in production (used for canonical URLs, sitemap, Open Graph).
    // When empty, it is detected from the request.
    'base_url' => '',

    // Default / fallback language.
    'default_lang' => 'en',

    // Secret used to sign media URLs and API tokens (min 32 chars).
    // Leave empty to auto-generate one in storage/.app_key on first run.
    'app_key' => '',

    // Diagnostics page: set a long secret (12+ chars) then open https://your-site/diag?key=THAT_SECRET
    // It tests PHP, storage and the connection to TikTok / tikwm. Leave empty to disable it.
    'diag_key' => '',

    // Never enable on a public server: shows PHP errors.
    'debug' => false,

    // Set to true ONLY if the site runs behind Cloudflare or a reverse proxy you control,
    // so the real visitor IP is read from CF-Connecting-IP / X-Forwarded-For.
    'trust_proxy' => false,

    // Let search engines index public profile pages (/en/user/xxx).
    'index_profiles' => true,

    // Contact address shown on the About / Privacy pages.
    'contact_email' => 'contact@example.com',

    // Search console verification codes (optional).
    'google_site_verification' => '',
    'bing_site_verification' => '',

    // Data providers. Profile lookups try them in order until one answers.
    //  - web   : reads the public profile page on tiktok.com (no key needed)
    //  - tikwm : public tikwm.com API (profile, videos, single video, stories)
    //  - mock  : fake demo data, for local testing only
    'providers' => [
        'profile' => ['web', 'tikwm'],
        'tikwm_base' => 'https://www.tikwm.com',
        // Paid tikwm-compatible service (e.g. tikwmapi.com): copy these from its dashboard / docs.
        'tikwm_prefix' => '/api',     // path before /user/info, /user/posts ...
        'tikwm_key' => '',            // your API key
        'tikwm_key_header' => '',     // header name if the key goes in a header (e.g. 'X-API-KEY')
        'tikwm_key_param' => '',      // query parameter name if the key goes in the URL (e.g. 'key')
        // Endpoint used for stories. Must return tikwm-style JSON {code:0, data:{videos:[...]}}.
        'stories_endpoint' => 'https://www.tikwm.com/api/user/story',
        // Single video / downloader: tiktok.com first, then tikwm.
        'video' => ['web', 'tikwm'],

        // RECOMMENDED when tikwm.com blocks your hosting (diag shows "403 Just a moment"):
        // subscribe to "TikTok Scraper" on rapidapi.com (tiktok-scraper7) and paste your key here.
        // Then the videos list, stories and downloads go through RapidAPI.
        'rapidapi_key' => '',
        'rapidapi_host' => 'tiktok-scraper7.p.rapidapi.com',
    ],

    // Optional outbound proxy for requests to TikTok (helps when the host IP is blocked).
    // Example: 'http://user:pass@proxy.example:8080'
    'outbound_proxy' => '',

    // Cache lifetimes in seconds.
    'cache_ttl' => [
        'profile' => 600,
        'videos' => 300,
        'stories' => 180,
        'video' => 900,
        'not_found' => 120,
    ],

    // Rate limits per visitor IP: [max requests, window in seconds].
    'rate_limits' => [
        'page' => [40, 60],      // profile / story / downloader lookups
        'api' => [60, 60],       // JSON API (videos, stories)
        'media' => [400, 60],    // proxied images & video playback chunks
        'download' => [30, 600], // file downloads
        'session' => [40, 600],     // lookups per browser session / 10 min
        'session_day' => [400, 86400],
        'verify' => [15, 600],      // browser checks per IP / 10 min
        'crawler' => [120, 60],     // verified search engines
    ],

    // ------------------------------------------------------------------ protection
    'guard' => [
        'enabled' => true,
        // Proof-of-work difficulty for the browser check (16-19 recommended; +1 = twice the work).
        'pow_bits' => 17,
        // How long a passed browser check lasts (seconds).
        'pass_ttl' => 21600,
        // Automatic ban after this many security events from one IP within 10 minutes.
        'auto_ban_after' => 40,
        'auto_ban_hours' => 24,
        // Visit logs for the admin panel, kept this many days.
        'analytics' => true,
        'retention_days' => 60,
        // OPTIONAL but strongest: free Cloudflare Turnstile (dash.cloudflare.com → Turnstile → Add site).
        'turnstile_site_key' => '',
        'turnstile_secret' => '',
    ],

    // ------------------------------------------------------------------ admin panel
    // Secret path, e.g. 'cp-7Hq2x9LmZt' → https://your-site/cp-7Hq2x9LmZt  (8-64 chars: letters, digits, - _)
    // Leave empty to disable the panel.
    'admin_path' => '',
    // Long password (12+ chars). You may instead put a password_hash() value in admin_password_hash.
    'admin_password' => '',
    'admin_password_hash' => '',
    // Optional: only these IPs may open the panel, e.g. ['1.2.3.4']. Empty = any IP (password still required).
    'admin_ips' => [],
];
