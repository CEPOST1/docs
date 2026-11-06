<?php
/** Server-rendered single video with download buttons. @var array $v */
$labels = ['hd' => 'dl_hd', 'nowm' => 'dl_nowm', 'wm' => 'dl_wm', 'mp3' => 'dl_mp3'];
?>
<article class="dl-result glass panel reveal">
  <div class="dl-media">
    <?php if ($v['images']): ?>
      <div class="photo-strip">
        <?php foreach ($v['images'] as $i => $img): ?>
        <a href="<?= e($img['dl']) ?>" download class="photo"><img src="<?= e($img['src']) ?>" alt="<?= e(t('photo_post') . ' ' . ($i + 1)) ?>" loading="lazy" decoding="async"></a>
        <?php endforeach; ?>
      </div>
    <?php elseif ($v['stream']): ?>
      <video class="dl-video" src="<?= e($v['stream']) ?>" <?= $v['cover'] ? 'poster="' . e($v['cover']) . '"' : '' ?> controls playsinline preload="none"></video>
    <?php elseif ($v['cover']): ?>
      <img class="dl-video" src="<?= e($v['cover']) ?>" alt="" loading="lazy">
    <?php else: ?>
      <div class="dl-video cover-fallback"><?= icon('video') ?></div>
    <?php endif; ?>
  </div>
  <div class="dl-info">
    <?php if ($v['author']['uniqueId'] !== ''): ?>
    <a class="author" href="<?= e(url('/user/' . strtolower($v['author']['uniqueId']))) ?>">
      <?php if ($v['author']['avatar']): ?><img src="<?= e($v['author']['avatar']) ?>" alt="" width="40" height="40" loading="lazy"><?php else: ?><span class="avatar-sm"><?= icon('user') ?></span><?php endif; ?>
      <span><strong><bdi><?= e($v['author']['nickname'] ?: $v['author']['uniqueId']) ?></bdi></strong><br><span class="muted small" dir="ltr">@<?= e($v['author']['uniqueId']) ?></span></span>
    </a>
    <?php endif; ?>
    <p class="dl-desc"><?= $v['desc'] !== '' ? e($v['desc']) : '' ?></p>
    <ul class="mini-stats" role="list">
      <?php foreach (['plays' => ['eye', 'views'], 'likes' => ['heart', 'likes'], 'comments' => ['comment', 'comments'], 'shares' => ['share', 'shares']] as $k => [$ic, $lbl]): if ($v[$k] === null) continue; ?>
      <li title="<?= e(t($lbl)) ?>"><?= icon($ic) ?> <?= e(fmt_compact($v[$k])) ?></li>
      <?php endforeach; ?>
      <?php if ($v['created']): ?><li><?= icon('calendar') ?> <time datetime="<?= e(gmdate('c', $v['created'])) ?>"><?= e(fmt_date($v['created'], false)) ?></time></li><?php endif; ?>
    </ul>
    <?php if ($v['musicTitle'] !== ''): ?><p class="muted small"><?= icon('music') ?> <?= e($v['musicTitle']) ?></p><?php endif; ?>
    <div class="dl-buttons">
      <?php foreach ($labels as $k => $lbl): if (empty($v['dl'][$k])) continue; ?>
      <a class="btn <?= $k === 'hd' || ($k === 'nowm' && empty($v['dl']['hd'])) ? 'btn-primary' : 'glass lit' ?>" href="<?= e($v['dl'][$k]) ?>" download><?= icon($k === 'mp3' ? 'music' : 'download') ?> <?= e(t($lbl)) ?></a>
      <?php endforeach; ?>
      <?php if ($v['url']): ?><a class="btn glass lit" href="<?= e($v['url']) ?>" rel="nofollow noopener noreferrer" target="_blank"><?= icon('external') ?> <?= e(t('open_tiktok')) ?></a><?php endif; ?>
    </div>
  </div>
</article>
