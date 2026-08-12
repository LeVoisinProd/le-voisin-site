<?php
/**
 * Le Catalogue professionnel, en point d'entrée autonome.   [V42-CATALOGUE]
 *
 * POURQUOI CE FICHIER EXISTE PLUTOT QU'UN MODULE DANS index.php
 *
 * Le Catalogue avait d'abord été écrit comme un module du routeur, à la
 * manière des projets ou de l'agenda. C'était la bonne architecture et elle
 * s'est révélée impraticable : le cache d'opcode du serveur garde index.php
 * compilé en mémoire et refuse de le relire. Ni l'installateur, ni un dépôt
 * par FTP, ni un redémarrage de PHP depuis le panneau n'y ont rien changé, et
 * le site a continué d'exécuter un index.php vieux de plusieurs jours pendant
 * tout un après-midi.
 *
 * Un fichier au nom neuf, lui, n'a pas d'entrée en mémoire : il est compilé à
 * la première requête. C'est vérifié, pas supposé — un fichier de diagnostic
 * déposé dans le même paquet qu'index.php a répondu immédiatement, l'autre
 * jamais.
 *
 * Ce fichier ne demande donc RIEN à index.php, RIEN au .htaccess, et RIEN au
 * CMS : pas de page à créer, pas de module à choisir. Il s'amorce lui-même,
 * comme installateur.php le fait déjà pour la même raison d'indépendance.
 *
 * L'adresse est le seul prix à payer :
 *
 *     https://le-voisin.com/catalogue.php            la grille
 *     https://le-voisin.com/catalogue.php?p=slug     une fiche
 *
 * C'est une adresse qu'on écrit dans un e-mail à un programmateur, pas une
 * adresse qu'on annonce sur le site. Elle fait donc le travail.
 *
 * Le jour où le cache sera vidé pour de bon, le module d'index.php reprendra
 * le service et ce fichier pourra disparaître. Les vues, les deux
 * bibliothèques, le style et les traductions sont les mêmes des deux côtés :
 * il n'y a rien à réécrire, seulement une porte à refermer.
 */

require __DIR__ . '/app/bootstrap.php';
I18n::init();

/* La langue : celle demandée, sinon celle du navigateur. Le Catalogue n'a pas
   d'adresse par langue, donc elle se choisit par paramètre et se garde en
   session le temps de la visite. */
session_boot();
$lg = strtolower(trim((string)($_GET['lang'] ?? '')));
if (in_array($lg, I18n::$langs, true)) $_SESSION['lv_cat_lang'] = $lg;
$lg = (string)($_SESSION['lv_cat_lang'] ?? '');
I18n::setLang(in_array($lg, I18n::$langs, true) ? $lg : I18n::browserLang());

/* ---------------------------------------------------------------------------
   Les vues attendent un $page, parce qu'elles ont été écrites pour le routeur.
   On leur en fabrique un : il porte le titre et sert de base aux liens de
   retour. Rien d'autre n'en est lu.
   --------------------------------------------------------------------------- */
$titre = setting('catalogue_titre', '') ?: 'Catalogue';
$page = [
    'id' => 0, 'module' => 'catalog', 'template' => 'standard',
    'title_fr' => $titre, 'title_en' => $titre,
    'body_fr' => '', 'body_en' => '',
    'slug_fr' => 'catalogue.php', 'slug_en' => 'catalogue.php',
];

/* Les deux adresses dont les vues ont besoin. Elles sont définies ici, dans le
   point d'entrée, et non dans une bibliothèque : ainsi les vues fonctionnent
   telles quelles sous ce fichier, sans rien exiger du routeur. */
function cat_lien(): string { return url('/catalogue.php'); }

function cat_lien_fiche(array $p): string
{
    $s = trim((string)($p['slug_' . I18n::$lang] ?? '')) ?: trim((string)($p['slug_' . I18n::$default] ?? ''));
    return url('/catalogue.php') . '?p=' . rawurlencode($s);
}

// ---- La porte ---------------------------------------------------------------

if (isset($_GET['sortie'])) { CatalogAuth::fermer(); redirect('/catalogue.php'); }

$catState = ['error' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CatalogAuth::check()) {
    CatalogAuth::requireCsrf();
    if (CatalogAuth::throttled()) {
        $catState['error'] = t('cat_trop');
    } elseif (CatalogAuth::verifier((string)($_POST['mdp'] ?? ''))) {
        /* On recharge plutôt que d'afficher : sans cela un rafraîchissement
           renverrait le mot de passe une seconde fois. */
        redirect('/catalogue.php' . (isset($_GET['p']) ? '?p=' . rawurlencode((string)$_GET['p']) : ''));
    } else {
        $catState['error'] = t('cat_mauvais');
    }
}

// ---- Rendu ------------------------------------------------------------------

/** Comme view() du routeur, mais sans lui. */
function cat_rendre(string $tpl, array $vars = []): never
{
    global $page, $titre;
    require_once LV_APP . '/views/partials/helpers.php';
    $meta = [
        'title' => $titre . ' — ' . setting('site_name', 'Le Voisin'),
        'desc'  => '', 'url' => '', 'og' => '', 'alt' => [],
    ];
    extract($vars, EXTR_SKIP);
    ob_start();
    include LV_APP . '/views/' . $tpl . '.php';
    $content = ob_get_clean();
    include LV_APP . '/views/layout.php';
    exit;
}

if (!CatalogAuth::check() || !CatalogAuth::configure()) {
    cat_rendre('catalog_login', ['catState' => $catState]);
}

// ---- Une fiche, ou la grille ------------------------------------------------

$slug = trim((string)($_GET['p'] ?? ''));
if ($slug !== '') {
    $item = Catalog::spectacle($slug, I18n::$lang);
    if (!$item) { http_response_code(404); }
    cat_rendre($item ? 'catalog_item' : '404', $item ? ['item' => $item] : []);
}

$spectacles = Catalog::spectacles();

/* Les filtres ne proposent que ce que les spectacles portent vraiment : un
   filtre qui ne renvoie jamais rien est pire qu'un filtre absent. */
$catsCat = [];
$publics = [];
foreach ($spectacles as &$sp) {
    $ids = array_map('intval', array_column(
        DB::all('SELECT category_id FROM project_categories WHERE project_id = ?', [(int)$sp['id']]),
        'category_id'));
    $sp['_cats'] = $ids;
    foreach ($ids as $cid) $catsCat[$cid] = true;
    $pu = trim((string)($sp['public_cible'] ?? ''));
    if ($pu !== '') $publics[$pu] = true;
}
unset($sp);

$cats = $catsCat
    ? DB::all('SELECT * FROM categories WHERE id IN (' . implode(',', array_map('intval', array_keys($catsCat))) . ') ORDER BY sort, id')
    : [];

cat_rendre('catalog', [
    'spectacles' => $spectacles,
    'cats'       => $cats,
    'publics'    => array_keys($publics),
    'tags'       => Catalog::tousLesTags($spectacles),
]);
