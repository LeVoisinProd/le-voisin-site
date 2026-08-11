<section class="section">
  <div class="wrap">
    <h1><?= e($former ? t('artists_former') : f($page, 'title')) ?></h1>
    <?php if (!$former && f($page, 'body')): ?><div class="rich lead"><?= f($page, 'body') ?></div><?php endif; ?>

    <?php if ($artists): ?>
    <div class="events-grid artists-grid">
      <?php foreach ($artists as $a):
          $img = !empty($a['cover_image_id']) ? Img::row((int)$a['cover_image_id']) : null;
          if ($img) Img::ensure($img, 'card');
      ?>
      <a class="ecard acard" href="<?= e(detail_url('artists', $a)) ?>">
        <div class="ecard-media"><?= $img ? Img::tag($img, 'card', ['alt' => $a['name']]) : '' ?></div>
        <div class="ecard-overlay">
          <h2 class="ecard-title"><?= e($a['name']) ?></h2>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="muted"><?= e($former ? t('no_events') : t('no_events')) ?></p>
    <?php endif; ?>

    <div class="artists-switch">
      <?php if ($former): ?>
        <a href="<?= e(Pages::url($page)) ?>">← <?= e(t('artists_back')) ?></a>
      <?php elseif ($hasFormer): ?>
        <a href="<?= e(Pages::url($page)) ?>?anciennes=1"><?= e(t('artists_former')) ?> →</a>
      <?php endif; ?>
    </div>
  </div>
</section>
