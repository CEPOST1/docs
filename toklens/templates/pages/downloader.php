<?php
/** @var array $faq @var string $input @var ?array $result @var ?string $error */
use App\View;
?>
<section class="hero hero-sm wrap">
  <p class="badge glass"><?= icon('download') ?> <?= e(t('dl_badge')) ?></p>
  <h1 class="hero-title"><?= e(t('dl_h1')) ?></h1>
  <p class="hero-lead"><?= e(t('dl_lead')) ?></p>
  <?php View::partial('search', ['action' => url('/video-downloader'), 'name' => 'url', 'placeholder' => t('dl_placeholder'), 'button' => t('dl_btn'), 'value' => $input, 'icon' => 'link', 'inputmode' => 'url']); ?>
  <?php if ($error): ?><p class="alert alert-error" role="alert"><?= icon('info') ?> <?= e($error) ?></p><?php endif; ?>
</section>

<?php if ($result): ?>
<div class="wrap"><?php View::partial('video-result', ['v' => $result]); ?></div>
<?php endif; ?>

<section class="section wrap narrow reveal">
  <article class="prose glass panel">
    <h2><?= e(t('seo_dl_h2')) ?></h2>
    <?= t('seo_dl_html') ?>
  </article>
</section>

<?php View::partial('faq', ['faq' => $faq]); ?>
<?php View::partial('tools', ['except' => '/video-downloader']); ?>
