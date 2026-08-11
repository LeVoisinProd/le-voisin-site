<?php
/** Page « Mon espace » — porte d'entrée vers les espaces réservés.
 *
 *  Remplace l'ancienne page « Soutien », dont le contenu (guide interne, code de
 *  conduite, dispositif de signalement, références) passe SOUS les deux cartes.
 *
 *  [H1] Le titre affiché ne vient PAS de $page['title'] : la table `pages` n'a
 *  qu'un seul champ de titre, qui sert à la fois au menu et au h1. Le menu doit
 *  dire « Mon espace », la page « Vos espaces dédiés ». Le h1 passe donc par une
 *  clé de traduction, et le CMS garde la main sur le libellé du menu.
 *
 *  Les deux destinations ne sont pas au même stade :
 *    — /espace/ répond déjà (302 vers la connexion)
 *    — le catalogue n'est pas déployé. Tant que `catalogue_url` est vide dans les
 *      réglages, la carte affiche « Bientôt » au lieu d'un lien mort. Même logique
 *      que pro.php avec pro_projects_url.
 */
$catUrl = trim((string)setting('catalogue_url', ''));
?>
<section class="espaces">
  <div class="wrap espaces-grid">

    <div class="espaces-intro">
      <h1><?= e(t('esp_h1')) ?></h1>
      <p class="lead"><?= e(t('esp_intro')) ?></p>
    </div>

    <div class="espaces-cards">

      <a class="esp-card esp-card--membres" href="<?= e(url('/espace/')) ?>">
        <span class="esp-arc esp-arc-1" aria-hidden="true"></span>
        <span class="esp-arc esp-arc-2" aria-hidden="true"></span>
        <p class="esp-eyebrow"><?= e(t('esp_membres_pour')) ?></p>
        <h2 class="esp-title"><?= e(t('esp_membres_title')) ?></h2>
        <p class="esp-desc"><?= e(t('esp_membres_desc')) ?></p>
        <span class="btn"><?= e(t('esp_acceder')) ?>
          <svg class="esp-fleche" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
      </a>

      <?php if ($catUrl !== ''): ?>
      <a class="esp-card esp-card--pros" href="<?= e($catUrl) ?>">
      <?php else: ?>
      <div class="esp-card esp-card--pros esp-card--soon">
      <?php endif; ?>
        <span class="esp-arc esp-arc-1" aria-hidden="true"></span>
        <span class="esp-arc esp-arc-2" aria-hidden="true"></span>
        <?php if ($catUrl === ''): ?><span class="esp-soon"><?= e(t('esp_soon')) ?></span><?php endif; ?>
        <p class="esp-eyebrow"><?= e(t('esp_pros_pour')) ?></p>
        <h2 class="esp-title"><?= e(t('esp_pros_title')) ?></h2>
        <p class="esp-desc"><?= e(t('esp_pros_desc')) ?></p>
        <?php if ($catUrl !== ''): ?>
        <span class="btn"><?= e(t('esp_acceder_cat')) ?>
          <svg class="esp-fleche" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
        <?php endif; ?>
      <?= $catUrl !== '' ? '</a>' : '</div>' ?>

    </div>
  </div>
</section>

<?php /* Le contenu rédigé dans le CMS — l'ancien texte « Soutien » — vient ici,
         sans titre de section : les cartes ont déjà ouvert la page. */ ?>
<?php if (f($page, 'body')): ?>
<section class="section">
  <div class="wrap">
    <div class="rich esp-ressources"><?= f($page, 'body') ?></div>
  </div>
</section>
<?php endif; ?>
