<?php /** @var array $faq */ if (!$faq) return; ?>
<section class="section wrap narrow reveal" aria-labelledby="faq-h">
  <h2 id="faq-h" class="section-title"><?= e(t('faq_title')) ?></h2>
  <div class="faq">
    <?php foreach ($faq as $i => [$q, $a]): ?>
    <details class="faq-item glass"<?= $i === 0 ? ' open' : '' ?> name="faq">
      <summary><span><?= e($q) ?></span><?= icon('chevron', 'faq-chev') ?></summary>
      <div class="faq-body"><p><?= e($a) ?></p></div>
    </details>
    <?php endforeach; ?>
  </div>
</section>
