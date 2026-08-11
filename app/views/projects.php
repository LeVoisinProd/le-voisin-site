<section class="section">
  <div class="wrap">
    <h1><?= e($former ? t('projects_former') : f($page, 'title')) ?></h1>
    <?php if (!$former && f($page, 'body')): ?><div class="rich lead"><?= f($page, 'body') ?></div><?php endif; ?>

    <?php if (!$former && $cats): ?>
    <nav class="chips" aria-label="Categories">
      <a class="chip<?= $activeCat === '' ? ' on' : '' ?>" href="<?= e(Pages::url($page)) ?>"><?= e(t('all')) ?></a>
      <?php foreach ($cats as $c): $cs = f($c, 'slug') ?: $c['slug_en']; ?>
      <a class="chip<?= $activeCat === $cs ? ' on' : '' ?>" href="<?= e(Pages::url($page)) ?>?cat=<?= e($cs) ?>"><?= e(f($c, 'name')) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($projects): ?>
    <div class="grid cards">
      <?php foreach ($projects as $p) echo card('projects', $p, html_entity_decode(f($p, 'title')), project_artists_names((int)$p['id'])); ?>
    </div>
    <?php else: ?>
    <p class="muted"><?= e(t('no_events')) ?></p>
    <?php endif; ?>

    <div class="artists-switch">
      <?php if ($former): ?>
        <a href="<?= e(Pages::url($page)) ?>">&larr; <?= e(t('projects_back')) ?></a>
      <?php elseif ($hasFormer): ?>
        <a href="<?= e(Pages::url($page)) ?>?anciens=1"><?= e(t('projects_former')) ?> &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
</section>
