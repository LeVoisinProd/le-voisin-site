<?php
/* Le Catalogue — la grille.   [V42-CATALOGUE]
 *
 * Maquette validée le 11.08.2026 avec les vingt pièces réelles ; ce qu'elle
 * fixe est écrit au chapitre 14 de l'architecture. L'essentiel tient en une
 * phrase : la carte du Catalogue en dit PLUS que la carte publique, et elle le
 * dit en texte lisible sous l'image plutôt qu'en surimpression.
 *
 * La raison est d'usage. Sur la page publique, une carte doit donner envie :
 * une belle image, un titre par-dessus, rien qui encombre. Ici, un
 * programmateur balaie vingt cartes en cherchant une durée, un public, une
 * langue. S'il doit ouvrir chaque fiche pour les trouver, il en ouvre trois et
 * s'arrête.
 *
 * Les filtres tournent dans le navigateur, sans recharger la page : à trente
 * pièces, un aller-retour au serveur par clic serait une lenteur gratuite.
 * Sans JavaScript, tout reste affiché — on perd le tri, jamais le contenu.
 */
$PUBLICS = [
    'young' => t('cat_pub_young'),
    'all'   => t('cat_pub_all'),
    'adult' => t('cat_pub_adult'),
];
?>
<section class="section cat-tete">
  <div class="wrap">
    <h1><?= e(f($page, 'title')) ?></h1>
    <?php if (f($page, 'body')): ?>
    <div class="rich lead cat-intro"><?= f($page, 'body') ?></div>
    <?php endif; ?>
  </div>
</section>

<?php if (!$spectacles): ?>

  <?php /* Aucun spectacle coché. Ce n'est pas une erreur : c'est l'état du
           premier jour, et la phrase dit quoi faire plutôt que de constater
           un vide. */ ?>
  <section class="section"><div class="wrap">
    <p class="lead"><?= e(t('cat_vide')) ?></p>
  </div></section>

<?php else: ?>

<div class="wrap cat-filtres" id="cat-filtres">
  <?php if ($cats): ?>
  <span class="cat-groupe">
    <b><?= e(t('cat_f_discipline')) ?></b>
    <button class="cat-f on" data-axe="cat" data-val=""><?= e(t('cat_tout')) ?></button>
    <?php foreach ($cats as $c): ?>
    <button class="cat-f" data-axe="cat" data-val="<?= e((string)$c['id']) ?>"><?= e(f($c, 'name')) ?></button>
    <?php endforeach; ?>
  </span>
  <?php endif; ?>

  <?php if ($publics): ?>
  <span class="cat-groupe">
    <b><?= e(t('cat_f_public')) ?></b>
    <?php /* $pb, et non $p : la boucle des cartes plus bas se sert de $p pour
             le spectacle. Deux boucles successives n'entrent pas en conflit,
             mais relire « $p » en pensant à un projet alors qu'il porte un
             public est le genre de confusion qui se paie six mois plus tard. */ ?>
    <?php foreach ($publics as $pb): ?>
    <button class="cat-f" data-axe="pub" data-val="<?= e($pb) ?>"><?= e($PUBLICS[$pb] ?? $pb) ?></button>
    <?php endforeach; ?>
  </span>
  <?php endif; ?>

  <?php if ($tags): ?>
  <span class="cat-groupe">
    <b><?= e(t('cat_f_mots')) ?></b>
    <?php foreach ($tags as $t): ?>
    <button class="cat-f" data-axe="tag" data-val="<?= e(mb_strtolower($t)) ?>"><?= e($t) ?></button>
    <?php endforeach; ?>
  </span>
  <?php endif; ?>
</div>

<div class="wrap cat-grille" id="cat-grille">
  <?php foreach ($spectacles as $p):
      $tagsP  = Catalog::tags($p);
      /* Les mêmes fonctions que la grille publique : Img::row pour la vignette,
         detail_url pour l'adresse, project_artists_names pour la ligne des
         artistes. Rien de propre au Catalogue — une correction faite là-bas
         profite ici, et le jour où la vignette change de format, elle change
         aux deux endroits. */
      $img    = !empty($p['cover_image_id']) ? Img::row((int)$p['cover_image_id']) : null;
      $noms   = project_artists_names((int)$p['id']);
      $duree  = (int)($p['duration_min'] ?? 0);
      $annee  = (int)($p['year_creation'] ?? 0);
      $pub    = (string)($p['public_cible'] ?? '');
      /* La ligne de méta se compose de ce qui existe, et de rien d'autre : une
         fiche sans durée ne doit pas afficher « · 0 min », ni un séparateur
         qui pend dans le vide. */
      $meta   = array_filter([
          $annee > 0 ? (string)$annee : '',
          $duree > 0 ? $duree . ' ' . t('cat_min') : '',
          $pub !== '' ? ($PUBLICS[$pub] ?? '') : '',
      ]);
  ?>
  <a class="cat-card"
     href="<?= e(detail_url('catalog', $p)) ?>"
     data-cat="<?= e(implode(' ', array_map('strval', $p['_cats'] ?? []))) ?>"
     data-pub="<?= e($pub) ?>"
     data-tag="<?= e(mb_strtolower(implode(' ', $tagsP))) ?>">
    <span class="cat-img"><?= $img
        ? Img::tag($img, 'card', ['alt' => f($p, 'title')])
        : '<span class="card-ph" aria-hidden="true">' . e(mb_substr(f($p, 'title'), 0, 1)) . '</span>' ?></span>
    <span class="cat-body">
      <span class="cat-title"><?= e(f($p, 'title')) ?></span>
      <?php if ($noms): ?>
      <span class="cat-artiste"><?= e($noms) ?></span>
      <?php endif; ?>
      <?php if ($meta): ?>
      <span class="cat-meta"><?= e(implode(' · ', $meta)) ?></span>
      <?php endif; ?>
      <?php if ($tagsP): ?>
      <span class="cat-tags"><?= e(implode(' · ', $tagsP)) ?></span>
      <?php endif; ?>
    </span>
  </a>
  <?php endforeach; ?>
</div>

<?php /* Le compte des résultats, écrit par le script quand un filtre tombe à
         zéro. Sans lui, une grille vide ressemble à une page cassée. */ ?>
<div class="wrap"><p class="cat-rien" id="cat-rien" hidden><?= e(t('cat_aucun')) ?></p></div>

<?php endif; ?>
