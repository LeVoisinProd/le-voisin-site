<section class="section">
  <div class="wrap">
    <h1><?= e($archives ? t('agenda_archive') : f($page, 'title')) ?></h1>
    <?php if (!$archives && f($page, 'body')): ?><div class="rich lead"><?= f($page, 'body') ?></div><?php endif; ?>

    <form class="agenda-filters" method="get" action="<?= e(Pages::url($page)) ?>">
      <?php if ($archives): ?><input type="hidden" name="archives" value="1"><?php endif; ?>
      <label>
        <span><?= e(t('filter_artist')) ?></span>
        <select name="artist">
          <option value=""><?= e(t('all')) ?></option>
          <?php foreach ($artistsF as $a): ?>
          <option value="<?= (int)$a['id'] ?>"<?= $fArtist === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        <span><?= e(t('filter_project')) ?></span>
        <select name="project">
          <option value=""><?= e(t('all')) ?></option>
          <?php foreach ($projectsF as $p): ?>
          <option value="<?= (int)$p['id'] ?>"<?= $fProject === (int)$p['id'] ? ' selected' : '' ?>><?= e(html_entity_decode(f($p, 'title'))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit"><?= e(t('filter_apply')) ?></button>
    </form>

    <?php if ($archives): ?>
      <?php
      // [V31-ARCHIVES] L'historique se lit par année. Les dates arrivent déjà
      // triées de la plus récente à la plus ancienne : les regrouper conserve
      // cet ordre, sans second tri.
      $parAnnée = [];
      foreach ($past as $ev) $parAnnée[substr((string)$ev['date_sort'], 0, 4)][] = $ev;
      ?>
      <?php if ($parAnnée): ?>
        <?php foreach ($parAnnée as $année => $dates): ?>
        <h2 class="sub"><?= e($année) ?></h2>
        <div class="events-grid past"><?php foreach ($dates as $ev) echo event_card($ev); ?></div>
        <?php endforeach; ?>
      <?php else: ?>
      <p class="muted"><?= e(t('no_events')) ?></p>
      <?php endif; ?>

      <div class="artists-switch">
        <a href="<?= e(Pages::url($page)) ?>">&larr; <?= e(t('agenda_back')) ?></a>
      </div>

    <?php else: ?>
      <h2 class="sub"><?= e(t('upcoming')) ?></h2>
      <?php if ($upcoming): ?>
      <div class="events-grid"><?php foreach ($upcoming as $ev) echo event_card($ev); ?></div>
      <?php else: ?>
      <p class="muted"><?= e(t('no_events')) ?></p>
      <?php endif; ?>

      <?php if ($past): ?>
      <h2 class="sub"><?= e(t('past')) ?></h2>
      <div class="events-grid past"><?php foreach ($past as $ev) echo event_card($ev); ?></div>
      <?php endif; ?>

      <?php if ($hasArchives): ?>
      <div class="artists-switch">
        <a href="<?= e(Pages::url($page)) ?>?archives=1"><?= e(t('agenda_archive')) ?> &rarr;</a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
