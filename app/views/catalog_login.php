<?php
/* Le Catalogue — la porte.   [V42-CATALOGUE]
 *
 * Une seule case, et deux phrases pour dire à qui on parle. Un programmateur
 * qui arrive ici a reçu un e-mail avec l'adresse et le mot de passe : la page
 * ne doit pas lui demander de comprendre quoi que ce soit, seulement de coller.
 *
 * Trois états, et un seul message chacun :
 *   — pas de mot de passe configuré : le Catalogue est fermé, et on le dit ;
 *   — trop d'essais : on le dit aussi, plutôt que de répéter « mot de passe
 *     incorrect » à quelqu'un qui ne comprendrait pas pourquoi ça ne marche
 *     plus alors qu'il vient de le corriger ;
 *   — mot de passe faux : la phrase la plus courte possible.
 */
?>
<section class="section cat-porte">
  <div class="wrap narrow">
    <h1><?= e(f($page, 'title')) ?></h1>

    <?php if (!CatalogAuth::configure()): ?>

      <p class="lead"><?= e(t('cat_ferme')) ?></p>

    <?php else: ?>

      <p class="lead"><?= e(t('cat_porte_intro')) ?></p>

      <?php if (!empty($catState['error'])): ?>
      <div class="form-errors" role="alert"><p><?= e($catState['error']) ?></p></div>
      <?php endif; ?>

      <form method="post" class="form cat-porte-form">
        <?= CatalogAuth::csrfField() ?>
        <div class="field">
          <label for="cat-mdp"><?= e(t('cat_mot_de_passe')) ?></label>
          <input type="password" id="cat-mdp" name="mdp" required autofocus
                 autocomplete="current-password">
        </div>
        <p><button class="btn big" type="submit"><?= e(t('cat_entrer')) ?></button></p>
      </form>

      <?php /* [19.08.2026] « Écrivez-nous » est un lien, et ne l'était pas.
               Un programmateur sans mot de passe lisait une invitation à écrire
               sans adresse où écrire : il lui fallait deviner la page contact,
               et celui qui est venu par un lien direct vers le Catalogue n'a
               jamais vu le reste du site. Même adresse que la fiche spectacle,
               réglable dans le CMS et à défaut celle d'Anna, et un objet
               pré-rempli pour qu'on sache d'où vient la demande. */ ?>
      <?php $catMail = trim((string)setting('catalogue_email', '')) ?: 'anna@le-voisin.com'; ?>
      <?php $catLien = '<a href="mailto:' . e($catMail) . '?subject=' . rawurlencode(t('cat_porte_sujet')) . '">'
                     . e(t('cat_porte_aide_lien')) . '</a>'; ?>
      <p class="muted cat-porte-aide"><?= sprintf(e(t('cat_porte_aide')), $catLien) ?></p>

    <?php endif; ?>
  </div>
</section>
