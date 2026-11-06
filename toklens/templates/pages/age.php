<?php
/** @var array $faq @var string $input @var ?array $result @var ?string $error */
use App\View;
?>
<section class="hero hero-sm wrap">
  <p class="badge glass"><?= icon('calendar') ?> <?= e(t('age_badge')) ?></p>
  <h1 class="hero-title"><?= e(t('age_h1')) ?></h1>
  <p class="hero-lead"><?= e(t('age_lead')) ?></p>
  <?php View::partial('search', ['action' => url('/account-age'), 'name' => 'q', 'placeholder' => t('age_placeholder'), 'button' => t('age_btn'), 'value' => $input, 'icon' => 'calendar']); ?>
  <p class="age-live muted small" data-age-live hidden></p>
  <?php if ($error): ?><p class="alert alert-error" role="alert"><?= icon('info') ?> <?= e($error) ?></p><?php endif; ?>
</section>

<?php if ($result && $result['ts']): $p = $result['profile']; ?>
<div class="wrap narrow">
  <section class="age-result glass panel reveal" aria-live="polite">
    <?php if ($p): ?>
    <a class="author" href="<?= e(url('/user/' . $p['uniqueId'])) ?>">
      <?php if ($p['avatarSrc']): ?><img src="<?= e($p['avatarSrc']) ?>" alt="" width="56" height="56"><?php else: ?><span class="avatar-sm"><?= icon('user') ?></span><?php endif; ?>
      <span><strong><bdi><?= e($p['nickname'] ?: $p['uniqueId']) ?></bdi></strong><br><span class="muted small" dir="ltr">@<?= e($p['uniqueId']) ?></span></span>
    </a>
    <?php endif; ?>
    <p class="muted"><?= e(t('age_result')) ?></p>
    <p class="age-date"><time datetime="<?= e(gmdate('c', $result['ts'])) ?>" data-ts="<?= (int) $result['ts'] ?>"><?= e(fmt_date($result['ts'])) ?></time></p>
    <p class="age-span"><?= icon('clock') ?> <?= e(fmt_age($result['ts'])) ?></p>
    <p class="muted small">ID: <code dir="ltr"><?= e($result['id']) ?></code></p>
  </section>
</div>
<?php endif; ?>

<section class="section wrap narrow reveal">
  <article class="prose glass panel">
    <h2><?= e(t('seo_age_h2')) ?></h2>
    <?= t('seo_age_html') ?>
  </article>
</section>

<?php View::partial('faq', ['faq' => $faq]); ?>
<?php View::partial('tools', ['except' => '/account-age']); ?>
