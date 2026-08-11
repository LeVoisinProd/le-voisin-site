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

      <section class="access-block">
        <p class="access-text"><strong><?= e(t('forms_member_lead')) ?></strong> <?= e(t('forms_member_intro')) ?></p>
        <p class="access-go"><a class="btn big" href="<?= e(url('/espace/login.php')) ?>"><?= e(t('forms_member_btn')) ?></a></p>
      </section>
    </div>

    <?= gallery_grid($gallery ?? []) ?>
    <?= docs_list($documents ?? []) ?>
  </div>
</article>
