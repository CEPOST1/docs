<?php
/** @var array $faq @var string $input @var ?array $profile @var ?array $stories @var ?string $error */
use App\View;
?>
<section class="hero hero-sm wrap">
  <p class="badge glass"><?= icon('eye') ?> <?= e(t('st_badge')) ?></p>
  <h1 class="hero-title"><?= e(t('st_h1')) ?></h1>
  <p class="hero-lead"><?= e(t('st_lead')) ?></p>
  <?php View::partial('search', ['action' => url('/story-viewer'), 'name' => 'u', 'placeholder' => t('search_placeholder'), 'button' => t('st_btn'), 'value' => $input, 'icon' => 'stories']); ?>
  <?php if ($error): ?><p class="alert alert-error" role="alert"><?= icon('info') ?> <?= e($error) ?></p><?php endif; ?>
</section>

<?php if ($profile): ?>
<div class="wrap">
  <section class="glass panel reveal">
    <a class="author" href="<?= e(url('/user/' . $profile['uniqueId'])) ?>">
      <?php if ($profile['avatarSrc']): ?><img src="<?= e($profile['avatarSrc']) ?>" alt="" width="56" height="56"><?php else: ?><span class="avatar-sm"><?= icon('user') ?></span><?php endif; ?>
      <span><strong><bdi><?= e($profile['nickname'] ?: $profile['uniqueId']) ?></bdi></strong><br><span class="muted small" dir="ltr">@<?= e($profile['uniqueId']) ?></span></span>
    </a>
    <?php if ($profile['private']): ?>
      <p class="alert glass"><?= icon('lock') ?> <?= e(t('private_notice')) ?></p>
    <?php elseif ($stories && $stories['items']): ?>
      <h2 class="panel-title"><?= e(t('st_found', ['n' => count($stories['items'])])) ?></h2>
      <div class="vgrid" data-static-feed><?php foreach ($stories['items'] as $v): ?>
        <button class="vcard" type="button" data-video="<?= e(json_script($v)) ?>">
          <?php if ($v['cover']): ?><img src="<?= e($v['cover']) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="cover-fallback"><?= icon('video') ?></span><?php endif; ?>
          <span class="vcard-play"><?= icon('play') ?></span>
          <?php if ($v['created']): ?><span class="vcard-badge"><?= e(fmt_date($v['created'])) ?></span><?php endif; ?>
        </button>
      <?php endforeach; ?></div>
    <?php elseif ($stories && !$stories['available']): ?>
      <p class="alert glass"><?= icon('info') ?> <?= e(t('st_unavailable')) ?></p>
    <?php else: ?>
      <p class="alert glass"><?= icon('info') ?> <?= e(t('no_stories')) ?></p>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>

<section class="section wrap narrow reveal">
  <article class="prose glass panel">
    <h2><?= e(t('seo_st_h2')) ?></h2>
    <?= t('seo_st_html') ?>
  </article>
</section>

<?php View::partial('faq', ['faq' => $faq]); ?>
<?php View::partial('tools', ['except' => '/story-viewer']); ?>
