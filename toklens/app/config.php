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
    ],
];
