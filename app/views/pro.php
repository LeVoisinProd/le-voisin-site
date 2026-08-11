<?php
/** Page PRO : accès collaborateurs uniquement (Mon espace + Espace projets).
 *  Le CMS et le tableau de bord ne sont volontairement PAS listés ici :
 *  on y accède en tapant directement leur adresse (moins d'exposition). */
$projUrl = trim((string)setting('pro_projects_url', ''));
?>
<section class="section">
  <div class="wrap narrow">
    <h1><?= e(f($page, 'title')) ?></h1>
    <?php if (f($page, 'body')): ?><div class="rich lead pro-intro"><?= f($page, 'body') ?></div><?php endif; ?>

    <div class="pro-grid">
      <!-- Mon espace -->
      <article class="pro-card">
        <span class="pro-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20a8 8 0 0 1 16 0"/><circle cx="12" cy="8" r="4"/></svg>
        </span>
        <h2 class="pro-title"><?= e(t('pro_member_title')) ?></h2>
        <p class="pro-desc"><?= e(t('pro_member_desc')) ?></p>
        <a class="btn" href="<?= e(url('/espace/')) ?>"><?= e(t('pro_login')) ?> →</a>
      </article>

      <!-- Espace projets -->
      <article class="pro-card">
        <span class="pro-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
        </span>
        <h2 class="pro-title"><?= e(t('pro_projects_title')) ?></h2>
        <p class="pro-desc"><?= e(t('pro_projects_desc')) ?></p>
        <?php if ($projUrl !== ''): ?>
        <a class="btn" href="<?= e($projUrl) ?>"><?= e(t('pro_login')) ?> →</a>
        <?php else: ?>
        <span class="pro-soon"><?= e(t('pro_projects_soon')) ?></span>
        <?php endif; ?>
      </article>
    </div>
  </div>
</section>
