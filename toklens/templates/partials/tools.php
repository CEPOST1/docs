<?php
/** Cross-links to the other tools (internal linking helps SEO). @var string $except */
$tools = [
    ['/', 'nav_profile', 'user', 'tool_profile_d'],
    ['/video-downloader', 'nav_downloader', 'download', 'tool_dl_d'],
    ['/story-viewer', 'nav_stories', 'stories', 'tool_st_d'],
    ['/account-age', 'nav_age', 'calendar', 'tool_age_d'],
];
?>
<section class="section wrap reveal" aria-labelledby="tools-h">
  <h2 id="tools-h" class="section-title"><?= e(t('more_tools')) ?></h2>
  <div class="grid grid-4">
    <?php foreach ($tools as [$href, $key, $ic, $desc]): if ($href === ($except ?? '') || !App\Features::pathOn($href)) continue; ?>
    <a class="card tool-card glass lit" href="<?= e(url($href)) ?>">
      <span class="card-icon"><?= icon($ic) ?></span>
      <span class="card-title"><?= e(t($key)) ?></span>
      <span class="muted small"><?= e(t($desc)) ?></span>
      <span class="card-more"><?= icon('arrow', 'flip-rtl') ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>
