<?php
/* Page « Formulaires » : deux accès expliqués, au lieu de deux vignettes muettes.
   [V6-FORMULAIRES]

   Avant, cette page affichait automatiquement une vignette par sous-page —
   « Infos personnelles » et « Factures / justificatifs » — sans un mot
   d'explication. Or « Infos personnelles » ne mène pas à un formulaire mais à
   une page de connexion : on cliquait en s'attendant à un formulaire et on
   tombait sur un mot de passe. D'où deux blocs, chacun avec sa phrase et son
   bouton, pour qu'on sache où l'on va avant de cliquer.

   Le lien vers les dépenses est retrouvé par son module, pas par un numéro de
   page : renommer ou déplacer la page ne casse donc rien. */

$lvDepenses = Pages::moduleP('form_expenses');
?>
<article class="section">
  <div class="wrap">
    <h1><?= e(f($page, 'title')) ?></h1>
    <?php if (f($page, 'body')): ?>
    <div class="rich form-intro"><?= f($page, 'body') ?></div>
    <?php endif; ?>

    <div class="access-blocks">
      <?php if ($lvDepenses): ?>
      <section class="access-block">
        <p class="access-text"><?= e(t('forms_expenses_intro')) ?></p>
        <p class="access-go"><a class="btn big" href="<?= e(Pages::url($lvDepenses)) ?>"><?= e(t('forms_expenses_btn')) ?></a></p>
      </section>
      <?php endif; ?>

      <?php /* Le bloc « Espace collaborateur » a été retiré le 11.08.2026.

               Il envoyait vers /espace/login.php à une époque où c'était la
               seule porte. Depuis, la page « Espaces dédiés » existe, elle est
               au menu, et elle explique les deux accès au lieu d'en poser un
               ici sans contexte. Deux entrées vers la même porte, sur deux
               pages différentes, c'est une de trop : celle qui reste est celle
               qui sait de quoi elle parle.

               Les clés forms_member_lead, forms_member_intro et
               forms_member_btn ne servent plus à personne. Elles restent dans
               les fichiers de langue — les retirer n'apporte rien et ferait
               deux fichiers de plus à déployer. */ ?>
    </div>

    <?= gallery_grid($gallery ?? []) ?>
    <?= docs_list($documents ?? []) ?>
  </div>
</article>
