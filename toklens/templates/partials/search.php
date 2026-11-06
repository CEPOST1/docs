<?php
/**
 * @var string $action  form action URL
 * @var string $name    query parameter name
 * @var string $placeholder
 * @var string $button
 * @var string $value
 * @var string $icon
 * @var string $inputmode
 */
$id = 'q-' . $name;
?>
<form class="search glass glass-refract lit" action="<?= e($action) ?>" method="get" role="search" data-search>
  <label class="sr-only" for="<?= e($id) ?>"><?= e($placeholder) ?></label>
  <span class="search-icon"><?= icon($icon ?? 'search') ?></span>
  <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= ($inputmode ?? '') === 'url' ? 'url' : 'text' ?>" value="<?= e($value ?? '') ?>"
         placeholder="<?= e($placeholder) ?>" autocomplete="off" autocapitalize="off" spellcheck="false" dir="ltr"
         maxlength="300" required<?= ($inputmode ?? '') ? ' inputmode="' . e($inputmode) . '"' : '' ?> enterkeyhint="search">
  <button class="btn btn-primary" type="submit"><span class="btn-label"><?= e($button) ?></span><?= icon('arrow', 'flip-rtl') ?></button>
</form>
