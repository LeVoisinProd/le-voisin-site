<?php
/**
 * Le dashboard, en point d'entrée autonome.        [D01-CONTACTS] [16.08.2026]
 *
 * POURQUOI UN FICHIER À LA RACINE PLUTÔT QU'UNE PAGE DE L'ADMINISTRATION
 *
 * Pour la même raison que catalogue.php, et elle a été remesurée le 16.08.2026:
 * le cache d'opcode de ce serveur garde index.php compilé et refuse de le
 * relire. Preuve du jour, faite avec la marque de diagnostic posée le 12.08
 * exactement pour cela: le fichier du serveur et la copie locale font tous deux
 * 25 319 octets, la marque y figure quatre fois, et elle n'apparaissait pas
 * dans la sortie. Un fichier au nom neuf, lui, se compile à la première requête.
 *
 * Ce fichier ne demande donc rien à index.php, rien au .htaccess et rien au CMS.
 * Il s'amorce lui-même.
 *
 * CE QU'IL REMPLACE. Le dashboard actuel est une page unique de 6,3 Mo servie
 * par Apps Script, qui embarque les 7 841 contacts dans son JavaScript et
 * cherche en mémoire à chaque frappe. Ici la recherche est faite par MariaDB,
 * et la page ne transporte que ce qu'elle montre: 5,7 Ko pour cinquante fiches.
 *
 * QUI ENTRE. Auth, c'est-à-dire les comptes du bureau, les mêmes que
 * l'administration du site. Pas MemberAuth, qui est l'espace des 77
 * collaborateur·rices, ni CatalogAuth, qui est le mot de passe unique donné aux
 * programmateur·rices. Trois portes distinctes, et cette page est derrière la
 * première.
 *
 * L'ADRESSE:  https://le-voisin.com/dashboard.php
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
I18n::init();
session_boot();

if (!Auth::check()) redirect('/admin/login.php');

// ---------------------------------------------------------------------------
// La recherche
// ---------------------------------------------------------------------------

const PAR_PAGE = 50;

/** Le nombre minimal de caractères d'un mot pour que FULLTEXT le voie. */
const FT_MIN = 4;

$q      = trim((string)($_GET['q'] ?? ''));
$cat    = trim((string)($_GET['cat'] ?? ''));
$pays   = trim((string)($_GET['pays'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = ['supprime_le IS NULL'];
$args   = [];

if ($cat !== '')  { $where[] = 'categorie = ?';   $args[] = $cat; }
if ($pays !== '') { $where[] = 'pays_struct = ?'; $args[] = $pays; }

/* DEUX CHEMINS DE RECHERCHE, ET C'EST VOULU.
 *
 * FULLTEXT est le bon outil: il utilise un index et rend en millisecondes sur
 * 7 841 lignes. Mais il ignore les mots plus courts que ft_min_word_len, qui
 * vaut 4 par défaut sur InnoDB. Chercher « GE », « CH » ou un nom de trois
 * lettres ne rendrait donc rien du tout, et l'utilisatrice conclurait que le
 * contact n'existe pas.
 *
 * D'où le repli en LIKE quand le mot le plus long est trop court. Il lit toute
 * la table, ce qui coûte quelques millisecondes ici et resterait acceptable à
 * dix fois ce volume. Un résultat lent vaut mieux qu'un résultat vide et faux. */
$modeRecherche = '';
if ($q !== '') {
    $motLePlusLong = 0;
    foreach (preg_split('/\s+/', $q) ?: [] as $mot) {
        $motLePlusLong = max($motLePlusLong, mb_strlen($mot));
    }
    if ($motLePlusLong >= FT_MIN) {
        $modeRecherche = 'index';
        $where[] = 'MATCH(nom, structure, ville_struct, mots_cles, notes) AGAINST (? IN NATURAL LANGUAGE MODE)';
        $args[]  = $q;
    } else {
        $modeRecherche = 'balayage';
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $where[] = '(nom LIKE ? OR structure LIKE ? OR ville_struct LIKE ? OR email1 LIKE ?)';
        array_push($args, $like, $like, $like, $like);
    }
}

$sqlWhere = implode(' AND ', $where);
$t0 = microtime(true);

$st = DB::pdo()->prepare("SELECT COUNT(*) FROM contact WHERE $sqlWhere");
$st->execute($args);
$total = (int)$st->fetchColumn();

$pages  = max(1, (int)ceil($total / PAR_PAGE));
$page   = min($page, $pages);
$offset = ($page - 1) * PAR_PAGE;

$st = DB::pdo()->prepare(
    "SELECT ref, nom, prenom, nom_famille, fonction, structure, categorie,
            ville_struct, pays_struct, email1, email_pro1, tel1, site
       FROM contact
      WHERE $sqlWhere
      ORDER BY nom
      LIMIT " . PAR_PAGE . " OFFSET $offset"
);
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

/* Les listes des filtres viennent de la base et non d'une constante: une
   catégorie nouvelle apparaît toute seule, et une catégorie disparue cesse
   d'être proposée. */
$cats  = DB::pdo()->query("SELECT categorie, COUNT(*) n FROM contact
                            WHERE supprime_le IS NULL AND categorie IS NOT NULL
                            GROUP BY categorie ORDER BY n DESC")->fetchAll();
$payss = DB::pdo()->query("SELECT pays_struct, COUNT(*) n FROM contact
                            WHERE supprime_le IS NULL AND pays_struct IS NOT NULL
                            GROUP BY pays_struct ORDER BY n DESC LIMIT 20")->fetchAll();

$lien = function (array $chg) use ($q, $cat, $pays, $page): string {
    $p = array_merge(['q' => $q, 'cat' => $cat, 'pays' => $pays, 'page' => $page], $chg);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/dashboard.php' . ($p ? '?' . http_build_query($p) : '');
};
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Contacts — Dashboard Le Voisin</title>
<style>
:root { --encre:#0d0d0d; --papier:#fff; --doux:#5c5c5c; --trait:#e4e4e4;
        --jaune:#FFD24D; --fond2:#fafafa; }
* { box-sizing:border-box; }
body { margin:0; background:var(--papier); color:var(--encre); font-size:15px;
       font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; line-height:1.5; }
header { border-bottom:2px solid var(--encre); padding:14px 24px; display:flex;
         align-items:baseline; gap:18px; flex-wrap:wrap; }
header h1 { font-size:17px; margin:0; }
header .cpt { color:var(--doux); font-size:13px; }
header a.retour { margin-left:auto; color:var(--doux); font-size:13px; }
form.rech { padding:14px 24px; border-bottom:1px solid var(--trait); display:flex;
            gap:10px; flex-wrap:wrap; align-items:center; background:var(--fond2); }
input[type=search] { flex:1 1 260px; min-width:200px; padding:8px 12px;
            border:1px solid var(--trait); border-radius:4px; font-size:15px; }
select { padding:8px 10px; border:1px solid var(--trait); border-radius:4px;
            font-size:14px; background:#fff; max-width:230px; }
button { padding:8px 18px; border:0; background:var(--encre); color:#fff;
            border-radius:4px; font-size:14px; cursor:pointer; }
a.vider { color:var(--doux); font-size:13px; }
.tw { overflow-x:auto; }
table { border-collapse:collapse; width:100%; font-size:14px; }
th, td { padding:8px 14px; border-bottom:1px solid var(--trait); text-align:left;
         vertical-align:top; }
th { background:var(--fond2); font-size:12px; text-transform:uppercase;
     letter-spacing:.04em; color:var(--doux); position:sticky; top:0; }
tbody tr:hover { background:var(--fond2); }
td .sec { color:var(--doux); font-size:12.5px; }
nav.pages { padding:16px 24px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
nav.pages a, nav.pages span { padding:5px 11px; border:1px solid var(--trait);
     border-radius:4px; text-decoration:none; color:var(--encre); font-size:13px; }
nav.pages span.ici { background:var(--encre); color:#fff; border-color:var(--encre); }
nav.pages .mut { border:0; color:var(--doux); }
.vide { padding:48px 24px; color:var(--doux); }
</style>
</head>
<body>

<header>
  <h1>Contacts</h1>
  <span class="cpt"><?= number_format($total, 0, ',', ' ') ?> fiches<?php
    if ($q !== '' || $cat !== '' || $pays !== '') echo ' trouvées'; ?>
    · <?= $ms ?> ms<?php if ($modeRecherche === 'balayage') echo ' · balayage, mot court'; ?></span>
  <a class="retour" href="/admin/">Administration</a>
</header>

<form class="rech" method="get" action="/dashboard.php">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, structure, ville, mots-clefs, notes" autofocus>
  <select name="cat">
    <option value="">Toutes les catégories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= e($c['categorie']) ?>"<?= $cat === $c['categorie'] ? ' selected' : '' ?>>
        <?= e($c['categorie']) ?> (<?= $c['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="pays">
    <option value="">Tous les pays</option>
    <?php foreach ($payss as $p): ?>
      <option value="<?= e($p['pays_struct']) ?>"<?= $pays === $p['pays_struct'] ? ' selected' : '' ?>>
        <?= e($p['pays_struct']) ?> (<?= $p['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $cat !== '' || $pays !== ''): ?>
    <a class="vider" href="/dashboard.php">tout effacer</a>
  <?php endif; ?>
</form>

<?php if (!$lignes): ?>
  <p class="vide">Aucune fiche ne correspond.<?php
    if ($modeRecherche === 'index'): ?> La recherche par index ignore les mots de
    moins de <?= FT_MIN ?> lettres.<?php endif; ?></p>
<?php else: ?>
<div class="tw">
<table>
  <thead><tr>
    <th>Nom</th><th>Fonction</th><th>Structure</th><th>Lieu</th>
    <th>Catégorie</th><th>Contact</th>
  </tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <td><?= e($r['nom']) ?><?php if ($r['prenom'] || $r['nom_famille']): ?>
        <div class="sec"><?= e(trim(($r['prenom'] ?? '') . ' ' . ($r['nom_famille'] ?? ''))) ?></div>
      <?php endif; ?></td>
      <td class="sec"><?= e($r['fonction'] ?? '') ?></td>
      <td><?= e($r['structure'] ?? '') ?><?php if ($r['site']): ?>
        <div class="sec"><a href="<?= e($r['site']) ?>" target="_blank" rel="noopener">site</a></div>
      <?php endif; ?></td>
      <td><?= e($r['ville_struct'] ?? '') ?>
        <?php if ($r['pays_struct']): ?><div class="sec"><?= e($r['pays_struct']) ?></div><?php endif; ?></td>
      <td class="sec"><?= e($r['categorie'] ?? '') ?></td>
      <td class="sec">
        <?php $m = $r['email_pro1'] ?: $r['email1']; ?>
        <?php if ($m): ?><a href="mailto:<?= e($m) ?>"><?= e($m) ?></a><?php endif; ?>
        <?php if ($r['tel1']): ?><div><?= e($r['tel1']) ?></div><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="pages">
  <?php if ($page > 1): ?><a href="<?= e($lien(['page' => $page - 1])) ?>">précédent</a><?php endif; ?>
  <?php
  /* Une pagination courte: les cinq pages autour, plus les extrémités. Sur 157
     pages, tout afficher ferait une barre plus haute que le tableau. */
  $vus = [];
  foreach ([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages] as $p) {
      if ($p < 1 || $p > $pages || isset($vus[$p])) continue;
      $vus[$p] = 1;
  }
  $prec = 0;
  foreach (array_keys($vus) as $p) {
      if ($p > $prec + 1) echo '<span class="mut">…</span>';
      echo $p === $page
          ? '<span class="ici">' . $p . '</span>'
          : '<a href="' . e($lien(['page' => $p])) . '">' . $p . '</a>';
      $prec = $p;
  }
  ?>
  <?php if ($page < $pages): ?><a href="<?= e($lien(['page' => $page + 1])) ?>">suivant</a><?php endif; ?>
  <span class="mut">page <?= $page ?> sur <?= $pages ?></span>
</nav>
<?php endif; ?>

</body>
</html>
