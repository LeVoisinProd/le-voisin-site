<?php
/**
 * Écran Contacts. [16.08.2026]
 *
 * Le carnet d'adresses de la diffusion: 7 841 fiches, cherchées et filtrées par
 * MariaDB. Le dashboard actuel les embarque dans son JavaScript, 2,23 Mo, et
 * cherche en mémoire à chaque frappe.
 *
 * ÉTAT: partiel. On lit, on cherche, on filtre. Ouvrir une fiche, créer et
 * modifier ne sont pas encore là, et c'est ce qui manque pour travailler
 * plutôt que consulter.
 */
declare(strict_types=1);

const PAR_PAGE = 50;

/** En dessous de cette longueur, FULLTEXT ne voit pas le mot. */
const FT_MIN = 4;

$q    = trim((string)($_GET['q'] ?? ''));
$cat  = trim((string)($_GET['cat'] ?? ''));
$pays = trim((string)($_GET['pays'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['supprime_le IS NULL'];
$args  = [];
if ($cat !== '')  { $where[] = 'categorie = ?';   $args[] = $cat; }
if ($pays !== '') { $where[] = 'pays_struct = ?'; $args[] = $pays; }

/* DEUX CHEMINS DE RECHERCHE, ET C'EST VOULU.
 *
 * FULLTEXT utilise un index et rend en millisecondes sur 7 841 lignes. Mais il
 * ignore les mots plus courts que ft_min_word_len, qui vaut 4 sur InnoDB.
 * Chercher « GE » ou un nom de trois lettres ne rendrait rien du tout, et l'on
 * en conclurait que le contact n'existe pas.
 *
 * D'où le repli en LIKE quand le mot le plus long est trop court: il lit toute
 * la table, ce qui coûte une douzaine de millisecondes ici. Un résultat lent
 * vaut mieux qu'un résultat vide et faux. L'écran dit lequel des deux il a
 * utilisé, pour que ce ne soit pas une magie invisible. */
$mode = '';
if ($q !== '') {
    $plusLong = 0;
    foreach (preg_split('/\s+/', $q) ?: [] as $mot) $plusLong = max($plusLong, mb_strlen($mot));
    if ($plusLong >= FT_MIN) {
        $mode    = 'index';
        $where[] = 'MATCH(nom, structure, ville_struct, mots_cles, notes) AGAINST (? IN NATURAL LANGUAGE MODE)';
        $args[]  = $q;
    } else {
        $mode    = 'balayage';
        $like    = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
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
       FROM contact WHERE $sqlWhere ORDER BY nom
      LIMIT " . PAR_PAGE . " OFFSET $offset");
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

/* Les listes des filtres viennent de la base, jamais d'une constante: une
   catégorie nouvelle apparaît toute seule, une catégorie disparue cesse d'être
   proposée, et personne n'a à tenir une liste à jour à la main. */
$cats  = DB::pdo()->query("SELECT categorie, COUNT(*) n FROM contact
                            WHERE supprime_le IS NULL AND categorie IS NOT NULL
                            GROUP BY categorie ORDER BY n DESC")->fetchAll();
$payss = DB::pdo()->query("SELECT pays_struct, COUNT(*) n FROM contact
                            WHERE supprime_le IS NULL AND pays_struct IS NOT NULL
                            GROUP BY pays_struct ORDER BY n DESC LIMIT 20")->fetchAll();

$lien = function (array $chg) use ($q, $cat, $pays, $page): string {
    $p = array_merge(['e' => 'contacts', 'q' => $q, 'cat' => $cat, 'pays' => $pays, 'page' => $page], $chg);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/dashboard.php?' . http_build_query($p);
};

$sst = number_format($total, 0, ',', ' ') . ' fiche' . ($total > 1 ? 's' : '')
     . (($q !== '' || $cat !== '' || $pays !== '') ? ' trouvée' . ($total > 1 ? 's' : '') : '')
     . ' · ' . $ms . ' ms'
     . ($mode === 'balayage' ? ' · balayage, mot court' : '');

dash_haut('contacts', e($sst));
?>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="contacts">
  <input type="search" name="q" value="<?= e($q) ?>"
         placeholder="Nom, structure, ville, mots-clefs, notes" autofocus>
  <select name="cat">
    <option value="">Toutes les catégories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= e($c['categorie']) ?>"<?= $cat === $c['categorie'] ? ' selected' : '' ?>><?=
        e($c['categorie']) ?> (<?= $c['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="pays">
    <option value="">Tous les pays</option>
    <?php foreach ($payss as $p): ?>
      <option value="<?= e($p['pays_struct']) ?>"<?= $pays === $p['pays_struct'] ? ' selected' : '' ?>><?=
        e($p['pays_struct']) ?> (<?= $p['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $cat !== '' || $pays !== ''): ?>
    <a class="vider" href="/dashboard.php?e=contacts">tout effacer</a>
  <?php endif; ?>
</form>

<?php if (!$lignes): ?>
  <p class="vide">Aucune fiche ne correspond.<?php if ($mode === 'index'): ?>
    La recherche par index ignore les mots de moins de <?= FT_MIN ?> lettres.<?php endif; ?></p>
<?php else: ?>
<div class="tw">
<table>
  <thead><tr>
    <th>Nom</th><th>Fonction</th><th>Structure</th><th>Lieu</th><th>Catégorie</th><th>Contact</th>
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
      <td><?= e($r['ville_struct'] ?? '') ?><?php if ($r['pays_struct']): ?>
        <div class="sec"><?= e($r['pays_struct']) ?></div><?php endif; ?></td>
      <td class="sec"><?= e($r['categorie'] ?? '') ?></td>
      <td class="sec"><?php $m = $r['email_pro1'] ?: $r['email1']; ?>
        <?php if ($m): ?><a href="mailto:<?= e($m) ?>"><?= e($m) ?></a><?php endif; ?>
        <?php if ($r['tel1']): ?><div><?= e($r['tel1']) ?></div><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="pages">
  <?php if ($page > 1): ?><a href="<?= e($lien(['page' => $page - 1])) ?>">précédent</a><?php endif; ?>
  <?php
  /* Les cinq pages autour, plus les extrémités. Sur 157 pages, tout afficher
     ferait une barre plus haute que le tableau. */
  $vus = [];
  foreach ([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages] as $p) {
      if ($p >= 1 && $p <= $pages) $vus[$p] = 1;
  }
  ksort($vus);
  $prec = 0;
  foreach (array_keys($vus) as $p) {
      if ($p > $prec + 1) echo '<span class="mut">…</span>';
      echo $p === $page ? '<span class="ici">' . $p . '</span>'
                        : '<a href="' . e($lien(['page' => $p])) . '">' . $p . '</a>';
      $prec = $p;
  }
  ?>
  <?php if ($page < $pages): ?><a href="<?= e($lien(['page' => $page + 1])) ?>">suivant</a><?php endif; ?>
  <span class="mut">page <?= $page ?> sur <?= $pages ?></span>
</nav>
<?php endif; ?>

<?php dash_bas(); ?>
