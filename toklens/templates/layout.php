<?php
/** @var array $seo @var string $content @var string $page */

use App\Config;
use App\I18n;
use App\Security;

$lang = I18n::current();
$meta = I18n::meta();
$nonce = Security::nonce();
$nav = [
    ['/', 'nav_profile', 'user', 'home'],
    ['/video-downloader', 'nav_downloader', 'download', 'downloader'],
    ['/story-viewer', 'nav_stories', 'stories', 'stories'],
    ['/account-age', 'nav_age', 'calendar', 'age'],
    ['/articles', 'nav_articles', 'book', 'articles'],
];
$active = match ($page) {
    'user' => 'home',
    'article' => 'articles',
    default => $page,
};
// Path without language prefix, for the language switcher.
$curPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$curPath = preg_replace('~^/[a-z]{2,3}(?=/|$)~', '', $curPath) ?: '/';
$switchable = $page !== 'error';
$jsI18n = [
    'loading' => t('loading'), 'loadMore' => t('load_more'), 'noVideos' => t('no_videos'), 'noStories' => t('no_stories'),
    'views' => t('views'), 'likes' => t('likes'), 'comments' => t('comments'), 'shares' => t('shares'),
    'copied' => t('copied'), 'error' => t('err_generic'), 'rate' => t('err_rate'), 'pinned' => t('pinned'),
    'hd' => t('dl_hd'), 'nowm' => t('dl_nowm'), 'wm' => t('dl_wm'), 'mp3' => t('dl_mp3'), 'photo' => t('photo_post'),
    'download' => t('download'), 'openTiktok' => t('open_tiktok'), 'retry' => t('retry'), 'fromVideos' => t('from_videos'),
    'ageFmt' => t('age_fmt'), 'ageOld' => t('age_old'), 'storiesUnavailable' => t('st_unavailable'),
];
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($meta['dir']) ?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seo['title']) ?></title>
<?php if ($seo['description'] !== ''): ?>
<meta name="description" content="<?= e($seo['description']) ?>">
<?php endif; ?>
<meta name="robots" content="<?= e($seo['robots']) ?>">
<link rel="canonical" href="<?= e($seo['canonical']) ?>">
<?php if (!str_starts_with($seo['robots'], 'noindex')): foreach ($seo['alternates'] as $code => $href): ?>
<link rel="alternate" hreflang="<?= e(I18n::meta($code)['hreflang']) ?>" href="<?= e($href) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e($seo['x_default']) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e(site_name()) ?>">
<meta property="og:type" content="<?= e($seo['type']) ?>">
<meta property="og:title" content="<?= e($seo['title']) ?>">
<meta property="og:description" content="<?= e($seo['description']) ?>">
<meta property="og:url" content="<?= e($seo['canonical']) ?>">
<meta property="og:image" content="<?= e($seo['image']) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="<?= e($meta['og']) ?>">
<?php if ($seo['published']): ?>
<meta property="article:published_time" content="<?= e($seo['published']) ?>">
<meta property="article:modified_time" content="<?= e($seo['modified']) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($seo['title']) ?>">
<meta name="twitter:description" content="<?= e($seo['description']) ?>">
<meta name="twitter:image" content="<?= e($seo['image']) ?>">
<meta name="theme-color" content="#0b0912" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#eef1f8" media="(prefers-color-scheme: light)">
<meta name="color-scheme" content="dark light">
<meta name="format-detection" content="telephone=no">
<?php if ($v = Config::get('google_site_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>"><?php endif; ?>
<?php if ($v = Config::get('bing_site_verification')): ?><meta name="msvalidate.01" content="<?= e($v) ?>"><?php endif; ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Vazirmatn:wght@400;500;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script nonce="<?= e($nonce) ?>">(function(){try{var t=localStorage.getItem('theme');if(!t&&matchMedia('(prefers-color-scheme: light)').matches)t='light';if(t)document.documentElement.dataset.theme=t;}catch(e){}})();</script>
<?php foreach ($seo['jsonld'] as $ld): ?>
<script type="application/ld+json" nonce="<?= e($nonce) ?>"><?= json_script($ld) ?></script>
<?php endforeach; ?>
</head>
<body class="page-<?= e($page) ?>">
<?php require ROOT . '/templates/partials/icons.php'; ?>
<div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span><span class="blob b4"></span><span class="grain"></span></div>
<a class="skip" href="#main"><?= e(t('skip')) ?></a>

<header class="nav-wrap">
  <nav class="nav glass glass-refract" aria-label="<?= e(site_name()) ?>">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e(site_name()) ?> — <?= e(t('nav_profile')) ?>">
      <svg class="brand-logo" aria-hidden="true"><use href="#i-logo"></use></svg>
      <span class="brand-name"><?= e(site_name()) ?></span>
    </a>
    <ul class="nav-links" role="list">
      <?php foreach ($nav as [$href, $key, $ic, $id]): ?>
      <li><a href="<?= e(url($href)) ?>"<?= $active === $id ? ' aria-current="page"' : '' ?>><?= e(t($key)) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="nav-actions">
      <button class="icon-btn glass lit" type="button" popovertarget="lang-menu" aria-label="<?= e(t('menu_language')) ?>">
        <?= icon('globe') ?><span class="lang-code"><?= e(strtoupper($lang)) ?></span>
      </button>
      <button class="icon-btn glass lit" type="button" data-theme-toggle aria-label="<?= e(t('toggle_theme')) ?>">
        <?= icon('sun', 'only-dark') ?><?= icon('moon', 'only-light') ?>
      </button>
    </div>
  </nav>
  <div id="lang-menu" class="lang-menu glass" popover>
    <p class="lang-title"><?= icon('globe') ?> <?= e(t('menu_language')) ?></p>
    <ul role="list">
      <?php foreach (I18n::codes() as $code): $href = $switchable ? url($curPath, $code) : url('/', $code); ?>
      <li><a href="<?= e($href) ?>" hreflang="<?= e(I18n::meta($code)['hreflang']) ?>" lang="<?= e($code) ?>" dir="<?= e(I18n::meta($code)['dir']) ?>"<?= $code === $lang ? ' aria-current="true"' : '' ?>><?= e(I18n::meta($code)['name']) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</header>

<main id="main" class="main">
<?= $content ?>
</main>

<nav class="dock glass glass-refract" aria-label="<?= e(t('tools')) ?>">
  <?php foreach ($nav as [$href, $key, $ic, $id]): ?>
  <a href="<?= e(url($href)) ?>"<?= $active === $id ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= e(t($key . '_short')) ?></span></a>
  <?php endforeach; ?>
</nav>

<footer class="footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="brand" href="<?= e(url('/')) ?>"><svg class="brand-logo" aria-hidden="true"><use href="#i-logo"></use></svg><span class="brand-name"><?= e(site_name()) ?></span></a>
      <p class="muted small"><?= e(t('footer_text')) ?></p>
    </div>
    <div>
      <h2 class="footer-h"><?= e(t('tools')) ?></h2>
      <ul role="list" class="footer-list">
        <?php foreach ($nav as [$href, $key]): ?><li><a href="<?= e(url($href)) ?>"><?= e(t($key)) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2 class="footer-h"><?= e(t('footer_info')) ?></h2>
      <ul role="list" class="footer-list">
        <li><a href="<?= e(url('/about')) ?>"><?= e(t('about_title')) ?></a></li>
        <li><a href="<?= e(url('/privacy')) ?>"><?= e(t('privacy_title')) ?></a></li>
        <li><a href="<?= e(url('/terms')) ?>"><?= e(t('terms_title')) ?></a></li>
      </ul>
    </div>
    <div>
      <h2 class="footer-h"><?= e(t('menu_language')) ?></h2>
      <ul role="list" class="footer-list footer-langs">
        <?php foreach (I18n::codes() as $code): ?><li><a href="<?= e(url('/', $code)) ?>" hreflang="<?= e(I18n::meta($code)['hreflang']) ?>" lang="<?= e($code) ?>"><?= e(I18n::meta($code)['name']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
  <p class="wrap copyright muted small">© <?= gmdate('Y') ?> <?= e(site_name()) ?>. <?= e(t('rights')) ?></p>
</footer>

<dialog id="player" class="modal glass" aria-labelledby="player-title">
  <div class="modal-head">
    <h2 id="player-title" class="modal-title"></h2>
    <button class="icon-btn glass" type="button" data-close aria-label="<?= e(t('close')) ?>"><?= icon('x') ?></button>
  </div>
  <div class="modal-media"></div>
  <div class="modal-meta muted small"></div>
  <div class="modal-actions"></div>
</dialog>

<div class="toasts" role="status" aria-live="polite"></div>
<script type="application/json" id="i18n" nonce="<?= e($nonce) ?>"><?= json_script($jsI18n) ?></script>
<script src="<?= e(asset('js/app.js')) ?>" defer nonce="<?= e($nonce) ?>"></script>
</body>
</html>
