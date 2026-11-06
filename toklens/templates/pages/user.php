<?php
/** @var array $p @var string $apiToken */
use App\View;

$name = $p['nickname'] !== '' ? $p['nickname'] : '@' . $p['uniqueId'];
$initial = mb_strtoupper(mb_substr($p['nickname'] ?: $p['uniqueId'], 0, 1));
$stats = [
    ['followers', 'stat_followers', 'users'],
    ['following', 'stat_following', 'user'],
    ['likes', 'stat_likes', 'heart'],
    ['videos', 'stat_videos', 'video'],
    ['friends', 'stat_friends', 'users'],
    ['diggs', 'stat_diggs', 'heart'],
];
$ts = static fn(?int $v) => $v ? '<time datetime="' . e(gmdate('c', $v)) . '" data-ts="' . (int) $v . '">' . e(fmt_date($v)) . '</time>' : '<span class="muted">' . e(t('na')) . '</span>';
?>
<div class="wrap">
  <nav class="crumbs small muted" aria-label="Breadcrumb">
    <a href="<?= e(url('/')) ?>"><?= e(t('breadcrumb_home')) ?></a> <span aria-hidden="true">/</span> <span>@<?= e($p['uniqueId']) ?></span>
  </nav>

  <section class="profile glass panel reveal" aria-labelledby="pname">
    <div class="avatar-ring">
      <?php if ($p['avatarSrc']): ?>
      <img class="avatar" src="<?= e($p['avatarSrc']) ?>" alt="<?= e($name) ?>" width="128" height="128" decoding="async" fetchpriority="high" referrerpolicy="no-referrer">
      <?php else: ?>
      <span class="avatar avatar-fallback" aria-hidden="true"><?= e($initial) ?></span>
      <?php endif; ?>
    </div>
    <div class="profile-main">
      <h1 id="pname" class="profile-name"><bdi><?= e($name) ?></bdi><?php if ($p['verified']): ?><svg class="ic-verified" role="img" aria-label="<?= e(t('verified')) ?>"><use href="#i-verified"></use></svg><?php endif; ?></h1>
      <p class="profile-handle" dir="ltr">@<?= e($p['uniqueId']) ?></p>
      <p class="tags">
        <span class="tag glass"><?= icon($p['private'] ? 'lock' : 'globe') ?> <?= e($p['private'] ? t('type_private') : t('type_public')) ?></span>
        <?php if ($p['verified']): ?><span class="tag glass tag-blue"><?= icon('shield') ?> <?= e(t('verified')) ?></span><?php endif; ?>
        <?php if ($p['region']): ?><span class="tag glass"><?= icon('flag') ?> <?= e($p['region']) ?></span><?php endif; ?>
      </p>
      <p class="bio"><?= $p['signature'] !== '' ? nl2br(e($p['signature'])) : '<span class="muted">' . e(t('no_bio')) . '</span>' ?></p>
      <?php if ($p['linkHref']): ?>
      <p><a class="bio-link" href="<?= e($p['linkHref']) ?>" rel="nofollow ugc noopener noreferrer" target="_blank" dir="ltr"><?= icon('link') ?> <?= e($p['linkText']) ?></a></p>
      <?php endif; ?>
      <div class="actions">
        <a class="btn btn-primary" href="<?= e($p['tiktokUrl']) ?>" rel="nofollow noopener noreferrer" target="_blank"><?= icon('external') ?> <?= e(t('open_tiktok')) ?></a>
        <button class="btn glass lit" type="button" data-share data-title="<?= e($name) ?>"><?= icon('share') ?> <?= e(t('share')) ?></button>
      </div>
    </div>
  </section>

  <section class="stats reveal" aria-label="<?= e(t('stat_followers')) ?>">
    <?php foreach ($stats as [$k, $label, $ic]): if ($p[$k] === null) continue; ?>
    <div class="stat glass lit">
      <span class="stat-icon"><?= icon($ic) ?></span>
      <span class="stat-value" title="<?= e(fmt_num($p[$k])) ?>" data-count="<?= (int) $p[$k] ?>"><?= e(fmt_compact($p[$k])) ?></span>
      <span class="stat-label"><?= e(t($label)) ?></span>
      <span class="stat-exact muted small"><?= e(fmt_num($p[$k])) ?></span>
    </div>
    <?php endforeach; ?>
  </section>

  <section class="glass panel reveal" aria-labelledby="info-h">
    <h2 id="info-h" class="panel-title"><?= icon('info') ?> <?= e(t('info_title')) ?></h2>
    <dl class="kv">
      <div><dt><?= icon('hash') ?> <?= e(t('info_id')) ?></dt><dd><code dir="ltr"><?= e($p['id']) ?></code> <button class="mini-btn" type="button" data-copy="<?= e($p['id']) ?>" aria-label="<?= e(t('copy')) ?>"><?= icon('copy') ?></button></dd></div>
      <div><dt><?= icon('calendar') ?> <?= e(t('info_created')) ?></dt><dd><?= $ts($p['created']) ?><?php if ($p['createdFromId']): ?> <span class="hint" title="<?= e(t('created_note')) ?>"><?= icon('info') ?></span><?php endif; ?></dd></div>
      <div><dt><?= icon('clock') ?> <?= e(t('info_age')) ?></dt><dd><?= e(fmt_age($p['created'])) ?></dd></div>
      <div><dt><?= icon('edit') ?> <?= e(t('info_nick_changed')) ?></dt><dd><?= $ts($p['nickModified'] ?: null) ?></dd></div>
      <div><dt><?= icon('edit') ?> <?= e(t('info_user_changed')) ?></dt><dd><?= $ts($p['uniqueModified'] ?: null) ?></dd></div>
      <div><dt><?= icon('flag') ?> <?= e(t('info_region')) ?></dt><dd data-region><?= $p['region'] !== '' ? e($p['region']) : '<span class="muted">' . e(t('na')) . '</span>' ?></dd></div>
      <?php if ($p['language']): ?><div><dt><?= icon('globe') ?> <?= e(t('info_language')) ?></dt><dd><?= e($p['language']) ?></dd></div><?php endif; ?>
      <div><dt><?= icon('lock') ?> <?= e(t('info_type')) ?></dt><dd><?= e($p['private'] ? t('type_private') : t('type_public')) ?></dd></div>
      <?php if ($p['secUid']): ?>
      <div class="kv-wide"><dt><?= icon('hash') ?> <?= e(t('info_secuid')) ?></dt><dd><code class="break" dir="ltr"><?= e($p['secUid']) ?></code> <button class="mini-btn" type="button" data-copy="<?= e($p['secUid']) ?>" aria-label="<?= e(t('copy')) ?>"><?= icon('copy') ?></button></dd></div>
      <?php endif; ?>
    </dl>
    <p class="muted small note"><?= icon('info') ?> <?= e(t('created_note')) ?></p>
  </section>

  <section class="reveal" aria-label="<?= e(t('tab_videos')) ?>">
    <div class="tabs glass" role="tablist">
      <button role="tab" id="tab-videos" aria-controls="panel-videos" aria-selected="true" type="button"><?= icon('video') ?> <?= e(t('tab_videos')) ?></button>
      <button role="tab" id="tab-stories" aria-controls="panel-stories" aria-selected="false" type="button" tabindex="-1"><?= icon('stories') ?> <?= e(t('tab_stories')) ?></button>
    </div>
    <?php if ($p['private']): ?>
    <p class="alert glass"><?= icon('lock') ?> <?= e(t('private_notice')) ?></p>
    <?php else: ?>
    <div id="panel-videos" role="tabpanel" aria-labelledby="tab-videos" class="feed"
         data-feed="videos" data-user="<?= e($p['uniqueId']) ?>" data-token="<?= e($apiToken) ?>"></div>
    <div id="panel-stories" role="tabpanel" aria-labelledby="tab-stories" class="feed" hidden
         data-feed="stories" data-user="<?= e($p['uniqueId']) ?>" data-uid="<?= e($p['id']) ?>" data-token="<?= e($apiToken) ?>"></div>
    <?php endif; ?>
  </section>
</div>

<?php View::partial('tools', ['except' => '/']); ?>
