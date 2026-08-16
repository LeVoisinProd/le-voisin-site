<?php
/**
 * La page « À propos ».   [V43-APROPOS]
 *
 * Le module s'appelle « team » pour des raisons d'histoire : il servait à
 * afficher l'équipe, et la page « À propos » s'est construite autour. Il fait
 * maintenant les deux — le texte de la page, puis les fiches de l'équipe.
 *
 * Ce qui change ici. Le corps du texte n'est plus versé tel quel dans un bloc
 * « rich » : il est lu, découpé en sections, et rendu selon ce qu'il contient
 * (voir partials/apropos.php pour la convention h2 / h3 / ul / blockquote).
 * Une page écrite sans cette convention s'affiche exactement comme avant, en
 * texte suivi — rien ne casse chez qui n'a rien appris.
 *
 * L'ÉQUIPE VIENT TOUJOURS APRÈS LE TEXTE. Le titre « Équipe » doit donc être
 * le dernier <h2> du champ, sans quoi les portraits se retrouveraient sous une
 * autre rubrique. C'est la seule règle d'ordre à retenir.
 *
 * Les biographies sont repliées. Elles ne sont pas raccourcies : personne ne
 * lit quatre parcours à la suite, tout le monde lit celui qu'il est venu
 * chercher, et le texte entier reste à un clic.
 */
require_once LV_APP . '/views/partials/apropos.php';

$apSections = apropos_sections((string)f($page, 'body'));
?>
<?php /* La feuille de style est appelée ici, et non dans le <head> du gabarit
         commun, ce qui n'est pas l'usage du site — les autres <link> sont tous
         dans layout.php.

         La raison est de prudence, pas de style. layout.php dessine TOUTES les
         pages : le déployer pour une feuille qui ne concerne qu'une seule page
         ferait courir un risque à l'ensemble du site, alors que ce chantier
         n'en a pas besoin. Un <link> posé dans le corps est appliqué par tous
         les navigateurs ; le seul défaut connu est un très bref instant sans
         style, imperceptible pour une feuille de cette taille.

         À remonter dans layout.php le jour où ce fichier sera déployé pour une
         autre raison. */ ?>
<link rel="stylesheet" href="<?= e(url('/assets/css/apropos.css')) ?>?v=<?= @filemtime(LV_ROOT . '/assets/css/apropos.css') ?: 1 ?>">
<section class="section">
  <div class="wrap">
    <h1><?= e(f($page, 'title')) ?></h1>

    <div class="about-page">

      <?php foreach ($apSections as $apS) echo apropos_section($apS); ?>

      <?php if ($members): ?>
      <div class="team">
        <?php foreach ($members as $m):
            $img = !empty($m['image_id']) ? Img::row((int)$m['image_id']) : null;
            $nom = trim($m['first_name'] . ' ' . $m['last_name']);
            $bio = trim((string)f($m, 'bio'));
            $credit = trim((string)$m['photo_credit']);
        ?>
        <article class="member">
          <div class="member-photo">
            <?= $img
                ? Img::tag($img, 'team', ['alt' => $nom])
                : '<span class="card-ph" aria-hidden="true">' . e(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1)) . '</span>' ?>
          </div>

          <div class="member-text">
            <h2 class="member-name"><?= e($nom) ?></h2>
            <?php if (f($m, 'role')): ?><p class="member-role"><?= e(f($m, 'role')) ?></p><?php endif; ?>
          </div>

          <?php /* Le repli n'existe que s'il y a quelque chose à replier : une
                   fiche sans biographie n'affiche pas un bouton qui n'ouvre
                   rien. Le crédit photo, lui, suit la biographie — c'est là
                   qu'il est lu, et il n'a pas à occuper une ligne sur une
                   grille de quatre portraits. */ ?>
          <?php if ($bio !== '' || $credit !== ''): ?>
          <details class="member-plus">
            <summary><?= e(t('bio')) ?></summary>
            <?php if ($bio !== ''): ?><div class="member-bio rich"><?= $bio ?></div><?php endif; ?>
            <?php if ($credit !== ''): ?><p class="member-credit">© <?= e($credit) ?></p><?php endif; ?>
          </details>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>
