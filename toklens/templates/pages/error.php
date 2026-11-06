<?php /** @var string $title @var string $message @var int $status @var ?string $username */ ?>
<section class="hero hero-sm wrap">
  <p class="error-code" aria-hidden="true"><?= (int) $status ?></p>
  <h1 class="hero-title"><?= e($title) ?></h1>
  <p class="hero-lead"><?= e($message) ?></p>
  <?php App\View::partial('search', ['action' => url('/lookup'), 'name' => 'q', 'placeholder' => t('search_placeholder'), 'button' => t('search_btn'), 'value' => $username ?? '', 'icon' => 'search']); ?>
  <p class="actions center">
    <?php if ($status === 503 || $status === 429): ?>
    <a class="btn glass lit" href="" data-reload><?= icon('zap') ?> <?= e(t('retry')) ?></a>
    <?php endif; ?>
    <a class="btn glass lit" href="<?= e(url('/')) ?>"><?= icon('arrow', 'flip-rtl') ?> <?= e(t('back_home')) ?></a>
  </p>
</section>
