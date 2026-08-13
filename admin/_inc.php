<?php
/**
 * Administration — amorçage commun + gabarit + rendu des champs.
 * [V11-LANGUE-CACHE] (30.07.2026)
 *
 * L'interface est bilingue : ta('…') va chercher le libellé dans
 * app/i18n/admin.fr.php ou admin.en.php selon la langue choisie dans la
 * barre latérale, et tc(…) traduit les libellés écrits dans les fichiers
 * de configuration. La langue du CMS n'a aucun effet sur le site public.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
I18n::init();
session_boot();
I18n::initAdmin();

/** Noms des langues de CONTENU (onglets FR / EN dans les formulaires). */
const ADMIN_LANGS_LABELS = ['en' => 'English', 'fr' => 'Français'];

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['lv_flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['lv_flash'] ?? null;
    unset($_SESSION['lv_flash']);
    return $f;
}

function admin_url(string $path = ''): string
{
    return url('/admin/' . ltrim($path, '/'));
}

/**
 * [V14-DUPLIQUER] Message affiché après duplication. Générique, ou précisé
 * par le module via 'dup_note' (app/config/entities.php) pour indiquer ce
 * qu'il reste à modifier dans la copie.
 */
function dup_message(array $def): string
{
    return !empty($def['dup_note'])
        ? ta('lst_duplicated_hint', tc($def['label']), tc($def['dup_note']))
        : ta('lst_duplicated', tc($def['label']));
}

/**
 * L'adresse d'un fichier de style ou de script, suivie de ?v=…
 *
 * Le numéro est la date de modification du fichier. Il change donc tout seul
 * à chaque mise à jour : le navigateur va rechercher la nouvelle version au
 * lieu de garder l'ancienne en mémoire. Sans cela, une correction de style
 * peut rester invisible pendant des jours.
 *
 * $chemin est relatif à la racine du site (« /admin/assets/admin.css »).
 */
function asset_url(string $chemin): string
{
    $chemin = '/' . ltrim($chemin, '/');
    $v = @filemtime(LV_ROOT . $chemin) ?: 1;
    return url($chemin) . '?v=' . $v;
}

/**
 * La page en cours, mais dans l'autre langue d'administration.
 * On reprend l'adresse affichée et on y pose (ou remplace) ?lang=…
 */
function admin_lang_url(string $lang): string
{
    $uri   = (string)($_SERVER['REQUEST_URI'] ?? '/admin/');
    $parts = explode('?', $uri, 2);
    $q     = [];
    if (isset($parts[1])) parse_str($parts[1], $q);
    $q['lang'] = $lang;
    return $parts[0] . '?' . http_build_query($q);
}

/** Le sélecteur FR | EN de la langue de l'administration. */
function admin_lang_switch(string $class = 'side-lang'): string
{
    $out = '<p class="' . e($class) . '" title="' . e(ta('nav_lang')) . '">';
    $premier = true;
    foreach (I18n::ADMIN_LANGS as $lg) {
        // Séparateur de secours : si la feuille de style n'est pas encore
        // chargée, les deux langues restent lisibles (« FR · EN ») au lieu
        // de se coller l'une à l'autre.
        if (!$premier) $out .= '<span class="lang-sep" aria-hidden="true">&nbsp;·&nbsp;</span>';
        $premier = false;
        $out .= '<a href="' . e(admin_lang_url($lg)) . '"' . (I18n::$admin === $lg ? ' class="on"' : '')
             . ' hreflang="' . e($lg) . '">' . e(mb_strtoupper($lg)) . '</a>';
    }
    return $out . '</p>';
}

/** Entête + navigation de l'administration. */
function admin_top(string $title, string $active = ''): void
{
    $u = Auth::user();
    $entities = Content::entities();
    ?><!DOCTYPE html>
<html lang="<?= e(I18n::$admin) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf" content="<?= e(Auth::csrf()) ?>">
<title><?= e($title) ?> — <?= e(ta('com_admin')) ?></title>
<!-- [V24-FAVICON] Icône du navigateur : le logo Le Voisin (bloc noir « LE » sur la
     bande jaune), dessiné en SVG pour rester net à toutes les tailles. -->
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' fill='%23fcfcfc'/><rect x='0' y='3' width='14.5' height='13' fill='%230c0d0d'/><rect x='0' y='16' width='32' height='13' fill='%23ffd331'/><text x='7.25' y='13.4' font-family='Helvetica,Arial,sans-serif' font-size='10.6' font-weight='bold' fill='%23ffffff' text-anchor='middle' textLength='9.4' lengthAdjust='spacingAndGlyphs'>LE</text><text x='16' y='25.4' font-family='Helvetica,Arial,sans-serif' font-size='10.6' font-weight='bold' fill='%230c0d0d' text-anchor='middle' textLength='28' lengthAdjust='spacingAndGlyphs'>VOISIN</text></svg>">
<link rel="stylesheet" href="<?= e(asset_url('/assets/css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(admin_url('assets/vendor/cropperjs/cropper.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('/admin/assets/admin.css')) ?>">
</head>
<body>
<div class="shell">
<aside class="side">
  <p class="side-logo">LE&nbsp;VOISIN<span><?= e(ta('com_admin')) ?></span></p>
  <nav>
    <a href="<?= e(admin_url()) ?>"<?= $active === 'dash' ? ' class="on"' : '' ?>><?= e(ta('nav_dash')) ?></a>
    <a href="<?= e(admin_url('pages.php')) ?>"<?= $active === 'pages' ? ' class="on"' : '' ?>><?= e(ta('nav_pages')) ?></a>
    <p class="side-sep"><?= e(ta('nav_modules')) ?></p>
    <?php foreach ($entities as $key => $def): ?>
    <a href="<?= e(admin_url('list.php?e=' . $key)) ?>"<?= $active === 'e:' . $key ? ' class="on"' : '' ?>><?= e(tc($def['menu'])) ?></a>
    <?php endforeach; ?>
    <p class="side-sep"><?= e(ta('nav_espace')) ?></p>
    <a href="<?= e(admin_url('collaborators.php')) ?>"<?= $active === 'collab' ? ' class="on"' : '' ?>><?= e(ta('nav_collab')) ?></a>
    <?php /* [13.08.2026] Les documents entre les personnes et le journal : on y
             va en pensant « une pièce », et non « quelqu’un ». Jusqu’ici il
             fallait passer par une fiche, donc savoir de qui elle était. */ ?>
    <a href="<?= e(admin_url('documents.php')) ?>"<?= $active === 'documents' ? ' class="on"' : '' ?>><?= e(ta('nav_documents')) ?></a>
    <a href="<?= e(admin_url('access-log.php')) ?>"<?= $active === 'access_log' ? ' class="on"' : '' ?>><?= e(ta('nav_access_log')) ?></a>
    <p class="side-sep"><?= e(ta('nav_config')) ?></p>
    <a href="<?= e(admin_url('settings.php')) ?>"<?= $active === 'settings' ? ' class="on"' : '' ?>><?= e(ta('nav_settings')) ?></a>
    <a href="<?= e(admin_url('users.php')) ?>"<?= $active === 'users' ? ' class="on"' : '' ?>><?= e(ta('nav_users')) ?></a>
  </nav>
  <div class="side-foot">
    <a href="<?= e(url('/' . I18n::$default)) ?>" target="_blank"><?= e(ta('nav_site')) ?> <?= Ico::ext() ?></a>
    <a href="<?= e(admin_url('logout.php')) ?>"><?= e(ta('nav_logout')) ?><?= $u ? ' (' . e($u['name'] ?: $u['email']) . ')' : '' ?></a>
    <?= admin_lang_switch() ?>
  </div>
</aside>
<main class="content">
<?php $f = flash(); if ($f): ?>
<div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>
<?php
}

/**
 * Les libellés dont le navigateur a besoin (admin.js).
 * Ils partent dans window.LV_ADMIN.i18n, dans la langue de l'administration.
 */
function admin_js_labels(): array
{
    return [
        'err'           => ta('js_err'),
        'confirm'       => ta('js_confirm'),
        'slugAuto'      => ta('fld_slug_ph'),
        'orderSaved'    => ta('js_order_saved'),
        'structSaved'   => ta('js_struct_saved'),
        'sending'       => ta('js_sending'),
        'noImage'       => ta('fld_no_image'),
        'imgAdded'      => ta('js_img_added'),
        'imgOptim'      => ta('js_img_optim'),
        'galDrop'       => ta('fld_gal_drop'),
        'imgDel'        => ta('js_img_del'),
        'altSaved'      => ta('js_alt_saved'),
        'cropSaved'     => ta('js_crop_saved'),
        'cropSavedN'    => ta('js_crop_saved_n'),
        'cropNone'      => ta('js_crop_none'),
        'cropLeave'     => ta('js_crop_leave'),
        'vidDrop'       => ta('js_vid_drop'),
        'vidSending'    => ta('js_vid_sending'),
        'vidAdded'      => ta('js_vid_added'),
        'vidNeedLink'   => ta('js_vid_need_link'),
        'feedLoading'   => ta('js_feed_loading'),
        'feedEmpty'     => ta('js_feed_empty'),
        'vidDel'        => ta('js_vid_del'),
        'durSaved'      => ta('js_dur_saved'),
        'docAdded'      => ta('js_doc_added'),
        'docDel'        => ta('js_doc_del'),
        'docNeedLink'   => ta('js_doc_need_link'),
        'docLinkSaved'  => ta('js_doc_link_saved'),
        'titleSaved'    => ta('js_title_saved'),
        'socialGen'     => ta('js_social_gen'),
        'socialRegen'   => ta('js_social_regen'),
        'socialGenBtn'  => ta('ed_social_gen'),
        'textCopied'    => ta('js_text_copied'),
        'socialPushed'  => ta('js_social_pushed'),
        'socialPushBtn' => ta('ed_social_push'),
        'styP'          => ta('js_sty_p'),
        'styH2'         => ta('js_sty_h2'),
        'styH3'         => ta('js_sty_h3'),
        'styLead'       => ta('js_sty_lead'),
        'styQuote'      => ta('js_sty_quote'),
        'cropNames'     => [
            'banner'   => ta('crop_banner'),
            'cover'    => ta('crop_cover'),
            'card'     => ta('crop_card'),
            'square'   => ta('crop_square'),
            'team'     => ta('crop_team'),
            'thumb'    => ta('crop_thumb'),
            'og'       => ta('crop_og'),
            'doccover' => ta('crop_doccover'),
            'gallery'  => ta('crop_gallery'),
            'content'  => ta('crop_content'),
            'logo'     => ta('crop_logo'),
        ],
    ];
}

function admin_bottom(array $opts = []): void
{
    ?></main>
</div>
<div class="modal" id="cropModal" hidden>
  <div class="modal-box">
    <div class="modal-head">
      <strong><?= e(ta('crop_title')) ?></strong>
      <span class="crop-formats" id="cropFormats"></span>
      <button type="button" class="icon-btn" id="cropClose">×</button>
    </div>
    <div class="crop-area"><img id="cropImage" src="" alt=""></div>
    <div class="modal-foot">
      <span class="hint"><?= e(ta('crop_hint')) ?></span>
      <button type="button" class="btn" id="cropSave"><?= e(ta('crop_save')) ?></button>
    </div>
  </div>
</div>
<script>
window.LV_ADMIN = {
  base: <?= json_encode(rtrim(admin_url(), '/')) ?>,
  csrf: document.querySelector('meta[name=csrf]').content,
  formats: <?= json_encode(Img::formats()) ?>,
  cropUi: <?= json_encode(Img::conf()['crop_ui']) ?>,
  linkList: <?= json_encode($opts['linkList'] ?? []) ?>,
  lang: <?= json_encode(I18n::$admin) ?>,
  i18n: <?= json_encode(admin_js_labels(), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= e(admin_url('assets/vendor/sortablejs/Sortable.min.js')) ?>"></script>
<script src="<?= e(admin_url('assets/vendor/cropperjs/cropper.min.js')) ?>"></script>
<script src="<?= e(admin_url('assets/vendor/tinymce/tinymce.min.js')) ?>"></script>
<script src="<?= e(asset_url('/admin/assets/admin.js')) ?>"></script>
<?php /* [V16-DATES] Aide à la frappe des dates ; sans effet s'il n'y en a pas. */ ?>
<?= Dates::script() ?>
</body>
</html>
<?php
}

/** Liste de liens internes pour l'éditeur (pages + fiches). */
function admin_link_list(): array
{
    $out = [];
    foreach (Pages::all() as $p) {
        $out[] = ['title' => ta('lnk_page') . ' — ' . (fa($p, 'title') ?: ta('com_untitled')), 'value' => Pages::url($p, I18n::$default)];
    }
    $mp = Pages::moduleP('projects');
    if ($mp) foreach (DB::all('SELECT * FROM projects ORDER BY sort') as $r) {
        $out[] = ['title' => ta('lnk_project') . ' — ' . html_entity_decode(fa($r, 'title')),
                  'value' => url('/' . I18n::$default . '/' . Pages::path($mp, I18n::$default) . '/' . ($r['slug_' . I18n::$default] ?: $r['slug_en']))];
    }
    $ma = Pages::moduleP('artists');
    if ($ma) foreach (DB::all('SELECT * FROM artists ORDER BY sort') as $r) {
        $out[] = ['title' => ta('lnk_artist') . ' — ' . $r['name'],
                  'value' => url('/' . I18n::$default . '/' . Pages::path($ma, I18n::$default) . '/' . ($r['slug_' . I18n::$default] ?: $r['slug_en']))];
    }
    return $out;
}

/**
 * Adresse publique d'une fiche, vue depuis l'administration.   [V30-VOIR-LA-PAGE]
 *
 * Rend l'adresse de la page où la fiche se voit vraiment sur le site, ou une
 * chaîne vide s'il n'y en a pas — auquel cas le bouton « Voir la page » ne
 * s'affiche pas du tout, plutôt que de mener nulle part.
 *
 * Trois cas, du plus complet au plus simple :
 *
 *   — un projet et un artiste ont chacun leur page : l'adresse se compose du
 *     chemin de la page de module et du raccourci (slug) de la fiche.
 *   — un événement et un membre de l'équipe n'ont pas de page à eux : le lien
 *     mène à la page qui les affiche, l'agenda ou l'équipe.
 *   — une catégorie n'est pas une page non plus : le lien mène à la page
 *     Projets, déjà filtrée sur cette catégorie.
 *
 * Rien n'est deviné. Si la page de module n'existe pas ou n'est pas en ligne,
 * la fonction renvoie une chaîne vide : mieux vaut pas de bouton du tout
 * qu'un bouton qui tombe sur une page introuvable.
 */
function admin_public_url(string $entity, array $row): string
{
    $module = [
        'project'  => 'projects',
        'artist'   => 'artists',
        'event'    => 'agenda',
        'team'     => 'team',
        'category' => 'projects',
    ][$entity] ?? '';
    if ($module === '') return '';

    $mp = Pages::moduleP($module);
    if (!$mp) return '';

    // La langue du site par défaut : c'est l'adresse « principale » de la
    // fiche, celle dont l'autre langue n'est qu'une traduction.
    $lang  = I18n::$default;
    $parts = [$lang];
    $path  = trim(Pages::path($mp, $lang), '/');
    if ($path !== '') $parts[] = $path;

    // Le raccourci de la fiche, avec repli sur l'anglais : une fiche neuve
    // peut n'avoir été nommée que d'un côté.
    $slug = trim((string)($row['slug_' . $lang] ?? ''));
    if ($slug === '') $slug = trim((string)($row['slug_en'] ?? ''));

    if ($entity === 'category') {
        return url('/' . implode('/', $parts)) . ($slug !== '' ? '?cat=' . urlencode($slug) : '');
    }
    if ($entity === 'event' || $entity === 'team') {
        return url('/' . implode('/', $parts));
    }
    if ($slug === '') return '';
    $parts[] = $slug;
    return url('/' . implode('/', $parts));
}

// ============================================================
// Rendu des champs d'édition
// ============================================================

function field_wrap(string $label, string $inner, string $help = '', bool $required = false): string
{
    return '<div class="f"><label class="f-label">' . e($label) . ($required ? ' <b class="req">*</b>' : '') . '</label>'
        . ($help !== '' ? '<p class="f-help">' . e($help) . '</p>' : '')
        . $inner . '</div>';
}

function langtabs(array $panels): string
{
    // $panels = ['en' => html, 'fr' => html]
    $tabs = '<div class="ltabs"><div class="ltabs-nav">';
    foreach (array_keys($panels) as $i => $lg) {
        $tabs .= '<button type="button" class="ltab' . ($i === 0 ? ' on' : '') . '" data-lang="' . e($lg) . '">'
              . e(ADMIN_LANGS_LABELS[$lg] ?? strtoupper($lg)) . '</button>';
    }
    $tabs .= '</div>';
    foreach ($panels as $lg => $html) {
        $tabs .= '<div class="lpane" data-lang="' . e($lg) . '"' . (array_key_first($panels) === $lg ? '' : ' hidden') . '>' . $html . '</div>';
    }
    return $tabs . '</div>';
}

function render_field(string $name, array $fdef, ?array $row, string $entityKey): string
{
    $type = $fdef['type'];
    // Les libellés des fichiers de configuration peuvent être bilingues :
    // tc() choisit la bonne langue, et laisse passer une simple chaîne.
    $label = tc($fdef['label'] ?? $name);
    $help = tc($fdef['help'] ?? '');
    $req = !empty($fdef['required']);
    $id = (int)($row['id'] ?? 0);
    $langs = I18n::$langs;

    switch ($type) {
        case 'text':
        case 'url':
            $v = (string)($row[$name] ?? '');
            return field_wrap($label, '<input type="text" name="' . e($name) . '" value="' . e($v) . '"'
                . ($type === 'url' ? ' placeholder="https://…"' : '') . '>', $help, $req);

        /* [V28-INSTAGRAM] Plusieurs adresses, une par ligne. Un champ d'une
           seule ligne obligerait à inventer un séparateur — une virgule, un
           point-virgule — que personne ne devine et qui casse au premier
           copier-coller. Une adresse par ligne se voit et se comprend. */
        case 'urls':
            $v = (string)($row[$name] ?? '');
            return field_wrap($label, '<textarea rows="3" name="' . e($name) . '" spellcheck="false"'
                . ' placeholder="https://…&#10;https://…">' . e($v) . '</textarea>', $help, $req);

        /* [V16-DATES] Champ texte et non « type=date » : un champ de date natif
           s'affiche dans la langue du navigateur, donc 07/04/1990 ici et
           04/07/1990 ailleurs. On demande donc explicitement le jour d'abord,
           partout et pour tout le monde. La saisie reste tolérante : les barres
           et les traits d'union sont acceptés et convertis. */
        case 'date':
            $v = (string)($row[$name] ?? '');
            return field_wrap($label, Dates::champ($name, $v, I18n::$admin),
                $help !== '' ? $help : ta('com_date_help', Dates::gabarit(I18n::$admin)), $req);

        case 'toggle':
            $v = (int)($row[$name] ?? 0);
            return '<div class="f f-toggle"><label class="switch"><input type="checkbox" name="' . e($name) . '" value="1"'
                . ($v ? ' checked' : '') . '><span></span></label><span class="f-label inline">' . e($label) . '</span></div>';

        case 'select_entity':
            $target = Content::def($fdef['entity']);
            $display = $fdef['display'];
            $opts = '<option value="">—</option>';
            foreach (DB::all('SELECT * FROM `' . $target['table'] . '` ORDER BY ' . $target['orderby']) as $r) {
                $lbl = $display === 'title' ? html_entity_decode(fa($r, 'title')) : (string)$r[$display];
                $opts .= '<option value="' . (int)$r['id'] . '"' . ((int)($row[$name] ?? 0) === (int)$r['id'] ? ' selected' : '') . '>'
                      . e($lbl) . '</option>';
            }
            return field_wrap($label, '<select name="' . e($name) . '">' . $opts . '</select>', $help, $req);

        case 'select_static':
            $cur = (string)($row[$name] ?? '');
            $opts = '';
            foreach (($fdef['options'] ?? []) as $val => $lbl) {
                $opts .= '<option value="' . e($val) . '"' . ($cur === (string)$val ? ' selected' : '') . '>' . e(tc($lbl)) . '</option>';
            }
            return field_wrap($label, '<select name="' . e($name) . '">' . $opts . '</select>', $help, $req);

        case 'rel_multi':
            $target = Content::def($fdef['entity']);
            $selected = $id ? Content::relIds($fdef, $id) : [];
            $inner = '<div class="checks">';
            foreach (DB::all('SELECT * FROM `' . $target['table'] . '` ORDER BY ' . $target['orderby']) as $r) {
                $lbl = $fdef['display'] === 'name' && isset($r['name']) && !isset($r['name_en'])
                    ? (string)$r['name']
                    : html_entity_decode(fa($r, $fdef['display']));
                $inner .= '<label class="check"><input type="checkbox" name="' . e($name) . '[]" value="' . (int)$r['id'] . '"'
                       . (in_array((int)$r['id'], $selected, true) ? ' checked' : '') . '> ' . e($lbl) . '</label>';
            }
            $inner .= '</div>';
            return field_wrap($label, $inner, $help, $req);

        case 'i18n_text':
            $panels = [];
            foreach ($langs as $lg) {
                $panels[$lg] = '<input type="text" name="' . e($name . '_' . $lg) . '" value="' . e((string)($row[$name . '_' . $lg] ?? '')) . '"'
                    . ' data-slug-source="' . e($name . ':' . $lg) . '">';
            }
            return field_wrap($label, langtabs($panels), $help, $req);

        case 'i18n_textarea':
            $panels = [];
            foreach ($langs as $lg) {
                $panels[$lg] = '<textarea rows="3" name="' . e($name . '_' . $lg) . '">' . e((string)($row[$name . '_' . $lg] ?? '')) . '</textarea>';
            }
            return field_wrap($label, langtabs($panels), $help, $req);

        case 'i18n_html':
            $panels = [];
            foreach ($langs as $lg) {
                $panels[$lg] = '<textarea class="wysiwyg" rows="10" name="' . e($name . '_' . $lg) . '" data-owner="' . e($entityKey . ':' . $id) . '">'
                    . htmlspecialchars((string)($row[$name . '_' . $lg] ?? ''), ENT_NOQUOTES) . '</textarea>';
            }
            return field_wrap($label, langtabs($panels), $help !== '' ? $help : ta('fld_html_hint'), $req);

        case 'i18n_slug':
            $panels = [];
            foreach ($langs as $lg) {
                $panels[$lg] = '<input type="text" class="slug" name="' . e($name . '_' . $lg) . '" value="' . e((string)($row[$name . '_' . $lg] ?? ''))
                    . '" data-slug-for="' . e(($fdef['from'] ?? 'title') . ':' . $lg) . '" placeholder="' . e(ta('fld_slug_ph')) . '">';
            }
            return field_wrap($label, langtabs($panels), ta('fld_slug_hint'), false);

        case 'image':
            $zone = $fdef['zone'] ?? 'cover';
            $imgId = (int)($row[$name] ?? 0);
            $img = $imgId ? Img::row($imgId) : null;
            $thumb = '';
            if ($img) { Img::ensure($img, 'thumb'); $thumb = Img::fileUrl($img, 'thumb', 'jpg') . '?t=' . time(); }
            /* [V30-SEO-AUTO] « auto » est l'image qui servira faute de choix —
               aujourd'hui l'image représentative, pour l'image de partage.
               On la montre en grisé plutôt que d'écrire « aucune image » :
               la case est vide, mais la fiche, elle, ne l'est pas. */
            $auto = (!$img && !empty($fdef['auto']) && is_array($fdef['auto'])) ? $fdef['auto'] : null;
            $autoThumb = '';
            if ($auto) { Img::ensure($auto, 'thumb'); $autoThumb = Img::fileUrl($auto, 'thumb', 'jpg') . '?t=' . time(); }
            $vide = $autoThumb !== ''
                ? '<img class="imgpick-auto" src="' . e($autoThumb) . '" alt=""><span class="imgpick-autotag">' . e(ta('fld_img_auto')) . '</span>'
                : '<span class="imgpick-empty">' . e(ta('fld_no_image')) . '</span>';
            $inner = '<div class="imgpick' . ($img ? ' has-img' : '') . '" data-owner="' . e($entityKey . ':' . $id) . '" data-zone="' . e($zone) . '" data-name="' . e($name) . '">'
                . '<input type="hidden" name="' . e($name) . '" value="' . ($imgId ?: '') . '">'
                . '<span class="imgpick-preview">' . ($thumb ? '<img src="' . e($thumb) . '" alt="">' : $vide) . '</span>'
                . '<span class="imgpick-actions">'
                . '<label class="btn small">' . e(ta('fld_choose_image')) . '<input type="file" accept="image/jpeg,image/png,image/webp" hidden></label>'
                . '<button type="button" class="btn small ghost js-crop"' . ($img ? '' : ' hidden') . ' data-img="' . $imgId . '">' . e(ta('fld_crop')) . '</button>'
                . '<button type="button" class="btn small ghost js-imgremove"' . ($img ? '' : ' hidden') . '>' . e(ta('fld_remove')) . '</button>'
                . '</span><span class="hint">' . e(ta('fld_img_hint')) . '</span></div>';
            return field_wrap($label, $inner, $help, $req);

        case 'gallery':
            $zone = $fdef['zone'] ?? 'gallery';
            // id 0 est valide pour la zone « site » (logos partenaires)
            $imgs = ($id || $entityKey === 'site') ? Img::gallery($entityKey, $id, $zone) : [];
            $items = '';
            foreach ($imgs as $g) {
                Img::ensure($g, 'thumb');
                $items .= gallery_item_html($g);
            }
            $inner = '<div class="gal" data-owner="' . e($entityKey . ':' . $id) . '" data-zone="' . e($zone) . '">'
                . '<div class="gal-grid">' . $items . '</div>'
                . '<label class="dropzone">' . e(ta('fld_gal_drop'))
                . '<input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden></label>'
                . '<p class="hint">' . e(ta('fld_gal_hint')) . '</p></div>';
            return field_wrap($label, $inner, $help, false);

        case 'videos':
            /* [12.08.2026] Le même type sert deux emplacements : les vidéos
               publiques et la captation réservée au Catalogue. « catalogue »
               dans la définition du champ dit lequel on dessine, et la liste
               n'affiche que les siennes. Sans ce tri, les deux emplacements
               montreraient la même chose et on ne saurait plus lequel remplir. */
            $catOnly = !empty($fdef['catalogue']);
            $vids = $id ? VideoLib::forOwner($entityKey, $id) : [];
            $vids = array_values(array_filter($vids, fn($v) => !empty($v['catalog_only']) === $catOnly));
            $items = '';
            foreach ($vids as $v) $items .= video_item_html($v);
            $feedBtn = setting('yt_channel_id') !== ''
                ? '<button type="button" class="btn small ghost js-vid-feed">' . e(ta('fld_vid_feed')) . '</button>' : '';
            $inner = '<div class="vids" data-owner="' . e($entityKey . ':' . $id) . '"'
                . ' data-catalogue="' . ($catOnly ? '1' : '0') . '">'
                . '<div class="vids-list">' . $items . '</div>'
                . '<label class="dropzone js-vid-drop">' . e(ta('fld_vid_drop'))
                . '<input type="file" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov,.ogv" multiple hidden></label>'
                . '<div class="vids-add"><input type="text" class="js-vid-url" placeholder="' . e(ta('fld_vid_url')) . '">'
                . '<button type="button" class="btn small js-vid-add">' . e(ta('fld_vid_add')) . '</button>' . $feedBtn . '</div>'
                . '<div class="vids-feed" hidden></div>'
                . '<p class="hint">' . ta('fld_vid_hint') . '</p></div>';
            return field_wrap($label, $inner, $help, false);

        case 'documents':
            /* [V31-DOC-LIEN] Deux façons de mettre un document en
               téléchargement, et la seconde n'existait pas : le déposer ici,
               ou coller le lien de l'endroit où il vit déjà. La ligne de
               saisie est calquée sur celle des vidéos, au même endroit sous
               la zone de dépôt, pour que le geste soit le même partout.

               [V31-PRESSE] Le même bloc sert maintenant deux fois sur une
               fiche projet : les documents à télécharger, et la revue de
               presse. Ce qui change entre les deux tient à la « zone » — un
               mot dans l'adresse, invisible à l'écran — et aux quatre
               phrases qui l'entourent : on ne dépose pas un rider comme on
               colle l'adresse d'un article. Le préfixe des clefs de texte
               suffit à les changer toutes les quatre. */
            $zone = $fdef['zone'] ?? Docs::ZONE_DEFAUT;
            $mots = $fdef['mots'] ?? 'doc';   // « doc » ou « press »
            $docs = $id ? Docs::forOwner($entityKey, $id, $zone) : [];
            $items = '';
            foreach ($docs as $d) $items .= doc_item_html($d);
            $inner = '<div class="docsadmin" data-owner="' . e($entityKey . ':' . $id) . '" data-zone="' . e($zone) . '">'
                . '<div class="docs-list">' . $items . '</div>'
                . '<label class="dropzone">' . e(ta('fld_' . $mots . '_drop'))
                . '<input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip" multiple hidden></label>'
                . '<div class="docs-add"><input type="text" class="js-doc-url" placeholder="' . e(ta('fld_' . $mots . '_url')) . '">'
                . '<button type="button" class="btn small js-doc-add">' . e(ta('fld_' . $mots . '_add')) . '</button></div>'
                . '<p class="hint">' . e(ta('fld_' . $mots . '_hint')) . '</p></div>';
            return field_wrap($label, $inner, $help, false);

        case 'seo':
            /* [V30-SEO-AUTO] Les deux cases restent vides la plupart du temps,
               et c'est très bien : le titre de la pièce, son introduction et
               sa photo suffisent. Le problème était qu'une case vide ne disait
               pas ce qu'elle allait faire — on ne savait pas si Google verrait
               quelque chose, ni quoi.

               Le texte gris clair des cases n'est donc plus une consigne mais
               le résultat : c'est mot pour mot ce que le site publiera si on
               n'y touche pas. Il est calculé par les mêmes fonctions que la
               page publique appelle, dans la langue de chaque onglet. Écrire
               par-dessus reprend la main ; effacer la rend à l'automatique. */
            $panels = [];
            foreach ($langs as $lg) {
                $autoT = Content::seoTitle($row, $entityKey, $lg);
                $autoD = Content::seoDesc($row, $lg);
                $valD  = (string)($row['meta_desc_' . $lg] ?? '');
                $panels[$lg] = '<input type="text" name="meta_title_' . $lg . '" value="' . e((string)($row['meta_title_' . $lg] ?? ''))
                    . '" placeholder="' . e($autoT !== '' ? $autoT : ta('fld_seo_title_ph')) . '">'
                    . '<textarea rows="3" class="js-seo-desc" name="meta_desc_' . $lg
                    . '" placeholder="' . e($autoD !== '' ? $autoD : ta('fld_seo_desc_ph')) . '">' . e($valD) . '</textarea>'
                    . '<p class="hint seo-count"><b>0</b> / ' . Content::SEO_DESC_MAX . '</p>';
            }
            /* L'image de partage suit la même règle : laissée vide, c'est
               l'image représentative de la fiche qui part sur les réseaux.
               Elle s'affiche donc en aperçu, en grisé, pour qu'on la voie
               au lieu de lire « aucune image » sur une fiche qui en a une. */
            $ogAuto = empty($row['og_image_id']) ? Content::seoImage($row) : null;
            $og = render_field('og_image_id', ['type' => 'image', 'zone' => 'og',
                'label' => ta('fld_seo_og'),
                'auto'  => $ogAuto,
                'help' => ta('fld_seo_og_help')], $row, $entityKey);
            return '<details class="seo-block"><summary>' . e($label) . '</summary>'
                . field_wrap(ta('fld_seo_meta'), langtabs($panels), ta('fld_seo_auto_help'))
                . $og . '</details>';
    }
    return '';
}

function gallery_item_html(array $g): string
{
    $thumb = Img::fileUrl($g, 'thumb', 'jpg') . '?t=' . time();
    return '<div class="gal-item" data-id="' . (int)$g['id'] . '">'
        . '<img src="' . e($thumb) . '" alt="">'
        . '<span class="gal-tools">'
        . '<button type="button" class="icon-btn js-crop" data-img="' . (int)$g['id'] . '" title="' . e(ta('fld_crop')) . '">' . Ico::ciseaux() . '</button>'
        . '<button type="button" class="icon-btn js-img-del" title="' . e(ta('com_delete')) . '">×</button>'
        . '</span>'
        . '<input type="text" class="gal-alt js-alt" data-lang="fr" value="' . e((string)$g['alt_fr']) . '" placeholder="' . e(ta('fld_alt', 'FR')) . '">'
        . '<input type="text" class="gal-alt js-alt" data-lang="en" value="' . e((string)$g['alt_en']) . '" placeholder="' . e(ta('fld_alt', 'EN')) . '">'
        . '</div>';
}

function video_item_html(array $v): string
{
    $isFile = ($v['provider'] ?? '') === 'file';
    $link   = $isFile ? (string)$v['url'] : ((string)$v['url'] ?: VideoLib::watchUrl($v['provider'], $v['vid']));
    $kind   = $isFile ? ta('fld_vid_file') : ucfirst($v['provider']);
    $title  = $v['title'] !== '' ? $v['title'] : ($isFile ? basename((string)$v['url']) : $v['vid']);
    if ($v['thumb'] !== '') {
        $preview = '<img src="' . e($v['thumb']) . '" alt="">';
    } elseif ($isFile) {
        $preview = '<video class="vid-thumbvid" src="' . e($v['url']) . '#t=0.1" muted preload="metadata"></video>';
    } else {
        $preview = '<span class="vid-noimg"></span>';
    }
    return '<div class="vid-item" data-id="' . (int)$v['id'] . '">'
        . '<span class="vid-drag" title="' . e(ta('com_reorder')) . '">⋮⋮</span>'
        . $preview
        . '<span class="vid-meta"><strong>' . e($title) . '</strong><em>' . e($kind) . '</em></span>'
        . '<label class="vid-secs" title="' . e(ta('fld_vid_secs')) . '">'
            . '<input type="number" class="js-vid-secs" min="1" max="60" step="1" value="' . (int)($v['duration'] ?? 6) . '"><span>s</span></label>'
        . '<a class="icon-btn" href="' . e($link) . '" target="_blank" title="' . e(ta('com_view')) . '">' . Ico::ext() . '</a>'
        . '<button type="button" class="icon-btn js-vid-del" title="' . e(ta('com_delete')) . '">×</button>'
        . '</div>';
}

function doc_item_html(array $d): string
{
    $lien = Docs::estLien($d);
    $cover = !empty($d['cover_image_id']) ? Img::row((int)$d['cover_image_id']) : null;
    $thumb = '';
    if ($cover) { Img::ensure($cover, 'thumb'); $thumb = Img::fileUrl($cover, 'thumb', 'jpg'); }
    $badge = $d['ext'] !== '' ? strtoupper($d['ext']) : ($lien ? ta('fld_doc_lien') : '');

    /* [V31-DOC-LIEN] Sous les deux titres : le nom du fichier déposé, qui ne
       se modifie pas, ou l'adresse du lien, qui se corrige sur place. Un
       lien mal collé se répare alors sans supprimer la ligne ni reperdre
       les titres qu'on venait d'écrire. */
    $bas = $lien
        ? '<input type="text" class="js-doc-link" value="' . e((string)$d['url']) . '" placeholder="' . e(ta('fld_doc_url')) . '">'
        : '<em>' . e($d['filename']) . ' · ' . e(Docs::human((int)$d['size'])) . '</em>';

    return '<div class="doc-item' . ($lien ? ' doc-lien' : '') . '" data-id="' . (int)$d['id'] . '">'
        . '<span class="vid-drag" title="' . e(ta('com_reorder')) . '">⋮⋮</span>'
        . ($thumb ? '<img src="' . e($thumb) . '" alt="">' : '<span class="doc-badge">' . e($badge) . '</span>')
        . '<span class="doc-fields">'
        . '<input type="text" class="js-doc-title" data-lang="fr" value="' . e((string)$d['title_fr']) . '" placeholder="' . e(ta('fld_doc_title', 'FR')) . '">'
        . '<input type="text" class="js-doc-title" data-lang="en" value="' . e((string)$d['title_en']) . '" placeholder="' . e(ta('fld_doc_title', 'EN')) . '">'
        . $bas . '</span>'
        . '<a class="icon-btn" href="' . e(Docs::fileUrl($d)) . '" target="_blank" title="' . e(ta('com_open')) . '">' . Ico::ext() . '</a>'
        . '<button type="button" class="icon-btn js-doc-del" title="' . e(ta('com_delete')) . '">×</button>'
        . '</div>';
}
