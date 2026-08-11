<?php /* Le Voisin — portail « Administration ».   [V5-ADMIN]
        Une seule page, deux états : non connecté → formulaire de connexion ;
        connecté → choix entre le tableau de bord et le CMS du site.
        Cette page n'est jamais dans le menu : on y accède par le pied de page. */
$u       = Auth::user();
$dashUrl = trim((string)setting('pro_dashboard_url', ''));
$dashLbl = trim((string)setting('pro_dashboard_label', '')) ?: 'Dashboard';
?>
<section class="section">
  <div class="wrap narrow">

<?php if (!$u): ?>

    <div class="adm-login">
      <h1><?= e(t('adm_login_title')) ?></h1>
      <p class="adm-login-intro"><?= e(t('adm_login_intro')) ?></p>

      <?php if ($state['error'] !== ''): ?>
      <div class="form-errors" role="alert"><p><?= e($state['error']) ?></p></div>
      <?php endif; ?>

      <form class="form adm-form" method="post" action="<?= e($state['url']) ?>">
        <?= Auth::csrfField() ?>
        <div class="field">
          <label for="adm_email"><?= e(t('adm_email')) ?></label>
          <input type="email" id="adm_email" name="email" required autofocus
                 autocomplete="username" value="<?= e($state['email']) ?>">
        </div>
        <div class="field">
          <label for="adm_pass"><?= e(t('adm_password')) ?></label>
          <input type="password" id="adm_pass" name="password" required autocomplete="current-password">
        </div>
        <p><button class="btn big" type="submit"><?= e(t('adm_submit')) ?></button></p>

        <!-- « Mot de passe oublié ? » : il n'existe pas de réinitialisation
             automatique (aucun envoi de lien par courriel). Plutôt qu'un lien
             mort, on affiche la marche à suivre réelle en une phrase. -->
        <details class="adm-forgot">
          <summary><?= e(t('adm_forgot')) ?></summary>
          <p><?= e(t('adm_forgot_help')) ?></p>
        </details>
      </form>
    </div>

<?php else: ?>

    <div class="adm-head">
      <h1><?= e(f($page, 'title')) ?></h1>
      <p class="adm-who"><?= e(t('adm_hello')) ?> <strong><?= e($u['name'] ?: $u['email']) ?></strong>
        · <a href="<?= e($state['url'] . '?deconnexion=1') ?>"><?= e(t('adm_logout')) ?></a></p>
    </div>

    <?php if (f($page, 'body')): ?><div class="rich lead pro-intro"><?= f($page, 'body') ?></div><?php endif; ?>

    <div class="pro-grid">

      <!-- Tableau de bord (application externe) -->
      <article class="pro-card">
        <span class="pro-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l3.5-4 3 2.5L20 7"/></svg>
        </span>
        <h2 class="pro-title"><?= e($dashLbl) ?></h2>
        <p class="pro-desc"><?= e(t('pro_dashboard_desc')) ?></p>
        <?php if ($dashUrl !== ''): ?>
        <a class="btn" href="<?= e($dashUrl) ?>" target="_blank" rel="noopener"><?= e(t('adm_open')) ?> <?= Ico::ext() ?></a>
        <?php else: ?>
        <span class="pro-soon"><?= e(t('pro_soon')) ?></span>
        <?php endif; ?>
      </article>

      <!-- CMS du site -->
      <article class="pro-card">
        <span class="pro-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 13h6M7 16h4"/></svg>
        </span>
        <h2 class="pro-title"><?= e(t('pro_cms_title')) ?></h2>
        <p class="pro-desc"><?= e(t('pro_cms_desc')) ?></p>
        <a class="btn" href="<?= e(url('/admin/')) ?>"><?= e(t('adm_open')) ?> →</a>
      </article>

    </div>

<?php endif; ?>
  </div>
</section>
