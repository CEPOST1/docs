<?php
/** @var array $a @var array $c @var string $slug @var array $langs @var array $related */
use App\Articles;
use App\I18n;

$toolKey = ['/' => 'nav_profile', '/video-downloader' => 'nav_downloader', '/story-viewer' => 'nav_stories', '/account-age' => 'nav_age'][$a['tool'] ?? ''] ?? null;
?>
<div class="wrap narrow">
  <nav class="crumbs small muted" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>"><?= e(t('breadcrumb_home')) ?></a> <span aria-hidden="true">/</span>
    <a href="<?= e(url('/articles')) ?>"><?= e(t('nav_articles')) ?></a>
  </nav>
  <article class="prose article glass panel reveal">
    <header>
      <h1><?= e($c['title']) ?></h1>
      <p class="muted small">
        <?= icon('calendar') ?> <?= e(t('published')) ?> <time datetime="<?= e($a['date']) ?>"><?= e(fmt_date(strtotime($a['date']) ?: null, false)) ?></time>
        · <?= e(t('min_read', ['n' => Articles::readingMinutes($c['body'])])) ?>
      </p>
      <p class="lead"><?= e($c['description']) ?></p>
    </header>
    <?= $c['body'] ?>
    <?php if ($toolKey && App\Features::pathOn($a['tool'])): ?>
    <p class="cta"><a class="btn btn-primary" href="<?= e(url($a['tool'])) ?>"><?= icon('sparkles') ?> <?= e(t('art_try')) ?>: <?= e(t($toolKey)) ?></a></p>
    <?php endif; ?>
    <?php if (count($langs) > 1): ?>
    <p class="muted small"><?= icon('globe') ?> <?= e(t('art_lang_note')) ?>
      <?php foreach ($langs as $l): ?><a href="<?= e(url('/articles/' . $slug, $l)) ?>" hreflang="<?= e(I18n::meta($l)['hreflang']) ?>" lang="<?= e($l) ?>"><?= e(I18n::meta($l)['name']) ?></a> <?php endforeach; ?>
    </p>
    <?php endif; ?>
  </article>
</div>

<?php if ($related): ?>
<section class="section wrap reveal" aria-labelledby="rel-h">
  <h2 id="rel-h" class="section-title"><?= e(t('related')) ?></h2>
  <div class="grid grid-3">
    <?php foreach ($related as $r): ?>
    <article class="card article-card glass lit" lang="<?= e($r['lang']) ?>">
      <h3 class="card-title"><a class="stretched" href="<?= e(url('/articles/' . $r['slug'], $r['lang'])) ?>"><?= e($r['title']) ?></a></h3>
      <p class="muted small"><?= e($r['description']) ?></p>
      <span class="card-more"><?= e(t('read_more')) ?> <?= icon('arrow', 'flip-rtl') ?></span>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
