<?php
/** Gabarit principal du site public.   [V5-ADMIN]
 *  $content : HTML de la vue.
 *
 *  V5-ADMIN (29.07.2026) : pied de page noir en trois colonnes, avec une
 *  barre du bas (copyright · mentions légales · cookies · Administration).
 *  Le lien « Administration » est le SEUL chemin vers la page de connexion
 *  de l'équipe : elle n'apparaît dans aucun menu. */
$siteName = setting('site_name', 'Le Voisin');
$gaId = trim(setting('ga_id', ''));
$cookiesMode = setting('cookies_mode', 'advanced'); // simple | advanced | off
$privacy = null;
foreach (Pages::all() as $pp) {
    if (str_contains($pp['slug_en'], 'privacy') || str_contains($pp['slug_fr'], 'confidentialite')) { $privacy = $pp; break; }
}

/* Page « Soutenez-nous / Support Us » du site, si elle existe.
   Elle sert à deux endroits : le bouton « Partenaires & dons » du pied de page
   mène directement à cette page, et la page « Soutien » n'affiche plus de bouton
   faisant double emploi avec lui. La page est reconnue à son raccourci (slug),
   en français comme en anglais : renommer son titre ne casse donc rien. */
if (!function_exists('lv_page_dons')) {
    function lv_page_dons(): ?array
    {
        static $cherchee = false, $memoire = null;
        if ($cherchee) return $memoire;
        $cherchee = true;
        $raccourcis = ['support-us', 'soutenez-nous', 'nous-soutenir', 'soutenir',
                       'faire-un-don', 'dons', 'donate', 'donations'];
        foreach (Pages::all() as $p) {
            if (empty($p['visible'])) continue;
            foreach (I18n::$langs as $lg) {
                $slug = strtolower(trim((string)($p['slug_' . $lg] ?? '')));
                if ($slug !== '' && in_array($slug, $raccourcis, true)) return $memoire = $p;
            }
        }
        return $memoire = null;
    }
}

/* Page « Administration » du site : la porte d'entrée de l'équipe (connexion,
   puis choix entre le tableau de bord et le CMS). Elle est reconnue à son
   module, jamais à un numéro : renommer la page ou changer son raccourci ne
   casse donc pas le lien du pied de page. Elle n'est volontairement dans aucun
   menu (in_nav = 0) : ce lien du bas de page est le seul chemin. */
if (!function_exists('lv_page_admin')) {
    function lv_page_admin(): ?array
    {
        static $cherchee = false, $memoire = null;
        if ($cherchee) return $memoire;
        $cherchee = true;
        foreach (Pages::all() as $p) {
            if (!empty($p['visible']) && ($p['module'] ?? '') === 'admin_portal') return $memoire = $p;
        }
        return $memoire = null;
    }
}

/* Page « Mentions légales », si elle existe ; à défaut la politique de
   confidentialité, qui en tient lieu aujourd'hui. Le libellé affiché est
   toujours le titre réel de la page : le lien ne promet jamais autre chose
   que ce qu'il ouvre. */
if (!function_exists('lv_page_mentions')) {
    function lv_page_mentions(): ?array
    {
        static $cherchee = false, $memoire = null;
        if ($cherchee) return $memoire;
        $cherchee = true;
        $raccourcis = ['mentions-legales', 'mentions', 'legal-notice', 'legal', 'impressum'];
        foreach (Pages::all() as $p) {
            if (empty($p['visible'])) continue;
            foreach (I18n::$langs as $lg) {
                $slug = strtolower(trim((string)($p['slug_' . $lg] ?? '')));
                if ($slug !== '' && in_array($slug, $raccourcis, true)) return $memoire = $p;
            }
        }
        return $memoire = null;
    }
}
?><!DOCTYPE html>
<html lang="<?= e(I18n::$lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title']) ?></title>
<?php /* [V42-CATALOGUE] « catalog » rejoint la liste : un espace sous mot de
         passe n'a rien à faire dans un moteur de recherche, et la grille
         serait indexée à travers la page de connexion. */ ?>
<?php if (in_array((string)($page['module'] ?? ''), ['admin_portal', 'pro', 'catalog'], true)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($meta['desc'] !== ''): ?><meta name="description" content="<?= e($meta['desc']) ?>"><?php endif; ?>
<?php if (!empty($meta['url'])): ?><link rel="canonical" href="<?= e($meta['url']) ?>"><?php endif; ?>
<?php foreach (($meta['alt'] ?? []) as $lg => $u): ?>
<link rel="alternate" hreflang="<?= e($lg) ?>" href="<?= e($u) ?>">
<?php endforeach; ?>
<?php if (!empty($meta['alt'][I18n::$default])): ?><link rel="alternate" hreflang="x-default" href="<?= e($meta['alt'][I18n::$default]) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($meta['title']) ?>">
<?php if ($meta['desc'] !== ''): ?><meta property="og:description" content="<?= e($meta['desc']) ?>"><?php endif; ?>
<?php if (!empty($meta['url'])): ?><meta property="og:url" content="<?= e($meta['url']) ?>"><?php endif; ?>
<?php if (!empty($meta['og'])): ?><meta property="og:image" content="<?= e($meta['og']) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<!-- [V24-FAVICON] Icône du navigateur : le logo Le Voisin (bloc noir « LE » sur la
     bande jaune), dessiné en SVG pour rester net à toutes les tailles. -->
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' fill='%23fcfcfc'/><rect x='0' y='3' width='14.5' height='13' fill='%230c0d0d'/><rect x='0' y='16' width='32' height='13' fill='%23ffd331'/><text x='7.25' y='13.4' font-family='Helvetica,Arial,sans-serif' font-size='10.6' font-weight='bold' fill='%23ffffff' text-anchor='middle' textLength='9.4' lengthAdjust='spacingAndGlyphs'>LE</text><text x='16' y='25.4' font-family='Helvetica,Arial,sans-serif' font-size='10.6' font-weight='bold' fill='%230c0d0d' text-anchor='middle' textLength='28' lengthAdjust='spacingAndGlyphs'>VOISIN</text></svg>">
<link rel="preload" href="<?= e(url('/assets/fonts/space-grotesk-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(url('/assets/css/fonts.css')) ?>?v=<?= @filemtime(LV_ROOT . '/assets/css/fonts.css') ?: 1 ?>">
<link rel="stylesheet" href="<?= e(url('/assets/css/site.css')) ?>?v=<?= @filemtime(LV_ROOT . '/assets/css/site.css') ?: 1 ?>">
<?php if ($gaId !== '' && $cookiesMode !== 'off'): ?>
<script>window.LV_GA = <?= json_encode($gaId) ?>;</script>
<?php elseif ($gaId !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config','<?= e($gaId) ?>');</script>
<?php endif; ?>
</head>
<?php $lvHasHero = (($page['template'] ?? '') === 'home') && (
        !empty(Img::gallery('page', (int)($page['id'] ?? 0)))
     || !empty(VideoLib::forOwner('page', (int)($page['id'] ?? 0)))
     ); ?>
<body<?= $lvHasHero ? ' class="has-hero"' : '' ?>>
<a class="skip" href="#main"><?= e(t('menu')) ?></a>
<header class="site-header">
  <div class="wrap header-in">
    <a class="logo" href="<?= e(url('/' . I18n::$lang)) ?>" aria-label="<?= e($siteName) ?>">
      <?php $logoId = (int)setting('logo_image_id', '0');
            $logoImg = $logoId ? Img::row($logoId) : null;
            if ($logoImg): ?>
      <img src="<?= e(upload_url('i/' . $logoImg['id'] . '/orig.' . $logoImg['ext'])) ?>" alt="<?= e($siteName) ?>">
      <?php elseif (is_file(LV_ROOT . '/assets/img/logo-levoisin.png')): ?>
      <img src="<?= e(url('/assets/img/logo-levoisin.png')) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>LE&nbsp;VOISIN<?php endif; ?>
    </a>
    <nav class="nav" id="nav" aria-label="Navigation">
      <ul>
        <?php foreach (Pages::nav() as $p):
            $on = !empty($page['id']) && ((int)$page['id'] === (int)$p['id'] || in_array((int)$page['id'], Pages::descendantIds((int)$p['id']), true));
            // Sous-menu : catégories pour la page Projets, sinon sous-pages visibles
            $sub = [];
            if ($p['module'] === 'projects') {
                foreach (DB::all('SELECT * FROM categories ORDER BY sort, id') as $c) {
                    $cs = f($c, 'slug') ?: $c['slug_en'];
                    if ($cs !== '') $sub[] = ['label' => f($c, 'name'), 'url' => Pages::url($p) . '?cat=' . rawurlencode($cs)];
                }
            }
            foreach (Pages::children((int)$p['id'], true) as $child) {
                if ($child['in_nav']) $sub[] = ['label' => f($child, 'title'), 'url' => Pages::url($child)];
            }
        ?>
        <li class="<?= $on ? 'on' : '' ?><?= $sub ? ' has-sub' : '' ?>">
          <a href="<?= e(Pages::url($p)) ?>"><?= e(f($p, 'title')) ?></a>
          <?php if ($sub): ?>
          <ul class="subnav">
            <?php foreach ($sub as $s): ?>
            <li><a href="<?= e($s['url']) ?>"><?= e($s['label']) ?></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="nav-langs">
        <?php foreach (I18n::$langs as $lg): ?>
        <a href="<?= e($meta['alt'][$lg] ?? url('/' . $lg)) ?>"<?= $lg === I18n::$lang ? ' class="on"' : '' ?>><?= e(strtoupper($lg)) ?></a>
        <?php endforeach; ?>
      </p>
    </nav>
    <button class="burger" id="burger" aria-expanded="false" aria-controls="nav"><span></span><span></span><span></span><span class="sr"><?= e(t('menu')) ?></span></button>
  </div>
</header>

<main id="main"><?= $content ?></main>

<?php
/* « Partenaires & dons » : mène à la page « Soutenez-nous » du site, dans la
   langue en cours et dans le même onglet. L'adresse du champ Réglages ->
   « Partenaires & dons (URL) » ne sert que si cette page n'existe pas ou
   qu'elle est masquée. */
$lvDons    = lv_page_dons();
$lvDonsUrl = $lvDons ? Pages::url($lvDons) : trim((string)setting('donate_url'));
$lvDonsExt = !$lvDons && str_starts_with($lvDonsUrl, 'http');

/* Bas du pied de page : mentions légales (ou, à défaut, la politique de
   confidentialité) puis l'accès de l'équipe. */
$lvMentions = lv_page_mentions() ?: $privacy;
$lvAdmin    = lv_page_admin();
?>
<footer class="site-footer">
  <div class="wrap footer-grid">

    <div class="footer-col">
      <p class="footer-logo">LE&nbsp;VOISIN</p>
      <?php if (setting_i18n('footer_about')): ?>
      <p class="footer-about"><?= e(setting_i18n('footer_about')) ?></p>
      <?php endif; ?>
      <ul class="footer-contact">
        <?php if (setting('contact_address')): ?>
        <li>
          <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
          <span><?= nl2br(e(setting('contact_address'))) ?></span>
        </li>
        <?php endif; ?>
        <?php if (setting('contact_phone')): ?>
        <li>
          <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 3h3l1.5 4-2 1.5a12 12 0 0 0 6.5 6.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4.5 5.2 2 2 0 0 1 6.5 3Z"/></svg>
          <a href="tel:<?= e(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a>
        </li>
        <?php endif; ?>
        <?php if (setting('contact_email')): ?>
        <li>
          <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>
          <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
        </li>
        <?php endif; ?>
      </ul>
      <?php if (setting_i18n('contact_hours')): ?><p class="muted"><?= e(setting_i18n('contact_hours')) ?></p><?php endif; ?>
    </div>

    <div class="footer-col">
      <h2 class="footer-h"><?= e(t('footer_nav_h')) ?></h2>
      <ul class="footer-links">
        <?php foreach (Pages::nav() as $p): ?>
        <li><a href="<?= e(Pages::url($p)) ?>"><?= e(f($p, 'title')) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-col">
      <h2 class="footer-h"><?= e(t('footer_follow_h')) ?></h2>
      <ul class="footer-links">
        <?php if ($lvDonsUrl !== ''): ?>
        <li><a href="<?= e($lvDonsUrl) ?>"<?= $lvDonsExt ? ' target="_blank" rel="noopener"' : '' ?>><?= e(t('donate')) ?></a></li>
        <?php endif; ?>
        <?php if (setting('newsletter_url')): ?>
        <li><a href="<?= e(setting('newsletter_url')) ?>" target="_blank" rel="noopener"><?= e(t('newsletter')) ?> <?= Ico::ext() ?></a></li>
        <?php endif; ?>
      </ul>
      <?php if (setting('instagram_url') || setting('linkedin_url')): ?>
      <p class="social-icons">
        <?php if (setting('instagram_url')): ?>
        <a href="<?= e(setting('instagram_url')) ?>" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram">
          <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2c2.72 0 3.06.01 4.12.06 1.07.05 1.8.22 2.43.46.66.26 1.22.6 1.77 1.15.55.55.89 1.11 1.15 1.77.24.63.41 1.36.46 2.43.05 1.07.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.07-.22 1.8-.46 2.43a4.9 4.9 0 0 1-1.15 1.77c-.55.55-1.11.89-1.77 1.15-.63.24-1.36.41-2.43.46-1.07.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.07-.05-1.8-.22-2.43-.46a4.9 4.9 0 0 1-1.77-1.15 4.9 4.9 0 0 1-1.15-1.77c-.24-.63-.41-1.36-.46-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.07.22-1.8.46-2.43.26-.66.6-1.22 1.15-1.77.55-.55 1.11-.89 1.77-1.15.63-.24 1.36-.41 2.43-.46C8.94 2.01 9.28 2 12 2Zm0 1.8c-2.67 0-2.99.01-4.04.06-.98.04-1.5.21-1.86.35-.47.18-.8.4-1.15.75-.35.35-.57.68-.75 1.15-.14.36-.31.88-.35 1.86-.05 1.05-.06 1.37-.06 4.04s.01 2.99.06 4.04c.04.98.21 1.5.35 1.86.18.47.4.8.75 1.15.35.35.68.57 1.15.75.36.14.88.31 1.86.35 1.05.05 1.37.06 4.04.06s2.99-.01 4.04-.06c.98-.04 1.5-.21 1.86-.35.47-.18.8-.4 1.15-.75.35-.35.57-.68.75-1.15.14-.36.31-.88.35-1.86.05-1.05.06-1.37.06-4.04s-.01-2.99-.06-4.04c-.04-.98-.21-1.5-.35-1.86a3.1 3.1 0 0 0-.75-1.15 3.1 3.1 0 0 0-1.15-.75c-.36-.14-.88-.31-1.86-.35-1.05-.05-1.37-.06-4.04-.06Zm0 3.07a5.13 5.13 0 1 1 0 10.26 5.13 5.13 0 0 1 0-10.26Zm0 8.46a3.33 3.33 0 1 0 0-6.66 3.33 3.33 0 0 0 0 6.66Zm6.54-8.66a1.2 1.2 0 1 1-2.4 0 1.2 1.2 0 0 1 2.4 0Z"/></svg>
        </a>
        <?php endif; ?>
        <?php if (setting('linkedin_url')): ?>
        <a href="<?= e(setting('linkedin_url')) ?>" target="_blank" rel="noopener" aria-label="LinkedIn" title="LinkedIn">
          <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.13 1.44-2.13 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14Zm1.78 13.02H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/></svg>
        </a>
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>

  </div>

  <?php if (setting_i18n('footer_note')): ?>
  <div class="wrap footer-note"><p><?= e(setting_i18n('footer_note')) ?></p></div>
  <?php endif; ?>

  <div class="wrap footer-bottom">
    <p class="footer-copy"><?= e($siteName) ?> &copy; <?= date('Y') ?> — <?= e(t('footer_rights')) ?></p>
    <p class="footer-legal">
      <?php if ($lvMentions): ?>
      <a href="<?= e(Pages::url($lvMentions)) ?>">
        <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.6h.01"/></svg>
        <?= e(f($lvMentions, 'title')) ?></a>
      <?php endif; ?>
      <?php if ($cookiesMode !== 'off'): ?>
      <button type="button" class="linklike js-cookie-open"><?= e(t('cookies_manage')) ?></button>
      <?php endif; ?>
      <?php if ($lvAdmin): ?>
      <a href="<?= e(Pages::url($lvAdmin)) ?>" class="footer-admin">
        <svg class="fic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.6 1.6 0 0 0 .32 1.76l.06.06a1.9 1.9 0 1 1-2.7 2.7l-.05-.06a1.6 1.6 0 0 0-1.77-.32 1.6 1.6 0 0 0-.97 1.46v.17a1.9 1.9 0 0 1-3.8 0v-.09a1.6 1.6 0 0 0-1.05-1.46 1.6 1.6 0 0 0-1.76.32l-.06.06a1.9 1.9 0 1 1-2.7-2.7l.06-.06a1.6 1.6 0 0 0 .32-1.76 1.6 1.6 0 0 0-1.46-.97H3.7a1.9 1.9 0 1 1 0-3.8h.09a1.6 1.6 0 0 0 1.46-1.05 1.6 1.6 0 0 0-.32-1.76l-.06-.06a1.9 1.9 0 1 1 2.7-2.7l.06.06a1.6 1.6 0 0 0 1.76.32h.08a1.6 1.6 0 0 0 .97-1.46V3.7a1.9 1.9 0 1 1 3.8 0v.09a1.6 1.6 0 0 0 .97 1.46 1.6 1.6 0 0 0 1.76-.32l.06-.06a1.9 1.9 0 1 1 2.7 2.7l-.06.06a1.6 1.6 0 0 0-.32 1.76v.08a1.6 1.6 0 0 0 1.46.97h.17a1.9 1.9 0 1 1 0 3.8h-.09a1.6 1.6 0 0 0-1.46.97Z"/></svg>
        <?= e(t('footer_admin')) ?></a>
      <?php endif; ?>
    </p>
  </div>
</footer>

<?php $partners = Img::gallery('site', 0, 'partners'); if ($partners): ?>
<div class="partners">
  <div class="wrap partners-in">
    <?php foreach ($partners as $lgo): Img::ensure($lgo, 'logo');
        $name = trim((string)($lgo['alt_fr'] ?? '')) ?: trim((string)($lgo['alt_en'] ?? ''));
        $link = trim((string)($lgo['alt_en'] ?? ''));
        $tag = Img::tag($lgo, 'logo', ['alt' => $name]);
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            echo '<a class="partner-logo" href="' . e($link) . '" target="_blank" rel="noopener"' . ($name ? ' title="' . e($name) . '"' : '') . '>' . $tag . '</a>';
        } else {
            echo '<span class="partner-logo"' . ($name ? ' title="' . e($name) . '"' : '') . '>' . $tag . '</span>';
        }
    endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($cookiesMode !== 'off'): ?>
<div class="cookies" id="cookies" hidden>
  <div class="cookies-box">
    <h2><?= e(ck('title')) ?></h2>
    <p><?= e(ck('text')) ?></p>
    <?php if ($privacy): ?><p><a href="<?= e(Pages::url($privacy)) ?>"><?= e(ck('more')) ?></a></p><?php endif; ?>
    <div class="cookies-actions">
      <?php if ($cookiesMode === 'simple'): ?>
      <button class="btn js-ck-all"><?= e(ck('ok')) ?></button>
      <?php else: ?>
      <button class="btn js-ck-all"><?= e(ck('accept')) ?></button>
      <button class="btn ghost js-ck-none"><?= e(ck('refuse')) ?></button>
      <button class="linklike js-ck-custom"><?= e(ck('customize')) ?></button>
      <?php endif; ?>
    </div>
  </div>
</div>
<dialog class="cookies-prefs" id="cookiePrefs">
  <form method="dialog">
    <h2><?= e(t('cookies_prefs_title')) ?></h2>
    <div class="pref"><div><strong><?= e(t('cookies_necessary')) ?></strong><p><?= e(t('cookies_necessary_txt')) ?></p></div>
      <input type="checkbox" checked disabled></div>
    <div class="pref"><div><strong><?= e(t('cookies_analytics')) ?></strong><p><?= e(t('cookies_analytics_txt')) ?></p></div>
      <input type="checkbox" id="ckAnalytics"></div>
    <div class="pref"><div><strong><?= e(t('cookies_media')) ?></strong><p><?= e(t('cookies_media_txt')) ?></p></div>
      <input type="checkbox" id="ckMedia"></div>
    <div class="cookies-actions">
      <button class="btn js-ck-save" value="save"><?= e(t('cookies_save')) ?></button>
      <button class="linklike" value="cancel"><?= e(t('close')) ?></button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<div class="lightbox" id="lightbox" hidden>
  <button class="lightbox-close" aria-label="<?= e(t('close')) ?>">×</button>
  <button class="lightbox-prev" aria-label="‹">‹</button>
  <img alt="">
  <button class="lightbox-next" aria-label="›">›</button>
</div>

<script>window.LV = {lang: <?= json_encode(I18n::$lang) ?>, cookiesMode: <?= json_encode($cookiesMode) ?>};</script>
<script src="<?= e(url('/assets/js/site.js')) ?>?v=<?= @filemtime(LV_ROOT . '/assets/js/site.js') ?: 1 ?>"></script>
<script>
/* Carrousel d'images du bandeau d'accueil (défilement automatique, flèches,
   pastilles). Script autonome : il fonctionne même si assets/js/site.js
   manque. La classe .js-hero-images lui est propre, il n'y a donc jamais de
   double initialisation avec le carrousel vidéo de site.js. */
(function () {
  var cars = document.querySelectorAll('.js-hero-images');
  if (!cars.length) return;

  Array.prototype.forEach.call(cars, function (car) {
    if (car.getAttribute('data-lv-init')) return;
    car.setAttribute('data-lv-init', '1');

    var slides = Array.prototype.slice.call(car.querySelectorAll('.hero-slide'));
    var dots   = Array.prototype.slice.call(car.querySelectorAll('.hero-dot'));
    if (slides.length < 2) return;

    var idx = 0, timer = null;
    var secs = parseInt(car.getAttribute('data-secs'), 10);
    if (!secs || secs < 2) secs = 6;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function render() {
      slides.forEach(function (s, k) { s.classList.toggle('on', k === idx); });
      dots.forEach(function (d, k) { d.classList.toggle('on', k === idx); });
    }
    function go(n) { idx = (n + slides.length) % slides.length; render(); }
    function stop() { if (timer) { clearTimeout(timer); timer = null; } }
    function play() {
      stop();
      if (reduce) return;
      timer = setTimeout(function () { go(idx + 1); play(); }, secs * 1000);
    }

    var next = car.querySelector('.hero-nav.next');
    var prev = car.querySelector('.hero-nav.prev');
    if (next) next.addEventListener('click', function () { go(idx + 1); play(); });
    if (prev) prev.addEventListener('click', function () { go(idx - 1); play(); });
    dots.forEach(function (d) {
      d.addEventListener('click', function () { go(parseInt(d.getAttribute('data-i'), 10) || 0); play(); });
    });

    /* Glissement au doigt sur mobile */
    var x0 = null;
    car.addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, {passive: true});
    car.addEventListener('touchend', function (ev) {
      if (x0 === null) return;
      var dx = ev.changedTouches[0].clientX - x0;
      x0 = null;
      if (Math.abs(dx) > 40) { go(dx < 0 ? idx + 1 : idx - 1); play(); }
    }, {passive: true});

    car.addEventListener('mouseenter', stop);
    car.addEventListener('mouseleave', play);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { play(); } });

    render();
    play();
  });
})();
</script>
<script>
/* Bandeau d'accueil : la vidéo démarre seule, sans son, avec un bouton pour
   activer le son. Les lecteurs sont chargés depuis les domaines « sans
   cookie » (youtube-nocookie.com, player.vimeo.com?dnt=1), donc sans dépôt
   de traceur : pas besoin d'attendre le consentement pour le bandeau. */
(function () {
  var host = document.querySelector('.hero-video');
  if (!host) return;
  var LBL = (window.LV && window.LV.lang === 'en') ? {on: 'Sound', off: 'Sound'} : {on: 'Son', off: 'Son'};

  function soundButton(toggle) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'hero-sound';
    b.setAttribute('aria-pressed', 'false');
    b.innerHTML = '<span class="hero-sound-ico" aria-hidden="true"></span><span>' + LBL.off + '</span>';
    var on = false;
    b.addEventListener('click', function (e) {
      e.preventDefault();
      on = !on;
      toggle(on);
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    return b;
  }

  /* 1. Lecteurs externes : on remplace le cadre « cookies » par le lecteur. */
  Array.prototype.forEach.call(host.querySelectorAll('figure.js-video[data-embed]'), function (fig) {
    var src = fig.getAttribute('data-embed') || '';
    if (!src) return;
    var isYT = src.indexOf('youtube') > -1, isVimeo = src.indexOf('vimeo') > -1;
    var p;
    if (isYT) {
      var m = src.match(/\/embed\/([^?&\/]+)/);
      p = 'autoplay=1&mute=1&loop=1&controls=0&modestbranding=1&playsinline=1&disablekb=1&iv_load_policy=3&enablejsapi=1' + (m ? '&playlist=' + m[1] : '');
    } else if (isVimeo) {
      p = 'autoplay=1&muted=1&loop=1&controls=0&title=0&byline=0&portrait=0&playsinline=1';
    } else {
      p = 'autoplay=1&mute=1&loop=1&controls=0';
    }
    var f = document.createElement('iframe');
    f.src = src + (src.indexOf('?') === -1 ? '?' : '&') + p;
    f.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
    f.setAttribute('frameborder', '0');
    f.setAttribute('tabindex', '-1');
    f.setAttribute('title', fig.getAttribute('data-title') || 'Video');
    fig.innerHTML = '';
    fig.appendChild(f);
    fig.appendChild(soundButton(function (on) {
      var w = f.contentWindow; if (!w) return;
      if (isYT) {
        w.postMessage(JSON.stringify({event: 'command', func: on ? 'unMute' : 'mute', args: []}), '*');
        if (on) w.postMessage(JSON.stringify({event: 'command', func: 'setVolume', args: [100]}), '*');
      } else if (isVimeo) {
        w.postMessage(JSON.stringify({method: 'setVolume', value: on ? 1 : 0}), '*');
      } else {
        w.postMessage(JSON.stringify({command: 'muted', parameters: [!on]}), '*');
      }
    }));
  });

  /* 2. Vidéos hébergées sur le site : elles démarrent déjà seules et muettes. */
  Array.prototype.forEach.call(host.querySelectorAll('.video-file'), function (fig) {
    var v = fig.querySelector('video'); if (!v) return;
    v.muted = true; v.autoplay = true; v.loop = true; v.playsInline = true;
    var go = v.play(); if (go && go.catch) go.catch(function () {});
    fig.appendChild(soundButton(function (on) { v.muted = !on; if (on) { v.volume = 1; v.play(); } }));
  });
})();
</script>
<script>
/* Accueil : la barre de menu est posée sur la photo, sans fond. Dès que le
   bandeau a défilé sous la barre, celle-ci redevient blanche avec le texte
   noir (sinon le menu blanc se retrouverait sur le texte de la page).
   Marche avec le carrousel de photos (.lvhero) comme avec la vidéo. */
(function () {
  var entete = document.querySelector('body.has-hero .site-header');
  var bandeau = document.querySelector('.lvhero, .hero-images, .hero-video');
  if (!entete || !bandeau) return;
  function maj() {
    entete.classList.toggle('scrolled', bandeau.getBoundingClientRect().bottom <= entete.offsetHeight);
  }
  window.addEventListener('scroll', maj, {passive: true});
  window.addEventListener('resize', maj);
  window.addEventListener('load', maj);
  maj();
})();
</script>
</body>
</html>
