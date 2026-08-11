<article class="section">
  <div class="wrap">

    <header class="fiche-head">
      <h1><?= e(f($project, 'title')) ?></h1>

      <?php if ($artists): ?>
      <p class="detail-by">
        <?php $links = []; foreach ($artists as $a) $links[] = '<a href="' . e(detail_url('artists', $a)) . '">' . e($a['name']) . '</a>'; echo implode(', ', $links); ?>
      </p>
      <?php endif; ?>

      <?php if (f($project, 'intro')): ?>
      <p class="lead fiche-sub"><?= nl2br(e(f($project, 'intro'))) ?></p>
      <?php endif; ?>

      <?php if ($cats): ?>
      <p class="chips small">
        <?php $mp = Pages::moduleP('projects'); foreach ($cats as $c): $cs = f($c, 'slug') ?: $c['slug_en']; ?>
        <a class="chip" href="<?= e($mp ? Pages::url($mp) . '?cat=' . urlencode($cs) : '#') ?>"><?= e(f($c, 'name')) ?></a>
        <?php endforeach; ?>
      </p>
      <?php endif; ?>

      <?php if ($events): ?>
      <ul class="dates-top">
        <?php foreach ($events as $ev):
            $place = trim((string)$ev['venue']) . ($ev['city'] !== '' ? ' — ' . $ev['city'] : ''); ?>
        <li>
          <strong><?= e(f($ev, 'date_text')) ?></strong>
          <?php if ($place !== ''): ?>
          <?php if ($ev['venue_url'] !== ''): ?>
          <a href="<?= e($ev['venue_url']) ?>" target="_blank" rel="noopener"><?= e($place) ?> <?= Ico::ext() ?></a>
          <?php else: ?>
          <span><?= e($place) ?></span>
          <?php endif; ?>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </header>

    <div class="fiche-media"><?= media_carousel($gallery, $cover) ?></div>

    <?php
    // [V31-DEUXPOINTS] Le tri des lignes sans réponse d'abord — il se repère
    // aux deux points —, le retrait des deux points ensuite. Jamais l'inverse.
    $distribution = sans_deux_points(f($project, 'distribution'));
    $infos = sans_deux_points(infos_pratiques(f($project, 'infos')));
    // [V31-PRESSE] La revue de presse compte elle aussi : une fiche qui n'a
    // qu'un article de presse doit ouvrir la colonne de droite pour lui.
    $hasAside = trim(strip_tags($distribution)) !== '' || trim(strip_tags($infos)) !== ''
                || !empty($press) || $documents;

    // Bios des artistes liés (extrait, avec lien vers la fiche complète)
    $bios = [];
    foreach ($artists as $a) {
        $bioHtml = f($a, 'body');
        if (trim(strip_tags($bioHtml)) === '') continue;
        [$excerpt, $truncated] = bio_excerpt($bioHtml, 2);
        $bios[] = ['artist' => $a, 'html' => $excerpt];
    }
    ?>
    <div class="fiche-grid<?= $hasAside ? '' : ' no-aside' ?>">
      <div class="fiche-main">
        <div class="rich"><?= f($project, 'body') ?></div>

        <?php if ($bios): ?>
        <h2 class="sub"><?= e(t('bio')) ?></h2>
        <?php foreach ($bios as $b): ?>
        <div class="bio-block">
          <?php if (count($bios) > 1): ?><h3 class="bio-name"><?= e($b['artist']['name']) ?></h3><?php endif; ?>
          <div class="rich"><?= $b['html'] ?></div>
          <p class="bio-more"><a href="<?= e(detail_url('artists', $b['artist'])) ?>"><?= e(t('bio_more')) ?> →</a></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($videos): ?>
        <h2 class="sub"><?= e(t('videos')) ?></h2>
        <div class="videos"><?php foreach ($videos as $v) echo video_embed($v); ?></div>
        <?php endif; ?>
      </div>

      <?php if ($hasAside): ?>
      <aside class="fiche-aside">
        <?php if (trim(strip_tags($distribution)) !== ''): ?>
        <div class="aside-block">
          <h2><?= e(t('distribution')) ?></h2>
          <div class="rich"><?= $distribution ?></div>
        </div>
        <?php endif; ?>
        <?php if (trim(strip_tags($infos)) !== ''): ?>
        <div class="aside-block">
          <h2><?= e(t('infos_pratiques')) ?></h2>
          <div class="rich"><?= $infos ?></div>
        </div>
        <?php endif; ?>
        <?php /* [V34-ORDRE-PRESSE] La presse se lit entre les infos pratiques
                 et les documents. L'ordre de la colonne suit celui de la
                 lecture : qui fait la fiche, ce qu'elle dure, ce qu'on en a
                 dit, et enfin ce qu'on emporte. Ce bloc était au-dessus des
                 infos pratiques, il est descendu d'un cran. La même liste en
                 lignes que les documents — titre, puis le nom du journal
                 quand le titre est laissé vide, et une flèche dessinée
                 quand l'article est ailleurs. */ ?>
        <?php if (!empty($press)): ?>
        <div class="aside-block aside-presse">
          <h2><?= e(t('presse')) ?></h2>
          <?= docs_list($press, true) ?>
        </div>
        <?php endif; ?>
        <?php if ($documents): ?>
        <div class="aside-block">
          <h2><?= e(t('documents')) ?></h2>
          <?= docs_list($documents, true) /* [V31-DOCS-LISTE] en lignes */ ?>
        </div>
        <?php endif; ?>
      </aside>
      <?php endif; ?>
    </div>

  </div>
</article>
