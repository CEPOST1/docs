<?php
/** @var array $rows sessions rows */
use App\Admin;

$devName = ['mobile' => '📱', 'desktop' => '💻', 'tablet' => '📲', 'bot' => '🤖', 'crawler' => '🔎', 'unknown' => '❔'];
?>
<div class="adm-scroll"><table class="adm-table">
  <thead><tr><th>آخر نشاط</th><th>الدولة</th><th>IP</th><th>الجهاز</th><th>الصفحة الحالية</th><th>صفحات</th><th>بحث</th><th>تحقّق</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $s): ?>
  <tr>
    <td><?= e(Admin::ago((int) $s['last_ts'])) ?></td>
    <td title="<?= e(Admin::countryName($s['country'])) ?>"><?= e(Admin::flag($s['country'])) ?> <?= e(Admin::countryName($s['country'])) ?></td>
    <td dir="ltr"><code><?= e($s['ip']) ?></code></td>
    <td dir="ltr"><?= e($devName[$s['device']] ?? '❔') ?> <?= e($s['agent']) ?></td>
    <td dir="ltr" class="break"><?= e(mb_substr((string) $s['last_path'], 0, 70)) ?></td>
    <td><?= (int) $s['hits'] ?></td>
    <td><?= (int) $s['lookups'] ?></td>
    <td><?= (int) $s['verified_ts'] > 0 ? '✅' : '—' ?></td>
    <td>
      <form method="post" action="<?= e(Admin::url()) ?>">
        <input type="hidden" name="csrf" value="<?= e(Admin::csrf()) ?>"><input type="hidden" name="action" value="ban">
        <input type="hidden" name="ip" value="<?= e($s['ip']) ?>"><input type="hidden" name="hours" value="24">
        <button class="btn glass mini" type="submit" data-confirm="حظر هذا الـ IP لمدة يوم؟">حظر</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="muted">لا يوجد أحد حالياً.</td></tr><?php endif; ?>
  </tbody>
</table></div>
