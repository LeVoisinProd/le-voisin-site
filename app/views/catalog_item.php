<?php
/* Le Catalogue — la fiche d'un spectacle.   [V42-CATALOGUE]
 *
 * Deux colonnes, et elles ne se ressemblent pas : à gauche on regarde, à
 * droite on emporte. C'est la seule page du site où ces deux gestes coexistent,
 * et les mélanger ferait qu'on ne ferait ni l'un ni l'autre.
 *
 * La colonne de droite est construite depuis le DOSSIER, pas depuis la base :
 * `Catalog::ressources()` lit `medias/{media_slug}/` et rend ce qu'il y trouve,
 * dans l'ordre utile. Déposer un fichier suffit à le faire apparaître ; en
 * retirer un suffit à le faire disparaître. Aucun formulaire, aucune ligne à
 * tenir à jour, et rien à ressaisir quand une fiche technique change.
 *
 * Le dossier peut être absent — c'est l'état de toutes les pièces tant que le
 * compte FTP n'existe pas. La colonne affiche alors une phrase qui dit quoi
 * faire, plutôt que de disparaître en silence : une fiche sans téléchargement
 * ressemble sinon à une fiche cassée.
 */
$slug   = trim((string)($item['media_slug'] ?? ''));
$res    = Catalog::ressources($slug, I18n::$lang);
$tags   = Catalog::tags($item);
$noms   = project_artists_names((int)$item['id']);

/* La vidéo qu'on montre : la captation allégée si elle existe, sinon le
   teaser. Un programmateur venu jusqu'ici veut voir la pièce, pas la bande
   annonce — mais mieux vaut la bande annonce que rien. */
$lecture = null;
foreach ($res as $r) {
    if (!$r['lecture']) continue;
    if ($r['cle'] === 'captation_stream') { $lecture = $r; break; }
    if ($lecture === null && $r['cle'] === 'teaser') $lecture = $r;
}
$poster = is_file(Catalog::dossier($slug) . '/video/poster.jpg')
        ? url('/' . Catalog::RACINE . '/' . $slug . '/video/poster.jpg') : '';

$PUBLICS = ['young' => t('cat_pub_young'), 'all' => t('cat_pub_all'), 'adult' => t('cat_pub_adult')];
$pub   = (string)($item['public_cible'] ?? '');
$duree = (int)($item['duration_min'] ?? 0);
$annee = (int)($item['year_creation'] ?? 0);
?>
<article class="section cat-fiche">
  <div class="wrap">

    <p class="cat-retour"><a href="<?= e(cat_lien()) ?>">&larr; <?= e(t('cat_retour')) ?></a></p>

    <header class="cat-fiche-tete">
      <h1><?= e(f($item, 'title')) ?></h1>
      <?php if ($noms): ?><p class="cat-fiche-artiste"><?= e($noms) ?></p><?php endif; ?>
    </header>

    <div class="cat-fiche-grid">

      <div class="cat-fiche-main">

        <?php if ($lecture): ?>
        <?php /* Lecteur natif, sans librairie et sans cookie : le fichier est
                 servi par Apache, donc les requêtes Range fonctionnent et l'on
                 peut se déplacer dans la vidéo sans rien coder. C'est aussi
                 pour cela que le mur de consentement ne s'affiche pas ici. */ ?>
        <div class="cat-video">
          <?php /* playsinline : sans lui, iOS ouvre la vidéo dans son propre lecteur
                 plein écran dès la lecture, et l'on perd la page autour — un
                 programmateur qui regarde sur iPad veut garder la fiche sous
                 les yeux. Le bouton plein écran, lui, reste à sa disposition. */ ?>
          <video controls playsinline preload="metadata"<?= $poster ? ' poster="' . e($poster) . '"' : '' ?>>
            <source src="<?= e(url('/' . Catalog::RACINE . '/' . $slug . '/video/' . $lecture['nom'])) ?>" type="video/mp4">
          </video>
          <p class="cat-video-lgd"><?= e($lecture['libelle']) ?></p>
        </div>
        <?php endif; ?>

        <?php if (f($item, 'intro')): ?>
        <p class="lead"><?= nl2br(e(f($item, 'intro'))) ?></p>
        <?php endif; ?>
        <?php if (f($item, 'body')): ?>
        <div class="rich"><?= f($item, 'body') ?></div>
        <?php endif; ?>
        <?php if (f($item, 'distribution')): ?>
        <div class="rich cat-distribution">
          <h2 class="sub"><?= e(t('distribution')) ?></h2>
          <?= f($item, 'distribution') ?>
        </div>
        <?php endif; ?>
      </div>

      <aside class="cat-fiche-aside">

        <?php /* Les faits qu'on cherche avant tout le reste, groupés et courts.
                 Ils viennent des champs, jamais du texte libre : c'est ici que
                 se paie la saisie structurée de l'étape 1. */ ?>
        <?php $faits = array_filter([
                $annee > 0 ? [t('cat_annee'), (string)$annee] : null,
                $duree > 0 ? [t('cat_duree'), $duree . ' ' . t('cat_min')] : null,
                $pub !== '' ? [t('cat_public'), ($PUBLICS[$pub] ?? '')] : null,
                f($item, 'capacity') !== '' ? [t('cat_jauge'), f($item, 'capacity')] : null,
              ]); ?>
        <?php if ($faits): ?>
        <div class="cat-bloc cat-faits">
          <dl>
            <?php foreach ($faits as [$k, $v]): ?>
            <dt><?= e($k) ?></dt><dd><?= e($v) ?></dd>
            <?php endforeach; ?>
          </dl>
          <?php if ($tags): ?><p class="cat-fiche-tags"><?= e(implode(' · ', $tags)) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="cat-bloc cat-telech">
          <h2><?= e(t('cat_telecharger')) ?></h2>

          <?php if (!$res): ?>
          <p class="muted"><?= e(t('cat_rien_depose')) ?></p>
          <?php else: ?>
          <ul class="cat-res">
            <?php foreach ($res as $r): ?>
            <li>
              <a href="<?= e(url('/telechargement.php') . '?p=' . (int)$item['id'] . '&f=' . rawurlencode($r['sous'] . '/' . $r['nom'])) ?>">
                <span class="cat-res-nom"><?= e($r['libelle']) ?></span>
                <span class="cat-res-meta"><?= e(strtoupper($r['ext'])) ?> · <?= e(Docs::human($r['taille'])) ?></span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>

        <?php /* Le contact, en dernier et sans titre pompeux : à ce stade la
                 personne sait ce qu'elle a vu, il lui faut à qui écrire. */ ?>
        <div class="cat-bloc cat-contact">
          <p><?= e(t('cat_contact')) ?></p>
          <p><a class="btn" href="mailto:<?= e(setting('contact_email', 'talkto@le-voisin.com')) ?>"><?= e(t('cat_ecrire')) ?></a></p>
        </div>

      </aside>
    </div>
  </div>
</article>
