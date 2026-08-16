<?php
/**
 * Écran Contacts. [16.08.2026]
 *
 * Le carnet d'adresses de la diffusion: 7 841 fiches, cherchées et filtrées par
 * MariaDB. Le dashboard actuel les embarque dans son JavaScript, 2,23 Mo, et
 * cherche en mémoire à chaque frappe.
 *
 * TROIS VUES DANS UN FICHIER, choisies par ?c=<id> et ?mod: la liste, la fiche,
 * le formulaire. Lire, chercher, filtrer, ouvrir, créer, modifier, supprimer.
 *
 * LA SUPPRESSION EST LOGIQUE. Une fiche effacée reste en base avec sa date et
 * sort des listes. Sur 7 841 contacts construits en des années, une suppression
 * définitive est une perte qu'on ne remarque que le jour où l'on cherche.
 */
declare(strict_types=1);

const PAR_PAGE = 50;

/** En dessous de cette longueur, FULLTEXT ne voit pas le mot. */
const FT_MIN = 4;

$cid = (int)($_GET['c'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER
// ═══════════════════════════════════════════════════════════════════════════

$CH_CONTACT = ['nom','prenom','nom_famille','fonction','structure','categorie',
               'ville_struct','pays_struct','region','adresse','cp','ville','dept','pays',
               'email1','email2','email_pro1','tel1','tel_pro1','site',
               'mots_cles','description','participations','notes'];
$err = $saisi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    foreach ($CH_CONTACT as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    if (($_POST['action'] ?? '') === 'supprimer' && $cid > 0) {
        DB::pdo()->prepare('UPDATE contact SET supprime_le = NOW() WHERE id = ?')->execute([$cid]);
        dash_flash('Contact supprimé. Il reste en base et peut être rétabli.');
        redirect('/dashboard.php?e=contacts');
    }

    if ($saisi['nom'] === '') $err['nom'] = 'Sans nom, la fiche ne se retrouve pas.';
    foreach (['email1','email2','email_pro1'] as $m) {
        if ($saisi[$m] !== '' && !filter_var($saisi[$m], FILTER_VALIDATE_EMAIL)) {
            $err[$m] = 'Cette adresse ne ressemble pas à une adresse.';
        }
    }

    if (!$err) {
        $vals = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $CH_CONTACT);
        if ($cid > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $CH_CONTACT));
            DB::pdo()->prepare("UPDATE contact SET $set WHERE id = ?")->execute([...$vals, $cid]);
            dash_flash('Contact enregistré.');
        } else {
            /* `ref` est NOT NULL et unique: elle vient de la reprise du dashboard.
               Une fiche créée ici s'en donne une qui ne peut pas entrer en
               collision avec les « c001 » à « c7841 » déjà repris. */
            $ref = 'n' . date('ymdHis') . random_int(10, 99);
            $cols = array_merge(['ref'], $CH_CONTACT);
            $q = implode(',', array_fill(0, count($cols), '?'));
            DB::pdo()->prepare('INSERT INTO contact (' . implode(',', $cols) . ") VALUES ($q)")
                     ->execute([$ref, ...$vals]);
            $cid = (int)DB::pdo()->lastInsertId();
            dash_flash('Contact créé.');
        }
        redirect('/dashboard.php?e=contacts&c=' . $cid);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LE FORMULAIRE
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['mod']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $k = $cid > 0 ? DB::one('SELECT * FROM contact WHERE id = ? AND supprime_le IS NULL', [$cid]) : [];
    if ($cid > 0 && !$k) { dash_haut('contacts'); echo '<p class="vide">Ce contact n\'existe pas.</p>'; dash_bas(); return; }
    $v = fn(string $c) => $saisi[$c] ?? ($k[$c] ?? '');

    $cats = DB::pdo()->query("SELECT DISTINCT categorie FROM contact
        WHERE supprime_le IS NULL AND categorie IS NOT NULL ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);
    $choixCat = ['' => '(aucune)'] + array_combine($cats, $cats);

    dash_haut('contacts', $cid > 0 ? 'modifier' : 'nouveau contact');
    dash_form_style();
    if ($err) echo '<div class="flash err">Rien n\'a été enregistré: ' . count($err)
                 . ' champ(s) à corriger. Ce que vous aviez saisi est conservé.</div>';
    ?>
    <div class="fil"><a href="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>">← retour</a></div>
    <form class="saisie" method="post"
          action="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>&amp;mod=1">
      <?= Auth::csrfField() ?>
      <div class="grille">
        <div class="titre-bloc">Qui</div>
        <?php
        ch('nom', 'Nom affiché', $v('nom'), $err, ['requis'=>true, 'large'=>true,
           'aide'=>'Ce qui apparaît dans les listes. Souvent le nom de la structure']);
        ch('prenom', 'Prénom', $v('prenom'), $err);
        ch('nom_famille', 'Nom de famille', $v('nom_famille'), $err);
        ch('fonction', 'Fonction', $v('fonction'), $err);
        ch('categorie', 'Catégorie', $v('categorie'), $err, ['type'=>'select','choix'=>$choixCat]);

        echo '<div class="titre-bloc">La structure</div>';
        ch('structure', 'Structure', $v('structure'), $err, ['large'=>true]);
        ch('ville_struct', 'Ville', $v('ville_struct'), $err);
        ch('pays_struct', 'Pays', $v('pays_struct'), $err);
        ch('region', 'Région', $v('region'), $err);
        ch('site', 'Site', $v('site'), $err, ['large'=>true]);

        echo '<div class="titre-bloc">Joindre</div>';
        ch('email_pro1', 'Courriel professionnel', $v('email_pro1'), $err, ['type'=>'email']);
        ch('email1', 'Courriel', $v('email1'), $err, ['type'=>'email']);
        ch('email2', 'Autre courriel', $v('email2'), $err, ['type'=>'email']);
        ch('tel_pro1', 'Téléphone professionnel', $v('tel_pro1'), $err);
        ch('tel1', 'Téléphone', $v('tel1'), $err);

        echo '<div class="titre-bloc">Adresse postale</div>';
        ch('adresse', 'Adresse', $v('adresse'), $err, ['large'=>true]);
        ch('cp', 'Code postal', $v('cp'), $err);
        ch('ville', 'Ville', $v('ville'), $err);
        ch('dept', 'Département', $v('dept'), $err);
        ch('pays', 'Pays', $v('pays'), $err);

        echo '<div class="titre-bloc">Le reste</div>';
        ch('mots_cles', 'Mots-clefs', $v('mots_cles'), $err, ['large'=>true,
           'aide'=>'Ils entrent dans la recherche par index']);
        ch('description', 'Description', $v('description'), $err, ['large'=>true]);
        ch('participations', 'Participations', $v('participations'), $err);
        ch('notes', 'Notes', $v('notes'), $err, ['type'=>'textarea','large'=>true,'rows'=>5,
           'aide'=>'Elles entrent aussi dans la recherche']);
        ?>
      </div>
      <div class="actions">
        <button type="submit"><?= $cid > 0 ? 'Enregistrer' : 'Créer' ?></button>
        <a class="sec2" href="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>">annuler</a>
        <?php if ($cid > 0): ?>
        <a class="sup" href="#" onclick="if(confirm('Supprimer ce contact ? Il restera en base.')){
             var f=document.createElement('form');f.method='post';
             f.action='/dashboard.php?e=contacts&c=<?= $cid ?>&mod=1';
             f.innerHTML='<?= addslashes(Auth::csrfField()) ?><input name=action value=supprimer>';
             document.body.appendChild(f);f.submit();}return false;">supprimer</a>
        <?php endif; ?>
      </div>
    </form>
    <style>.fil{padding:12px 26px 0;font-size:13px}.fil a{color:var(--doux);text-decoration:none}</style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($cid > 0) {
    $k = DB::one('SELECT * FROM contact WHERE id = ? AND supprime_le IS NULL', [$cid]);
    if (!$k) { dash_haut('contacts'); echo '<p class="vide">Ce contact n\'existe pas.</p>'; dash_bas(); return; }

    dash_haut('contacts', e(trim((string)($k['fonction'] ?? '') . ' ' . ($k['categorie'] ? '· ' . $k['categorie'] : ''))));
    ?>
    <div class="fil"><a href="/dashboard.php?e=contacts">← tous les contacts</a>
      <a class="mod" href="/dashboard.php?e=contacts&amp;c=<?= $cid ?>&amp;mod=1">modifier</a></div>
    <?php dash_flash_html(); ?>
    <div class="zone">
      <h2 class="gros"><?= e($k['nom']) ?></h2>
      <?php if ($k['prenom'] || $k['nom_famille']): ?>
        <p class="sst2"><?= e(trim(($k['prenom'] ?? '') . ' ' . ($k['nom_famille'] ?? ''))) ?></p>
      <?php endif; ?>
      <div class="fiche">
      <?php
      $l = function (string $lib, $val, string $href = '') {
          if ($val === null || $val === '') return;
          $v = $href ? '<a href="' . e($href . $val) . '">' . e((string)$val) . '</a>' : e((string)$val);
          printf('<div class="l"><span class="k">%s</span><span class="v">%s</span></div>', e($lib), $v);
      };
      $l('Fonction', $k['fonction']);
      $l('Catégorie', $k['categorie']);
      $l('Structure', $k['structure']);
      $l('Ville', trim((string)($k['ville_struct'] ?? '') . ' ' . ($k['pays_struct'] ? '· ' . $k['pays_struct'] : '')));
      $l('Région', $k['region']);
      $l('Site', $k['site']);
      $l('Courriel pro', $k['email_pro1'], 'mailto:');
      $l('Courriel', $k['email1'], 'mailto:');
      $l('Autre courriel', $k['email2'], 'mailto:');
      $l('Téléphone pro', $k['tel_pro1'], 'tel:');
      $l('Téléphone', $k['tel1'], 'tel:');
      $l('Adresse', trim((string)($k['adresse'] ?? '') . ' ' . ($k['cp'] ?? '') . ' ' . ($k['ville'] ?? '')));
      $l('Département', $k['dept']);
      $l('Pays', $k['pays']);
      $l('Mots-clefs', $k['mots_cles']);
      $l('Description', $k['description']);
      $l('Participations', $k['participations']);
      $l('Référence', $k['ref']);
      ?>
      </div>
      <?php if ($k['notes']): ?>
        <div class="bl"><h3>Notes</h3><p><?= nl2br(e($k['notes'])) ?></p></div>
      <?php endif; ?>
    </div>
    <style>
    .fil{padding:12px 26px 0;font-size:13px;display:flex;gap:16px}
    .fil a{color:var(--doux);text-decoration:none}
    .fil a.mod{margin-left:auto;color:var(--encre);font-weight:600}
    h2.gros{font-size:21px;margin:0 0 4px}
    .sst2{margin:0 0 18px;color:var(--doux);font-size:14px}
    .fiche{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:0 34px;max-width:960px}
    .fiche .l{display:flex;gap:12px;padding:7px 0;border-bottom:1px solid var(--trait)}
    .fiche .k{color:var(--doux);font-size:12.5px;min-width:140px}
    .fiche .v{font-size:14px;word-break:break-word}
    .bl{margin-top:24px;padding:13px 17px;background:var(--fond2);max-width:800px}
    .bl h3{font-size:13px;margin:0 0 6px}.bl p{margin:0;font-size:14px}
    </style>
    <?php dash_bas(); return;
}

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
    "SELECT id, ref, nom, prenom, nom_famille, fonction, structure, categorie,
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
  <a class="neuf" href="/dashboard.php?e=contacts&amp;mod=1">+ nouveau contact</a>
</form>
<?php dash_flash_html(); ?>
<style>.neuf{margin-left:auto;padding:8px 16px;background:var(--jaune);color:#0d0d0d;
  border-radius:4px;text-decoration:none;font-size:13.5px;font-weight:600}</style>

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
      <td><a href="/dashboard.php?e=contacts&amp;c=<?= (int)$r['id'] ?>"><?= e($r['nom']) ?></a><?php if ($r['prenom'] || $r['nom_famille']): ?>
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
