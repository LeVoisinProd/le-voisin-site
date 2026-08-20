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
        ? url('/telechargement.php') . '?p=' . (int)$item['id'] . '&f=video/poster.jpg' : '';

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
            <?php /* [13.08.2026] Par le portier, et non par l'adresse directe.
                     Celle-ci était servie par Apache : la captation intégrale
                     s'ouvrait sans mot de passe, le contraire de ce que le
                     Catalogue existe pour faire. Le portier répond aux requêtes
                     partielles, donc la barre de temps continue de marcher. */ ?>
            <source src="<?= e(url('/telechargement.php') . '?p=' . (int)$item['id'] . '&f=video/' . rawurlencode($lecture['nom'])) ?>" type="video/mp4">
          </video>
          <p class="cat-video-lgd"><?= e($lecture['libelle']) ?></p>
        </div>
        <?php endif; ?>

        <?php /* ── L'APPEL À PROGRAMMER, JUSTE SOUS LA CAPTATION ──────────────
             [16.08.2026] Demandé par Anna. Le formulaire `/demande.php`
             existait et RIEN N'Y MENAIT depuis le site: il fallait connaître
             l'adresse. Un pipeline dont la seule porte d'entrée est une adresse
             que personne n'a reste vide, et c'est exactement ce qu'il était.

             ICI ET NON SUR LA PAGE PUBLIQUE, et la différence est tout le
             raisonnement: cette page est derrière le mot de passe du Catalogue.
             Qui la lit est un programmateur, il vient de voir la captation
             entière, et c'est la seconde où il sait s'il veut la pièce. Le même
             bouton sur la fiche publique s'adresserait surtout à des curieux.

             LE TITRE PART DANS L'URL, en français (`title_fr`) quelle que soit
             la langue affichée: c'est le libellé que le bureau manipule, celui
             de `Offers::spectacles()`. Envoyer le titre traduit ferait échouer
             le rapprochement avec la pièce, et une demande rattachée à rien
             retombe dans le tri à la main.

             Le formulaire revalide ce paramètre contre la liste réelle: rien de
             ce qui vient de l'URL n'est réaffiché sur parole. */ ?>

        <?php /* [12.08.2026] SEULEMENT les vidéos réservées au Catalogue.

                 Ma première version les montrait toutes, en me disant que qui
                 entre avec un mot de passe doit tout voir. C'était faux à
                 l'usage : le teaser est déjà sur la page publique, et le
                 remettre ici prend la place de ce qu'on est venu chercher. Un
                 programmateur ouvre cette fiche pour la captation, pas pour la
                 bande annonce qu'il vient de regarder.

                 Les vidéos sont lues ici plutôt que passées par le contrôleur,
                 pour que cette vue fonctionne des deux côtés sans rien exiger
                 de personne. */ ?>
        <?php $vids = array_values(array_filter(
                VideoLib::forOwner('project', (int)$item['id']),
                fn($v) => !empty($v['catalog_only']))); ?>
        <?php if ($vids): ?>
        <div class="cat-videos">
          <?php foreach ($vids as $v) echo video_embed($v); ?>
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

        <?php /* [19.08.2026] L'appel à programmer se tient EN BAS, après les
                 crédits, et non plus sous la vidéo. Anna : « é a última parte
                 da página ». On regarde, on lit qui fait quoi, et c'est
                 seulement là qu'on demande une date : placé sous la vidéo, il
                 coupait la lecture pour proposer d'acheter avant d'avoir
                 montré la distribution. */ ?>
        <?php $tFr = trim((string)($item['title_fr'] ?? '')) ?: trim((string)($item['title_en'] ?? '')); ?>
        <?php if ($tFr !== ''): ?>
        <div class="cat-cta">
          <div>
            <strong><?= e(t('booking_cta')) ?></strong>
            <p><?= e(t('booking_cta_sub')) ?></p>
          </div>
          <a class="cat-cta-b" href="<?= e(url('/demande.php')) ?>?projet=<?= rawurlencode($tFr) ?>">
            <?= e(t('booking_cta_b')) ?></a>
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

          <?php /* [12.08.2026] Les documents du CMS, zone « doc ».

                   Quand ils ont quitté la page publique le 11.08, il était dit
                   que le Catalogue les servirait de là, derrière le mot de
                   passe. C'est écrit ici, et cela n'avait pas été fait : la
                   colonne ne lisait que le dossier medias/, qui n'existe pas
                   encore faute de compte FTP. Une fiche technique déjà déposée
                   dans le CMS restait donc invisible des deux côtés.

                   Les deux sources se suivent dans la même colonne, et
                   personne n'a à savoir laquelle est laquelle : ce qui vient du
                   CMS d'abord, parce que c'est ce qui existe aujourd'hui, puis
                   ce qui vient du dossier quand il y en aura. */ ?>
          <?php $docsCms = Docs::forOwner('project', (int)$item['id'], 'doc'); ?>
          <?php if ($docsCms): ?>
          <?= docs_list($docsCms, true) ?>
          <?php endif; ?>

          <?php if (!$res && !$docsCms): ?>
          <p class="muted"><?= e(t('cat_rien_depose')) ?></p>
          <?php elseif ($res): ?>
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
          <?php /* [12.08.2026] L'adresse du Catalogue, et non celle du site.
                   Qui arrive ici a déjà écrit, déjà parlé, souvent déjà reçu un
                   devis : le renvoyer vers une boîte générale lui fait
                   recommencer, et fait perdre le fil de la conversation.
                   Réglable dans le CMS ; à défaut, l'adresse d'Anna. */ ?>
          <?php $catMail = trim((string)setting('catalogue_email', '')) ?: 'anna@le-voisin.com'; ?>
          <p><a class="btn" href="mailto:<?= e($catMail) ?>"><?= e(t('cat_ecrire')) ?></a></p>
        </div>

      </aside>
    </div>
  </div>
</article>
