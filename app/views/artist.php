<article>
  <div class="section">
    <div class="wrap">
      <?php /* [V30-TETE-ARTISTE] La fiche artiste se présente maintenant comme
               une fiche projet : le nom de l'artiste est le titre de la page,
               tout en haut et sur toute la largeur, et non plus une ligne
               posée à côté d'un portrait.

               Ce que cela change, en dessous :

                 — la photo est passée en tête de la colonne de gauche, à la
                   largeur exacte des deux colonnes de texte qu'elle surplombe.
                   Elle est affichée au format « cover » (16:9), le même que le
                   bandeau des fiches projet : un carré de 800 px de côté
                   aurait repoussé la présentation d'un écran entier vers le
                   bas. Le cadrage se règle dans l'administration, fiche de
                   l'artiste, sous « Image représentative » — c'est le cadre
                   nommé « cover » qu'il faut ajuster.

                 — la colonne de droite (projets, dates, Instagram, Spotify)
                   commence désormais tout en haut, à la même hauteur que la
                   photo. Auparavant elle démarrait sous la présentation, et
                   la page avait un grand vide à droite avant de commencer. */ ?>
      <header class="fiche-head">
        <h1><?= e($artist['name']) ?></h1>
      </header>

      <?php // [V25-DATES] Les dates sont passées dans la colonne de droite.
            // [V26-SPOTIFY] Le lecteur Spotify aussi.
            // [V28-INSTAGRAM] Et la carte Instagram, [V32-ORDRE-ASIDE] passée
            // depuis devant Spotify.
            //
            // Les vidéos, elles, sont revenues à gauche : la colonne de droite
            // les avait rendues minuscules tout en s'allongeant démesurément,
            // et la page penchait. Ce qui accompagne la biographie de loin —
            // les projets, les dates, le compte, la musique — tient à droite ;
            // ce qui se regarde vraiment reste à gauche, à sa taille.
            //
            // [V31-SITE-ARTISTE] Enfin le site personnel, tout en bas : c'est
            // le lien qui mène hors du site, on le laisse partir en dernier.
            $spotify  = spotify_card((string)($artist['spotify_url'] ?? ''), (string)$artist['name']);
            $insta    = instagram_card((string)($artist['instagram_url'] ?? ''), (string)$artist['name']);
            $site     = site_card((string)($artist['website_url'] ?? ''));
            $hasAside = !empty($projects) || !empty($events) || $spotify !== '' || $insta !== '' || $site !== ''; ?>
      <div class="fiche-grid<?= $hasAside ? '' : ' no-aside' ?>">
        <div class="fiche-main">
          <?php if ($cover): Img::ensure($cover, 'cover'); ?>
          <div class="artist-photo"><?= Img::tag($cover, 'cover', ['alt' => $artist['name']]) ?></div>
          <?php endif; ?>

          <?php if (f($artist, 'intro')): ?>
          <p class="lead artist-lead"><?= nl2br(e(f($artist, 'intro'))) ?></p>
          <?php endif; ?>

          <div class="rich"><?= f($artist, 'body') ?></div>

          <?= gallery_grid($gallery) ?>

          <?php if ($videos): ?>
          <h2 class="sub"><?= e(t('videos')) ?></h2>
          <div class="videos"><?php foreach ($videos as $v) echo video_embed($v); ?></div>
          <?php endif; ?>

          <?php if ($documents): ?>
          <h2 class="sub"><?= e(t('documents')) ?></h2>
          <?= docs_list($documents) ?>
          <?php endif; ?>
        </div>

        <?php if ($hasAside): ?>
        <aside class="fiche-aside">
          <?php if ($projects): ?>
          <nav class="aside-block aside-menu" aria-label="<?= e(t('related_projects')) ?>">
            <h2><?= e(t('related_projects')) ?></h2>
            <ul>
              <?php foreach ($projects as $p): ?>
              <li><a href="<?= e(detail_url('projects', $p)) ?>"><span><?= e(html_entity_decode(f($p, 'title'))) ?></span><em>→</em></a></li>
              <?php endforeach; ?>
            </ul>
          </nav>
          <?php endif; ?>

          <?php // [V25-DATES] Les dates de représentation, sous la liste des projets.
                // Version compacte (pas de vignette, pas de rappel du nom de l'artiste,
                // qui est déjà le titre de la page) : date en gras, projet, puis lieu. ?>
          <?php if ($events): ?>
          <div class="aside-block aside-dates">
            <h2><?= e(t('agenda')) ?></h2>
            <ul>
              <?php foreach ($events as $ev):
                  $place = trim((string)$ev['venue']) . ($ev['city'] !== '' ? ' — ' . $ev['city'] : '');
                  $proj  = trim((string)f($ev, 'project_title')); ?>
              <li>
                <strong class="ad-date"><?= e(f($ev, 'date_text')) ?></strong>
                <?php if ($proj !== ''):
                    $pRow = ['slug_en' => $ev['project_slug_en'] ?? '', 'slug_fr' => $ev['project_slug_fr'] ?? '']; ?>
                <span class="ad-proj"><a href="<?= e(detail_url('projects', $pRow)) ?>"><?= e(html_entity_decode($proj)) ?></a></span>
                <?php endif; ?>
                <?php if ($place !== ''): ?>
                <span class="ad-lieu">
                  <?php if ($ev['venue_url'] !== ''): ?>
                  <a href="<?= e($ev['venue_url']) ?>" target="_blank" rel="noopener"><?= e($place) ?> <?= Ico::ext() ?></a>
                  <?php else: ?>
                  <?= e($place) ?>
                  <?php endif; ?>
                </span>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <?php // [V28-INSTAGRAM] La carte Instagram, puis [V26-SPOTIFY] le
                // lecteur Spotify. Les deux viennent après les dates : les
                // dates changent souvent et restent donc collées à la liste
                // des projets, tandis que le compte et la musique sont la
                // partie permanente de la fiche.
                //
                // [V32-ORDRE-ASIDE] Instagram passe devant Spotify. Tous les
                // artistes ont un compte, peu ont une page Spotify : mettre
                // le rare en premier laissait un blanc en haut de cette
                // partie pour la plupart des fiches. En le mettant après, la
                // colonne commence toujours par quelque chose. ?>
          <?php if ($insta !== ''): ?>
          <div class="aside-block aside-insta">
            <h2>Instagram</h2>
            <?= $insta ?>
          </div>
          <?php endif; ?>

          <?php if ($spotify !== ''): ?>
          <div class="aside-block aside-spotify">
            <h2><?= e(t('listen')) ?></h2>
            <?= $spotify ?>
          </div>
          <?php endif; ?>

          <?php // [V31-SITE-ARTISTE] Le site personnel de l'artiste, en dernier :
                // c'est le lien qui fait quitter le site, il part à la fin. ?>
          <?php if ($site !== ''): ?>
          <div class="aside-block aside-site">
            <h2><?= e(t('site_title')) ?></h2>
            <?= $site ?>
          </div>
          <?php endif; ?>
        </aside>
        <?php endif; ?>
      </div>
    </div>
  </div>
</article>
