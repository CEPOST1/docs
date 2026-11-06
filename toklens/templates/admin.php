<?php
/** Admin panel. Every value is escaped with e(). @var string $view */
use App\Admin;
use App\Security;

$nonce = Security::nonce();
$tabs = ['overview' => ['نظرة عامة', 'sparkles'], 'live' => ['الزوار الآن', 'users'], 'searches' => ['عمليات البحث', 'search'], 'security' => ['الحماية', 'shield'], 'db' => ['قاعدة البيانات', 'hash']];
$csrf = $view === 'login' ? '' : Admin::csrf();
$fmt = static fn($n) => number_format((float) $n);
$devName = ['mobile' => 'جوال', 'desktop' => 'كمبيوتر', 'tablet' => 'تابلت', 'bot' => 'بوت', 'crawler' => 'محرك بحث', 'unknown' => 'غير معروف'];
$kindName = ['profile' => 'حساب', 'video' => 'فيديو', 'stories' => 'ستوري', 'age' => 'عمر حساب'];
?>
<!doctype html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?php if ($view === 'live'): ?><meta http-equiv="refresh" content="30"><?php endif; ?>
<title>لوحة التحكم — <?= e(site_name()) ?></title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script nonce="<?= e($nonce) ?>">(function(){try{var t=localStorage.getItem('theme');if(t)document.documentElement.dataset.theme=t;}catch(e){}})();</script>
</head>
<body class="adm">
<?php require ROOT . '/templates/partials/icons.php'; ?>
<div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b3"></span></div>

<?php if ($view === 'login'): ?>
<main class="main"><section class="hero wrap">
  <form class="adm-login glass panel" method="post" action="<?= e(Admin::url()) ?>" autocomplete="off">
    <svg class="guard-logo" aria-hidden="true"><use href="#i-logo"></use></svg>
    <h1 class="guard-title">لوحة التحكم</h1>
    <?php if (!empty($error)): ?><p class="alert alert-error"><?= icon('info') ?> <?= e($error) ?></p><?php endif; ?>
    <input type="hidden" name="action" value="login">
    <input type="hidden" name="csrf" value="<?= e(Admin::loginCsrf()) ?>">
    <label class="adm-label" for="pw">كلمة المرور</label>
    <input class="adm-input" id="pw" name="password" type="password" required minlength="12" autocomplete="current-password" dir="ltr">
    <button class="btn btn-primary" type="submit" data-once><?= icon('lock') ?> دخول</button>
  </form>
</section></main>
<?php else: ?>

<header class="nav-wrap">
  <nav class="nav glass adm-nav">
    <span class="brand"><svg class="brand-logo" aria-hidden="true"><use href="#i-logo"></use></svg><span class="brand-name">لوحة التحكم</span></span>
    <ul class="nav-links adm-tabs" role="list">
      <?php foreach ($tabs as $k => [$label, $ic]): ?>
      <li><a href="<?= e(Admin::url($k)) ?>"<?= $view === $k ? ' aria-current="page"' : '' ?>><?= icon($ic) ?> <?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(Admin::url()) ?>" class="nav-actions">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout">
      <button class="icon-btn glass" type="submit" title="خروج"><?= icon('x') ?></button>
    </form>
  </nav>
</header>
<main class="main wrap adm-main">
<?php if (($_GET['err'] ?? '') === 'csrf'): ?>
  <p class="alert alert-error"><?= icon('info') ?> انتهت صلاحية الصفحة. أعد تنفيذ العملية من هذه الصفحة.</p>
<?php endif; ?>

<?php if ($view === 'overview'): ?>
  <section class="adm-cards">
    <?php foreach ($cards as [$label, $value, $ic]): ?>
    <div class="stat glass"><span class="stat-icon"><?= icon($ic) ?></span><span class="stat-value"><?= e($fmt($value)) ?></span><span class="stat-label"><?= e($label) ?></span></div>
    <?php endforeach; ?>
  </section>

  <section class="glass panel">
    <h2 class="panel-title"><?= icon('calendar') ?> آخر 14 يوماً <span class="adm-legend"><i class="lg-a"></i> زوار <i class="lg-b"></i> مشاهدات</span></h2>
    <?php $max = max(1, max(array_column($days, 'views'))); $w = 700; $h = 180; $bw = $w / count($days); ?>
    <svg class="adm-chart" viewBox="0 0 <?= $w ?> <?= $h + 24 ?>" role="img" aria-label="الزيارات">
      <?php foreach ($days as $i => $d): $x = $i * $bw; $hv = round($d['views'] / $max * $h); $hu = round($d['visitors'] / $max * $h); ?>
      <rect class="bar-b" x="<?= $x + 6 ?>" y="<?= $h - $hv ?>" width="<?= $bw - 12 ?>" height="<?= $hv ?>" rx="6"><title><?= e($d['label'] . ': ' . $d['views'] . ' مشاهدة') ?></title></rect>
      <rect class="bar-a" x="<?= $x + 12 ?>" y="<?= $h - $hu ?>" width="<?= $bw - 24 ?>" height="<?= $hu ?>" rx="5"><title><?= e($d['label'] . ': ' . $d['visitors'] . ' زائر') ?></title></rect>
      <text x="<?= $x + $bw / 2 ?>" y="<?= $h + 18 ?>" text-anchor="middle"><?= e($d['label']) ?></text>
      <?php endforeach; ?>
    </svg>
  </section>

  <div class="adm-grid">
    <section class="glass panel">
      <h2 class="panel-title"><?= icon('globe') ?> أكثر الدول (30 يوماً)</h2>
      <?php $top = max(1, (int) ($countries[0]['visitors'] ?? 1)); ?>
      <ul class="adm-bars" role="list">
        <?php foreach ($countries as $c): ?>
        <li><span><?= e(Admin::flag($c['cc'])) ?> <?= e(Admin::countryName($c['cc'])) ?></span><b><?= e($fmt($c['visitors'])) ?></b>
          <svg class="adm-meter" viewBox="0 0 100 6" preserveAspectRatio="none"><rect width="100" height="6" rx="3" class="m-bg"/><rect width="<?= round($c['visitors'] / $top * 100) ?>" height="6" rx="3" class="m-fg"/></svg></li>
        <?php endforeach; ?>
        <?php if (!$countries): ?><li class="muted">لا توجد بيانات بعد.</li><?php endif; ?>
      </ul>
    </section>
    <section class="glass panel">
      <h2 class="panel-title"><?= icon('search') ?> الأكثر بحثاً (7 أيام)</h2>
      <table class="adm-table"><tbody>
        <?php foreach ($topSearches as $r): ?><tr><td><span class="tag glass"><?= e($kindName[$r['kind']] ?? $r['kind']) ?></span></td><td dir="ltr" class="break"><?= e($r['query']) ?></td><td><b><?= e($fmt($r['n'])) ?></b></td></tr><?php endforeach; ?>
      </tbody></table>
    </section>
    <section class="glass panel">
      <h2 class="panel-title"><?= icon('book') ?> أكثر الصفحات (7 أيام)</h2>
      <table class="adm-table"><tbody><?php foreach ($pages as $r): ?><tr><td dir="ltr" class="break"><?= e($r['p']) ?></td><td><b><?= e($fmt($r['n'])) ?></b></td></tr><?php endforeach; ?></tbody></table>
    </section>
    <section class="glass panel">
      <h2 class="panel-title"><?= icon('user') ?> الأجهزة واللغات والمصادر</h2>
      <table class="adm-table"><tbody>
        <?php foreach ($devices as $r): ?><tr><td><?= e($devName[$r['device']] ?? $r['device']) ?></td><td><b><?= e($fmt($r['n'])) ?></b></td></tr><?php endforeach; ?>
        <?php foreach ($langs as $r): ?><tr><td>لغة: <?= e($r['lang']) ?></td><td><b><?= e($fmt($r['n'])) ?></b></td></tr><?php endforeach; ?>
        <?php foreach ($refs as $r): ?><tr><td dir="ltr">↩ <?= e($r['ref']) ?></td><td><b><?= e($fmt($r['n'])) ?></b></td></tr><?php endforeach; ?>
      </tbody></table>
    </section>
  </div>

<?php elseif ($view === 'live'): ?>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('users') ?> متصلون الآن (آخر 5 دقائق): <?= count($online) ?> <span class="muted small">— تتحدّث كل 30 ثانية</span></h2>
    <?php $rows = $online; require ROOT . '/templates/partials/admin-sessions.php'; ?>
  </section>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('globe') ?> الدول (24 ساعة)</h2>
    <p class="adm-chips"><?php foreach ($countries as $c): ?><span class="tag glass"><?= e(Admin::flag($c['cc'])) ?> <?= e(Admin::countryName($c['cc'])) ?> · <?= e($fmt($c['visitors'])) ?></span><?php endforeach; ?></p>
  </section>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('clock') ?> آخر 100 جلسة</h2>
    <?php $rows = $recent; require ROOT . '/templates/partials/admin-sessions.php'; ?>
  </section>

<?php elseif ($view === 'searches'): ?>
  <section class="glass panel">
    <form method="get" action="<?= e(Admin::url()) ?>" class="adm-filter">
      <input type="hidden" name="v" value="searches">
      <input class="adm-input" name="q" value="<?= e($q) ?>" placeholder="ابحث باسم مستخدم أو IP" dir="ltr">
      <button class="btn glass" type="submit"><?= icon('search') ?> بحث</button>
    </form>
    <div class="adm-scroll"><table class="adm-table">
      <thead><tr><th>الوقت</th><th>النوع</th><th>البحث</th><th>نجح</th><th>الدولة</th><th>IP</th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?><tr>
        <td><?= e(Admin::ago((int) $r['ts'])) ?></td><td><?= e($kindName[$r['kind']] ?? $r['kind']) ?></td>
        <td dir="ltr" class="break"><?= e($r['query']) ?></td><td><?= $r['ok'] ? '✅' : '❌' ?></td>
        <td><?= e(Admin::flag($r['country'])) ?></td><td dir="ltr"><code><?= e($r['ip']) ?></code></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </section>

<?php elseif ($view === 'security'): ?>
  <div class="adm-grid">
    <section class="glass panel">
      <h2 class="panel-title"><?= icon('lock') ?> حظر IP يدوياً</h2>
      <form method="post" action="<?= e(Admin::url()) ?>" class="adm-form">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="ban">
        <input class="adm-input" name="ip" placeholder="IP مثل 1.2.3.4" required dir="ltr">
        <input class="adm-input" name="reason" placeholder="السبب (اختياري)">
        <select class="adm-input" name="hours"><option value="1">ساعة</option><option value="24" selected>يوم</option><option value="168">أسبوع</option><option value="720">شهر</option><option value="8760">سنة</option></select>
        <button class="btn btn-primary" type="submit">حظر</button>
      </form>
    </section>
    <section class="glass panel">
      <h2 class="panel-title"><?= icon('shield') ?> أسباب الحظر (24 ساعة)</h2>
      <table class="adm-table"><tbody><?php foreach ($reasons as $r): ?><tr><td dir="ltr"><?= e($r['reason']) ?></td><td><b><?= e($fmt($r['n'])) ?></b></td></tr><?php endforeach; ?></tbody></table>
    </section>
  </div>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('lock') ?> IP محظورة حالياً (<?= count($bans) ?>)</h2>
    <div class="adm-scroll"><table class="adm-table">
      <thead><tr><th>IP</th><th>السبب</th><th>منذ</th><th>حتى</th><th></th></tr></thead>
      <tbody><?php foreach ($bans as $b): ?><tr>
        <td dir="ltr"><code><?= e($b['ip']) ?></code></td><td><?= e($b['reason']) ?></td><td><?= e(Admin::ago((int) $b['ts'])) ?></td><td dir="ltr"><?= e(gmdate('Y-m-d H:i', (int) $b['until'])) ?></td>
        <td><form method="post" action="<?= e(Admin::url()) ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="unban"><input type="hidden" name="ip" value="<?= e($b['ip']) ?>"><button class="btn glass mini" type="submit">إلغاء الحظر</button></form></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('users') ?> أكثر IP مخالفة (24 ساعة)</h2>
    <div class="adm-scroll"><table class="adm-table">
      <thead><tr><th>IP</th><th>عدد</th><th>آخر سبب</th><th></th></tr></thead>
      <tbody><?php foreach ($topIps as $r): ?><tr>
        <td dir="ltr"><code><?= e($r['ip']) ?></code></td><td><b><?= e($fmt($r['n'])) ?></b></td><td dir="ltr"><?= e($r['reason']) ?></td>
        <td><form method="post" action="<?= e(Admin::url()) ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="ban"><input type="hidden" name="ip" value="<?= e($r['ip']) ?>"><input type="hidden" name="hours" value="168"><button class="btn glass mini" type="submit">حظر أسبوع</button></form></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('info') ?> آخر 200 محاولة محظورة</h2>
    <div class="adm-scroll"><table class="adm-table">
      <thead><tr><th>الوقت</th><th>IP</th><th>السبب</th><th>المسار</th><th>المتصفح</th></tr></thead>
      <tbody><?php foreach ($blocks as $b): ?><tr>
        <td><?= e(Admin::ago((int) $b['ts'])) ?></td><td dir="ltr"><code><?= e($b['ip']) ?></code></td><td dir="ltr"><?= e($b['reason']) ?></td>
        <td dir="ltr" class="break"><?= e($b['path']) ?></td><td dir="ltr" class="break small muted"><?= e(mb_substr((string) $b['ua'], 0, 80)) ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </section>

<?php elseif ($view === 'db'): ?>
  <section class="glass panel">
    <h2 class="panel-title"><?= icon('hash') ?> قاعدة البيانات <span class="muted small">(SQLite · <?= e(number_format($size / 1024, 1)) ?> KB)</span></h2>
    <p class="adm-chips">
      <?php foreach ($tables as $name => $count): ?>
      <a class="tag glass<?= $t === $name ? ' is-on' : '' ?>" href="<?= e(Admin::url('db', ['t' => $name])) ?>"><?= e($name) ?> · <?= e($fmt($count)) ?></a>
      <?php endforeach; ?>
    </p>
    <form method="post" action="<?= e(Admin::url()) ?>"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="purge">
      <button class="btn glass mini" type="submit">حذف البيانات الأقدم من مدة الاحتفاظ</button></form>
  </section>
  <?php if ($t !== ''): ?>
  <section class="glass panel">
    <form method="get" action="<?= e(Admin::url()) ?>" class="adm-filter">
      <input type="hidden" name="v" value="db"><input type="hidden" name="t" value="<?= e($t) ?>">
      <input class="adm-input" name="q" value="<?= e($q) ?>" placeholder="فلترة حسب IP" dir="ltr">
      <button class="btn glass" type="submit"><?= icon('search') ?></button>
      <a class="btn btn-primary" href="<?= e(Admin::url('db', ['t' => $t, 'csv' => 1])) ?>"><?= icon('download') ?> تصدير CSV</a>
    </form>
    <div class="adm-scroll"><table class="adm-table adm-raw">
      <?php if ($rows): ?>
      <thead><tr><?php foreach (array_keys($rows[0]) as $col): ?><th dir="ltr"><?= e($col) ?></th><?php endforeach; ?></tr></thead>
      <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $col => $val): ?>
        <td dir="ltr" class="break"><?= e(in_array($col, ['ts', 'first_ts', 'last_ts', 'verified_ts', 'until', 'created', 'expires'], true) && is_numeric($val) && $val > 0 ? gmdate('Y-m-d H:i', (int) $val) : mb_substr((string) $val, 0, 160)) ?></td>
      <?php endforeach; ?></tr><?php endforeach; ?></tbody>
      <?php else: ?><tbody><tr><td class="muted">لا توجد صفوف.</td></tr></tbody><?php endif; ?>
    </table></div>
    <p class="actions">
      <?php if ($page > 1): ?><a class="btn glass" href="<?= e(Admin::url('db', ['t' => $t, 'p' => $page - 1, 'q' => $q])) ?>">→ السابق</a><?php endif; ?>
      <?php if (count($rows) === 50): ?><a class="btn glass" href="<?= e(Admin::url('db', ['t' => $t, 'p' => $page + 1, 'q' => $q])) ?>">التالي ←</a><?php endif; ?>
    </p>
  </section>
  <?php endif; ?>
<?php endif; ?>
</main>
<?php endif; ?>
<script nonce="<?= e($nonce) ?>">document.addEventListener('click',function(e){var b=e.target.closest('[data-confirm]');if(b&&!confirm(b.dataset.confirm))e.preventDefault();});
document.addEventListener('submit',function(e){var b=e.target.querySelector('[data-once]');if(b){if(b.dataset.sent){e.preventDefault();}b.dataset.sent='1';}});</script>
</body>
</html>
