<?php
/** @var array $faq @var ?string $error @var string $input */
use App\View;

$features = ta('features');
$featureIcons = ['users', 'calendar', 'edit', 'video', 'stories', 'shield'];
$steps = ta('how');
?>
<section class="hero wrap">
  <p class="badge glass"><?= icon('sparkles') ?> <?= e(t('home_badge')) ?></p>
  <h1 class="hero-title"><?= e(t('home_h1')) ?></h1>
  <p class="hero-lead"><?= e(t('home_lead')) ?></p>
  <?php View::partial('search', ['action' => url('/lookup'), 'name' => 'q', 'placeholder' => t('search_placeholder'), 'button' => t('search_btn'), 'value' => $input, 'icon' => 'search']); ?>
  <?php if ($error): ?><p class="alert alert-error" role="alert"><?= icon('info') ?> <?= e($error) ?></p><?php endif; ?>
  <p class="chips"><span class="muted small"><?= e(t('popular')) ?></span>
    <?php foreach (['tiktok', 'khaby.lame', 'charlidamelio', 'mrbeast', 'bellapoarch'] as $s): ?>
    <a class="chip glass" href="<?= e(url('/user/' . $s)) ?>">@<?= e($s) ?></a>
    <?php endforeach; ?>
  </p>
</section>

<section class="section wrap reveal" aria-labelledby="feat-h">
  <h2 id="feat-h" class="section-title"><?= e(t('feat_title')) ?></h2>
  <div class="grid grid-3">
    <?php foreach ($features as $i => [$title, $desc]):
        if (($i === 3 && !App\Features::on('downloads') && !App\Features::on('downloader')) || ($i === 4 && !App\Features::on('stories'))) continue; ?>
    <article class="card glass lit">
      <span class="card-icon"><?= icon($featureIcons[$i] ?? 'sparkles') ?></span>
      <h3 class="card-title"><?= e($title) ?></h3>
      <p class="muted"><?= e($desc) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section wrap reveal" aria-labelledby="how-h">
  <h2 id="how-h" class="section-title"><?= e(t('how_title')) ?></h2>
  <ol class="steps" role="list">
    <?php foreach ($steps as $i => [$title, $desc]): ?>
    <li class="step glass"><span class="step-n"><?= $i + 1 ?></span><h3 class="card-title"><?= e($title) ?></h3><p class="muted"><?= e($desc) ?></p></li>
    <?php endforeach; ?>
  </ol>
</section>

<?php View::partial('tools', ['except' => '/']); ?>

<section class="section wrap narrow reveal">
  <article class="prose glass panel">
    <h2><?= e(t('seo_home_h2')) ?></h2>
    <?= t('seo_home_html') ?>
  </article>
</section>

<?php View::partial('faq', ['faq' => $faq]); ?>
