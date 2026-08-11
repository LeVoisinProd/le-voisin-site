<?php
/**
 * Le Voisin — contrôleur du site public.   [V6-FORMULAIRES]
 * URLs : /{langue}/{chemin...} — ex. /fr/projets/bestiarium
 *
 * V6-FORMULAIRES (29.07.2026) : nouveau module « forms_portal ». La page
 * Formulaires n'affiche plus deux vignettes muettes mais deux blocs
 * expliqués : dépenses d'un côté, espace collaborateur de l'autre.
 *
 * V5-ADMIN (29.07.2026) : nouveau module « admin_portal » — la page
 * Administration, avec sa connexion et le choix entre le tableau de bord
 * et le CMS. Elle est exclue du plan du site (sitemap).
 *
 * V4-RENVOI (29.07.2026) : après un envoi réussi, les coordonnées sont
 * gardées en session pour proposer « Envoyer un autre justificatif ».
 */
require __DIR__ . '/app/bootstrap.php';
I18n::init();

$reqPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$basePath = parse_url(cfg('base_url', ''), PHP_URL_PATH) ?: '';
if ($basePath && str_starts_with($reqPath, $basePath)) {
    $reqPath = substr($reqPath, strlen($basePath));
}
$reqPath = trim($reqPath, '/');

// ---- Fichiers spéciaux -------------------------------------------------------
if ($reqPath === 'sitemap.xml') { lv_sitemap(); }
if ($reqPath === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /admin/\nDisallow: /install/\nSitemap: " . url('/sitemap.xml') . "\n";
    exit;
}

$segments = $reqPath === '' ? [] : explode('/', $reqPath);

// ---- Langue ------------------------------------------------------------------
if (!$segments || !in_array($segments[0], I18n::$langs, true)) {
    $lang = I18n::browserLang();
    redirect('/' . $lang . ($reqPath !== '' ? '/' . $reqPath : ''));
}
I18n::setLang(array_shift($segments));

// ---- Résolution de la page ---------------------------------------------------
[$page, $rest] = Pages::resolve($segments, I18n::$lang);

$entity = null;      // fiche projet/artiste éventuelle
$entityType = null;

if ($page && $rest) {
    if (count($rest) === 1 && in_array($page['module'], ['projects', 'artists'], true)) {
        $slug = $rest[0];
        $table = $page['module'] === 'projects' ? 'projects' : 'artists';
        $entity = DB::one(
            "SELECT * FROM `$table` WHERE visible = 1 AND (slug_" . I18n::$lang . ' = ? OR slug_' . I18n::$default . ' = ?)',
            [$slug, $slug]
        );
        $entityType = $page['module'] === 'projects' ? 'project' : 'artist';
        if (!$entity) $page = null;
    } else {
        $page = null;
    }
}
if (!$page) { lv_render_404(); }

// ---- URLs alternatives (hreflang + sélecteur de langue) ----------------------
$alt = [];
foreach (I18n::$langs as $lg) {
    if ($entity) {
        $slugAlt = trim((string)($entity['slug_' . $lg] ?? ''));
        if ($slugAlt === '') $slugAlt = trim((string)($entity['slug_' . I18n::$default] ?? ''));
        $alt[$lg] = url('/' . $lg . '/' . Pages::path($page, $lg) . '/' . $slugAlt);
    } else {
        $alt[$lg] = Pages::url($page, $lg);
    }
}

// ---- Meta SEO ----------------------------------------------------------------
/* [V30-SEO-AUTO] Les trois lignes qui suivent décidaient chacune dans leur coin
   de ce que Google lirait. Elles posent maintenant la question à Content, qui
   répond la même chose à l'administration : ce que la fiche montre en gris
   clair dans les cases vides du bloc « Référencement » est exactement ce qui
   sort ici. La description, en particulier, était coupée à 250 caractères au
   couteau, en plein milieu d'un mot ; elle est ramenée à 160 — la longueur
   au-delà de laquelle Google tranche de toute façon — et sur un mot entier. */
$metaRow   = $entity ?? $page;
$metaTitle = f($metaRow, 'meta_title') ?: Content::seoTitle($metaRow, (string)$entityType, I18n::$lang);
$metaDesc  = f($metaRow, 'meta_desc')  ?: Content::seoDesc($metaRow, I18n::$lang);
$ogImg     = Content::seoImage($metaRow);
$meta = [
    'title' => $metaTitle,
    'desc'  => $metaDesc,
    'url'   => $alt[I18n::$lang],
    'og'    => $ogImg ? Img::fileUrl($ogImg, 'og', 'jpg') : '',
    'alt'   => $alt,
];
if ($ogImg) Img::ensure($ogImg, 'og');

// ---- Traitement des formulaires ---------------------------------------------
$formState = ['sent' => isset($_GET['sent']), 'errors' => [], 'old' => [], 'again' => false, 'resumed' => false];
if (in_array($page['module'], ['form_infos', 'form_expenses'], true)) {
    $url = '/' . I18n::$lang . '/' . Pages::path($page, I18n::$lang);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $res = Forms::handle($page['module'], $_POST, $_FILES);
        if ($res['ok']) {
            // Les coordonnées sont gardées pour un éventuel envoi suivant :
            // une dépense = un envoi, autant que cela coûte le moins possible.
            Forms::remember($page['module'], $_POST);
            redirect($url . '?sent=1');
        }
        $formState = ['sent' => false, 'errors' => $res['errors'], 'old' => $_POST,
                      'again' => false, 'resumed' => false];

    } elseif (isset($_GET['vider'])) {
        Forms::forget($page['module']);
        redirect($url);

    } elseif (isset($_GET['suite'])) {
        // Retour au formulaire avec l'identité et le paiement déjà remplis.
        $old = Forms::recall($page['module']);
        if (!$old) redirect($url);
        $formState = ['sent' => false, 'errors' => [], 'old' => $old,
                      'again' => false, 'resumed' => true];

    } elseif ($formState['sent']) {
        $formState['again'] = (bool)Forms::recall($page['module']);
    }
    $formState['url'] = $url;
}

// ---- Rendu -------------------------------------------------------------------
function view(string $tpl, array $vars = []): never
{
    global $meta, $page;
    require_once LV_APP . '/views/partials/helpers.php';
    extract($vars, EXTR_SKIP);
    ob_start();
    include LV_APP . '/views/' . $tpl . '.php';
    $content = ob_get_clean();
    include LV_APP . '/views/layout.php';
    exit;
}

function lv_render_404(): never
{
    global $meta, $page;
    require_once LV_APP . '/views/partials/helpers.php';
    http_response_code(404);
    $home = Pages::home();
    $page = $home ?: ['template' => 'standard', 'module' => null];
    $meta = [
        'title' => t('not_found_title') . ' — ' . setting('site_name', 'Le Voisin'),
        'desc' => '', 'url' => '', 'og' => '', 'alt' => [],
    ];
    ob_start();
    include LV_APP . '/views/404.php';
    $content = ob_get_clean();
    include LV_APP . '/views/layout.php';
    exit;
}

/** Image d'un événement : la sienne, sinon projet, sinon artiste. */
function event_img(array $ev): ?array
{
    if (!empty($ev['image_id']) && ($img = Img::row((int)$ev['image_id']))) return $img;
    if (!empty($ev['project_id'])) {
        $p = DB::one('SELECT cover_image_id FROM projects WHERE id = ?', [$ev['project_id']]);
        if ($p && $p['cover_image_id'] && ($img = Img::row((int)$p['cover_image_id']))) return $img;
    }
    if (!empty($ev['artist_id'])) {
        $a = DB::one('SELECT cover_image_id FROM artists WHERE id = ?', [$ev['artist_id']]);
        if ($a && $a['cover_image_id'] && ($img = Img::row((int)$a['cover_image_id']))) return $img;
    }
    return null;
}

function events_query(string $where, array $params, string $order): array
{
    return DB::all(
        'SELECT e.*, a.name AS artist_name, a.slug_en AS artist_slug_en, a.slug_fr AS artist_slug_fr,
                p.title_en AS project_title_en, p.title_fr AS project_title_fr,
                p.intro_en AS project_intro_en, p.intro_fr AS project_intro_fr,
                p.slug_en AS project_slug_en, p.slug_fr AS project_slug_fr
         FROM events e
         LEFT JOIN artists a ON a.id = e.artist_id AND a.visible = 1
         LEFT JOIN projects p ON p.id = e.project_id AND p.visible = 1
         WHERE e.visible = 1 ' . $where . ' ORDER BY ' . $order,
        $params
    );
}

switch (true) {
    // ----- Fiche projet -----
    case $entityType === 'project':
        $pf = Content::def('project')['fields'];
        view('project', [
            'project'   => $entity,
            'cover'     => $entity['cover_image_id'] ? Img::row((int)$entity['cover_image_id']) : null,
            'cats'      => Content::related('category', $pf['categories'], (int)$entity['id'], false),
            'artists'   => Content::related('artist', $pf['artists'], (int)$entity['id']),
            'gallery'   => Img::gallery('project', (int)$entity['id']),
            'videos'    => VideoLib::forOwner('project', (int)$entity['id']),
            /* [V31-PRESSE] Deux listes désormais : les documents à télécharger
               et la revue de presse. Chacune va chercher la sienne. */
            'documents' => Docs::forOwner('project', (int)$entity['id'], 'doc'),
            'press'     => Docs::forOwner('project', (int)$entity['id'], 'press'),
            'events'    => events_query('AND e.project_id = ?', [$entity['id']], 'e.date_sort ASC'),
        ]);

    // ----- Fiche artiste -----
    case $entityType === 'artist':
        $af = Content::def('project')['fields'];
        $projects = Content::related('project', $af['artists'], (int)$entity['id'], true, true);
        $projIds = array_map(fn($p) => (int)$p['id'], $projects);
        $whereEv = 'AND (e.artist_id = ?' . ($projIds ? ' OR e.project_id IN (' . implode(',', $projIds) . ')' : '') . ')';
        view('artist', [
            'artist'    => $entity,
            'cover'     => $entity['cover_image_id'] ? Img::row((int)$entity['cover_image_id']) : null,
            'projects'  => $projects,
            'gallery'   => Img::gallery('artist', (int)$entity['id']),
            'videos'    => VideoLib::forOwner('artist', (int)$entity['id']),
            'documents' => Docs::forOwner('artist', (int)$entity['id']),
            'events'    => events_query($whereEv, [$entity['id']], 'e.date_sort ASC'),
        ]);

    // ----- Modules -----
    case $page['module'] === 'projects':
        $cat = trim((string)($_GET['cat'] ?? ''));
        $former = isset($_GET['anciens']) || isset($_GET['former']);
        $status = $former ? 'former' : 'current';
        $hasFormer = (int)DB::val("SELECT COUNT(*) FROM projects WHERE visible = 1 AND status = 'former'") > 0;
        $projects = DB::all('SELECT * FROM projects WHERE visible = 1 AND status = ? ORDER BY sort, id', [$status]);
        $cats = DB::all('SELECT * FROM categories ORDER BY sort, id');
        if ($cat !== '') {
            $catRow = null;
            foreach ($cats as $c) if ($cat === $c['slug_en'] || $cat === $c['slug_fr']) $catRow = $c;
            if ($catRow) {
                $ids = array_map('intval', array_column(
                    DB::all('SELECT project_id FROM project_categories WHERE category_id = ?', [$catRow['id']]), 'project_id'));
                $projects = array_values(array_filter($projects, fn($p) => in_array((int)$p['id'], $ids, true)));
            }
        }
        view('projects', ['projects' => $projects, 'cats' => $cats, 'activeCat' => $cat, 'former' => $former, 'hasFormer' => $hasFormer]);

    case $page['module'] === 'artists':
        $former = isset($_GET['anciennes']) || isset($_GET['former']);
        $status = $former ? 'former' : 'current';
        $hasFormer = (int)DB::val("SELECT COUNT(*) FROM artists WHERE visible = 1 AND status = 'former'") > 0;
        view('artists', [
            'artists' => DB::all('SELECT * FROM artists WHERE visible = 1 AND status = ? ORDER BY sort, id', [$status]),
            'former'  => $former,
            'hasFormer' => $hasFormer,
        ]);

    case $page['module'] === 'agenda':
        $fArtist = (int)($_GET['artist'] ?? 0);
        $fProject = (int)($_GET['project'] ?? 0);
        $where = ''; $params = [];
        if ($fArtist) { $where .= ' AND e.artist_id = ?'; $params[] = $fArtist; }
        if ($fProject) { $where .= ' AND e.project_id = ?'; $params[] = $fProject; }
        $today = date('Y-m-d');

        // [V31-ARCHIVES] La page « En tournée » se lit sur une saison, pas sur
        // dix ans. Elle montre donc ce qui vient, puis les dates déjà jouées de
        // l'année en cours — celles dont on parle encore, dont la presse et les
        // programmateurs se souviennent. Tout ce qui est antérieur au 1er
        // janvier ne disparaît pas : il passe derrière le lien « Projets
        // réalisés », qui ouvre l'historique complet, année par année.
        //
        // La frontière est le 1er janvier et non « il y a douze mois » : une
        // année de tournée est une unité que le métier reconnaît, une fenêtre
        // glissante n'en est pas une. Elle se déplace toute seule le jour de
        // l'an, sans rien à archiver à la main.
        $débutAnnée = date('Y') . '-01-01';
        $archives = isset($_GET['archives']) || isset($_GET['archive']);

        $upcoming = $archives ? [] : events_query(
            $where . ' AND COALESCE(e.date_end, e.date_sort) >= ?',
            array_merge($params, [$today]), 'e.date_sort ASC'
        );
        $past = $archives
            ? events_query($where . ' AND COALESCE(e.date_end, e.date_sort) < ?',
                array_merge($params, [$débutAnnée]), 'e.date_sort DESC')
            : events_query($where . ' AND COALESCE(e.date_end, e.date_sort) < ?
                                     AND COALESCE(e.date_end, e.date_sort) >= ?',
                array_merge($params, [$today, $débutAnnée]), 'e.date_sort DESC');

        // Le lien vers l'historique ne s'affiche que s'il y a un historique, et
        // il tient compte du filtre en cours : proposé sous une pièce qui n'a
        // jamais joué avant cette année, il ouvrirait une page vide.
        $hasArchives = (int)DB::val(
            'SELECT COUNT(*) FROM events e WHERE e.visible = 1
             AND COALESCE(e.date_end, e.date_sort) < ?' . $where,
            array_merge([$débutAnnée], $params)
        ) > 0;

        // [V31-FILTRES] Les deux menus ne proposent que ce que la page peut
        // réellement montrer. Ils listaient jusqu'ici toute la base : on y
        // trouvait des pièces jouées il y a trois ans et des artistes qui ne
        // travaillent plus avec le bureau. Un menu de filtre n'est pas un
        // catalogue.
        //
        // Ils se déduisent donc des dates elles-mêmes, sans liste à tenir à
        // jour : une pièce entre au menu le jour où on lui inscrit une date de
        // l'année, et en sort au changement d'année — où elle réapparaît
        // aussitôt dans le menu de l'historique. La borne est la même que
        // celle de l'affichage, si bien qu'un choix proposé ne donne jamais
        // une page vide. Les conditions de visibilité sont celles
        // d'events_query(), pour la même raison.
        //
        // Le « OR id = ? » garde l'option retenue même si elle sort de la
        // borne : sans lui, un lien vers ?project=… désignant une pièce
        // ancienne afficherait « Tout » tout en filtrant — le menu mentirait.
        $borne = 'EXISTS (SELECT 1 FROM events e WHERE e.visible = 1
                          AND COALESCE(e.date_end, e.date_sort) ' . ($archives ? '<' : '>=') . ' ?
                          AND e.%s = %s.id)';
        view('agenda', [
            'upcoming' => $upcoming, 'past' => $past,
            'archives' => $archives, 'hasArchives' => $hasArchives,
            'artistsF' => DB::all(
                'SELECT id, name FROM artists a WHERE visible = 1 AND ('
                . sprintf($borne, 'artist_id', 'a') . ' OR id = ?) ORDER BY name',
                [$débutAnnée, $fArtist]
            ),
            'projectsF'=> DB::all(
                'SELECT id, title_en, title_fr FROM projects p WHERE visible = 1 AND ('
                . sprintf($borne, 'project_id', 'p') . ' OR id = ?) ORDER BY title_en',
                [$débutAnnée, $fProject]
            ),
            'fArtist' => $fArtist, 'fProject' => $fProject,
        ]);

    case $page['module'] === 'team':
        view('team', ['members' => DB::all('SELECT * FROM team_members WHERE visible = 1 ORDER BY sort, id')]);

    case $page['module'] === 'pro':
        view('pro', []);

    // ----- Page « Espaces dédiés » : les deux portes d'entrée -----
    // Une carte vers /espace/ (artistes et technicien·nes), une vers le
    // catalogue (programmateur·rices). Le gabarit lit lui-même le réglage
    // « catalogue_url » : tant qu'il est vide, la seconde carte affiche
    // « Bientôt » au lieu d'un lien mort — même logique que pro.php avec
    // pro_projects_url. Rien à passer depuis ici.
    case $page['module'] === 'espaces':
        view('espaces', []);

    // ----- Page « Administration » : connexion, puis choix tableau de bord / CMS -----
    // Une seule adresse pour toute l'équipe. Le lien n'est que dans le pied de
    // page ; la page elle-même est hors du menu (in_nav = 0). L'authentification
    // est celle de l'administration du site (table users), sans code nouveau :
    // limitation des essais, jeton anti-CSRF et remise à niveau du mot de passe
    // sont déjà dans la classe Auth.
    case $page['module'] === 'admin_portal':
        $admUrl   = Pages::url($page);
        $admState = ['url' => $admUrl, 'error' => '', 'email' => ''];

        if (isset($_GET['deconnexion'])) { Auth::logout(); redirect($admUrl); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::check()) {
            Auth::requireCsrf();
            $admEmail = trim((string)($_POST['email'] ?? ''));
            if (Auth::throttled()) {
                $admState['error'] = t('adm_throttle');
                $admState['email'] = $admEmail;
            } elseif (Auth::login($admEmail, (string)($_POST['password'] ?? ''))) {
                redirect($admUrl);           // on recharge : plus de renvoi de formulaire
            } else {
                $admState['error'] = t('adm_bad');
                $admState['email'] = $admEmail;
            }
        }
        view('admin_portal', ['state' => $admState]);

    // ----- Page « Formulaires » : deux accès expliqués -----
    case $page['module'] === 'forms_portal':
        view('forms_portal', [
            'gallery'   => Img::gallery('page', (int)$page['id']),
            'documents' => Docs::forOwner('page', (int)$page['id']),
        ]);

    case $page['module'] === 'form_infos':
        // Le formulaire d'infos personnelles est désormais dans l'espace
        // collaborateur (connexion requise), premier onglet.   [V35-FICHE-ONGLET]
        redirect(MemberAuth::check() ? '/espace/#partie-infos' : '/espace/login.php');

    case $page['module'] === 'form_expenses':
        view('form', ['form' => $page['module'], 'def' => Forms::def($page['module']), 'state' => $formState]);

    // ----- Accueil -----
    case $page['template'] === 'home':
        $today = date('Y-m-d');
        view('home', [
            'heroVideos' => VideoLib::forOwner('page', (int)$page['id']),
            'projects' => DB::all('SELECT * FROM projects WHERE visible = 1 ORDER BY sort, id LIMIT 6'),
            'events'   => events_query('AND COALESCE(e.date_end, e.date_sort) >= ?', [$today], 'e.date_sort ASC'),
        ]);

    // ----- Page simple -----
    default:
        view('page', [
            'gallery' => Img::gallery('page', (int)$page['id']),
            'documents' => Docs::forOwner('page', (int)$page['id']),
            'children' => Pages::children((int)$page['id'], true),
        ]);
}

// ---- Sitemap -----------------------------------------------------------------
function lv_sitemap(): never
{
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    $entry = function (array $urls, string $lastmod = '') {
        foreach ($urls as $lg => $u) {
            echo "<url><loc>" . e($u) . "</loc>";
            foreach ($urls as $lg2 => $u2) {
                echo '<xhtml:link rel="alternate" hreflang="' . $lg2 . '" href="' . e($u2) . '"/>';
            }
            if ($lastmod) echo '<lastmod>' . substr($lastmod, 0, 10) . '</lastmod>';
            echo "</url>\n";
        }
    };
    foreach (Pages::all() as $p) {
        if (!$p['visible']) continue;
        // Les pages d'accès privé n'ont rien à faire dans le plan du site.
        if (in_array((string)$p['module'], ['admin_portal', 'pro'], true)) continue;
        $urls = [];
        foreach (I18n::$langs as $lg) $urls[$lg] = Pages::url($p, $lg);
        $entry($urls, (string)$p['updated_at']);
    }
    foreach (['projects' => 'projects', 'artists' => 'artists'] as $module => $table) {
        $mp = Pages::moduleP($module);
        if (!$mp) continue;
        foreach (DB::all("SELECT * FROM `$table` WHERE visible = 1") as $row) {
            $urls = [];
            foreach (I18n::$langs as $lg) {
                $slug = trim((string)($row['slug_' . $lg] ?? '')) ?: trim((string)($row['slug_' . I18n::$default] ?? ''));
                $urls[$lg] = url('/' . $lg . '/' . Pages::path($mp, $lg) . '/' . $slug);
            }
            $entry($urls, (string)($row['updated_at'] ?? ''));
        }
    }
    echo '</urlset>';
    exit;
}
