<?php
/** Browser-check interstitial. @var string $challenge @var int $bits @var string $tsKey */
use App\I18n;
use App\Security;

$nonce = Security::nonce();
?>
<!doctype html>
<html lang="<?= e(I18n::current()) ?>" dir="<?= e(I18n::dir()) ?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e(t('verify_title')) ?> — <?= e(site_name()) ?></title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script nonce="<?= e($nonce) ?>">(function(){try{var t=localStorage.getItem('theme');if(!t&&matchMedia('(prefers-color-scheme: light)').matches)t='light';if(t)document.documentElement.dataset.theme=t;}catch(e){}})();</script>
</head>
<body class="page-challenge">
<div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span></div>
<main class="main">
  <section class="hero wrap">
    <div class="guard-card glass panel" id="guard"
         data-c="<?= e($challenge) ?>" data-bits="<?= (int) $bits ?>" data-ts-key="<?= e($tsKey) ?>">
      <svg class="guard-logo" aria-hidden="true" viewBox="0 0 32 32"><defs><linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#25f4ee"/><stop offset=".55" stop-color="#7c5cff"/><stop offset="1" stop-color="#fe2c55"/></linearGradient></defs><circle cx="14" cy="14" r="10" fill="none" stroke="url(#gg)" stroke-width="3.2"/><path d="M11.5 9.8v8.4l7-4.2z" fill="url(#gg)"/><path d="M21.5 21.5 28 28" stroke="url(#gg)" stroke-width="3.6" stroke-linecap="round"/></svg>
      <h1 class="guard-title"><?= e(t('verify_title')) ?></h1>
      <p class="muted" data-guard-msg><?= e(t('verify_text')) ?></p>
      <div class="guard-bar" aria-hidden="true"><span></span></div>
      <div id="ts-box" class="guard-ts"></div>
      <p class="muted small guard-fail" data-guard-fail hidden><?= e(t('verify_fail')) ?></p>
      <noscript><p class="alert alert-error"><?= e(t('verify_fail')) ?></p></noscript>
    </div>
  </section>
</main>
<?php if ($tsKey !== ''): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer nonce="<?= e($nonce) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/guard.js')) ?>" defer nonce="<?= e($nonce) ?>"></script>
</body>
</html>
