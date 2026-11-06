<?php
/** @var array $list */
use App\Articles;
?>
<section class="hero hero-sm wrap">
  <p class="badge glass"><?= icon('book') ?> <?= e(t('nav_articles')) ?></p>
  <h1 class="hero-title"><?= e(t('art_h1')) ?></h1>
  <p class="hero-lead"><?= e(t('art_lead')) ?></p>
</section>

<section class="wrap">
  <div class="grid grid-3">
    <?php foreach ($list as $a): ?>
    <article class="card article-card glass lit reveal" lang="<?= e($a['lang']) ?>" dir="<?= e(App\I18n::meta($a['lang'])['dir']) ?>">
      <p class="muted small"><?= icon('calendar') ?> <time datetime="<?= e($a['date']) ?>"><?= e(fmt_date(strtotime($a['date']) ?: null, false)) ?></time> · <?= e(t('min_read', ['n' => Articles::readingMinutes($a['body'])])) ?></p>
      <h2 class="card-title"><a class="stretched" href="<?= e(url('/articles/' . $a['slug'], $a['lang'])) ?>"><?= e($a['title']) ?></a></h2>
      <p class="muted"><?= e($a['description']) ?></p>
      <span class="card-more"><?= e(t('read_more')) ?> <?= icon('arrow', 'flip-rtl') ?></span>
    </article>
    <?php endforeach; ?>
  </div>
</section>
