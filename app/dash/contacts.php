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
               'mots_cles','description','participations','notes',
               /* Ajoutées le 16.08.2026, migration 018. `pronom` et `adresse2`
                  existaient dans le dashboard et se perdaient en silence à
                  l'enregistrement, faute de colonne. */
               'pronom','adresse2','instagram','linkedin','directions',
               'date_mois','date_notes'];
$err = $saisi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('contacts');
    foreach ($CH_CONTACT as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    /* LA PHOTO. Convertie en data URI et rangée dans la fiche, comme le fait
       le dashboard — c'est ce qui rend la reprise sans perte, et c'est aussi
       discutable: une image dans une colonne de texte pèse sur chaque requête
       qui fait SELECT *. On garde la forme existante plutôt que d'en inventer
       une seconde, et 60 fiches sur 8432 en portent une.

       400 Ko au maximum, et une liste de types fermée: un data URI n'est pas
       servi par Apache mais il est rendu par le navigateur, et un SVG accepté
       ici s'exécuterait dans la page. */
    if (!empty($_POST['photo_retirer'])) {
        $saisi['photo'] = '';
    } elseif (($_FILES['photo_f']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $f = $_FILES['photo_f'];
        $ok = ['image/jpeg' => 1, 'image/png' => 1, 'image/webp' => 1, 'image/gif' => 1];
        $mime = function_exists('mime_content_type') && is_uploaded_file((string)$f['tmp_name'])
              ? (string)@mime_content_type((string)$f['tmp_name']) : '';
        if (!isset($ok[$mime])) {
            $err['photo_f'] = 'JPEG, PNG, GIF ou WebP seulement.';
        } elseif ((int)$f['size'] > 400 * 1024) {
            $err['photo_f'] = 'La photo dépasse 400 Ko.';
        } else {
            $saisi['photo'] = 'data:' . $mime . ';base64,'
                            . base64_encode((string)file_get_contents((string)$f['tmp_name']));
        }
    } else {
        /* Aucun fichier envoyé: on ne touche pas à celle qui est enregistrée. */
        unset($saisi['photo']);
    }

    /* LES TROIS LISTES À COCHER. Elles arrivent en tableau et repartent en
       chaîne à virgules — le format du dashboard, pour que la reprise depuis
       lv-contacts reste sans perte et qu'un import relise ce que l'écran écrit.

       `participations_libre` permet d'en ajouter une qui n'est pas dans les
       douze proposées: une liste fermée demanderait une migration à chaque
       nouveau festival, et le prochain arrive toujours. */
    foreach (['participations', 'date_mois', 'directions'] as $liste) {
        $c = array_filter(array_map('trim', (array)($_POST[$liste . '_c'] ?? [])),
                          fn($x) => $x !== '');
        if ($liste === 'participations') {
            foreach (explode(',', (string)($_POST['participations_libre'] ?? '')) as $x) {
                $x = trim($x);
                if ($x !== '' && !in_array($x, $c, true)) $c[] = $x;
            }
        }
        /* Le champ n'est écrasé que si le formulaire portait bien ses cases:
           sans cela, un POST qui ne les contient pas viderait la liste. */
        if (isset($_POST[$liste . '_c']) || ($liste === 'participations' && isset($_POST['participations_libre']))) {
            $saisi[$liste] = mb_substr(implode(', ', array_unique($c)), 0, 500);
        }
    }

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
        /* Les champs absents du POST — la photo qu'on n'a pas retouchée —
           sortent de l'écriture au lieu d'y entrer à NULL. */
        $cols = array_values(array_filter($CH_CONTACT, fn($c) => array_key_exists($c, $saisi)));
        $vals = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $cols);
        if ($cid > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            DB::pdo()->prepare("UPDATE contact SET $set WHERE id = ?")->execute([...$vals, $cid]);
            dash_flash('Contact enregistré.');
        } else {
            /* `ref` est NOT NULL et unique: elle vient de la reprise du dashboard.
               Une fiche créée ici s'en donne une qui ne peut pas entrer en
               collision avec les « c001 » à « c7841 » déjà repris. */
            $ref = 'n' . date('ymdHis') . random_int(10, 99);
            /* $cols porte déjà les seules colonnes que le POST a apportées:
               on lui ajoute `ref` devant, et surtout PAS $CH_CONTACT entier —
               sinon l'INSERT réclamerait plus de valeurs que $vals n'en a. */
            $colsIns = array_merge(['ref'], $cols);
            $q = implode(',', array_fill(0, count($colsIns), '?'));
            DB::pdo()->prepare('INSERT INTO contact (' . implode(',', $colsIns) . ") VALUES ($q)")
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
    <form class="saisie" method="post" enctype="multipart/form-data"
          action="/dashboard.php?e=contacts<?= $cid > 0 ? '&amp;c=' . $cid : '' ?>&amp;mod=1">
      <?= Auth::csrfField() ?>
      <div class="grille">
        <div class="titre-bloc">Qui</div>
        <?php
        ch('nom', 'Nom affiché', $v('nom'), $err, ['requis'=>true, 'large'=>true,
           'aide'=>'Ce qui apparaît dans les listes. Souvent le nom de la structure']);
        ch('prenom', 'Prénom', $v('prenom'), $err);
        ch('nom_famille', 'Nom de famille', $v('nom_famille'), $err);
        /* Le pronom n'est pas un ornement: écrire à un programmateur en se
           trompant est le genre de faute qui ferme une porte avant la première
           phrase. 236 fiches le portent déjà. */
        ch('pronom', 'Pronom', $v('pronom'), $err, ['aide'=>'elle, il, iel, Mme, M.']);
        ch('fonction', 'Fonction', $v('fonction'), $err);
        ch('categorie', 'Catégorie', $v('categorie'), $err, ['type'=>'select','choix'=>$choixCat]);

        echo '<div class="titre-bloc">La structure</div>';
        ch('structure', 'Structure', $v('structure'), $err, ['large'=>true]);
        ch('ville_struct', 'Ville', $v('ville_struct'), $err);
        ch('pays_struct', 'Pays', $v('pays_struct'), $err);
        ch('region', 'Région', $v('region'), $err);
        ch('site', 'Site', $v('site'), $err, ['large'=>true]);
        ch('instagram', 'Instagram', $v('instagram'), $err);
        ch('linkedin', 'LinkedIn', $v('linkedin'), $err);

        echo '<div class="titre-bloc">Joindre</div>';
        ch('email_pro1', 'Courriel professionnel', $v('email_pro1'), $err, ['type'=>'email']);
        ch('email1', 'Courriel', $v('email1'), $err, ['type'=>'email']);
        ch('email2', 'Autre courriel', $v('email2'), $err, ['type'=>'email']);
        ch('tel_pro1', 'Téléphone professionnel', $v('tel_pro1'), $err);
        ch('tel1', 'Téléphone', $v('tel1'), $err);

        echo '<div class="titre-bloc">Adresse postale</div>';
        ch('adresse', 'Adresse', $v('adresse'), $err, ['large'=>true]);
        ch('adresse2', 'Adresse (suite)', $v('adresse2'), $err, ['large'=>true]);
        ch('cp', 'Code postal', $v('cp'), $err);
        ch('ville', 'Ville', $v('ville'), $err);
        ch('dept', 'Département', $v('dept'), $err);
        ch('pays', 'Pays', $v('pays'), $err);

        echo '<div class="titre-bloc">Le reste</div>';
        ch('mots_cles', 'Mots-clefs', $v('mots_cles'), $err, ['large'=>true,
           'aide'=>'Ils entrent dans la recherche par index']);
        ch('description', 'Description', $v('description'), $err, ['large'=>true]);
        ?>
        <div class="ch large">
          <label for="photo_f">Photo</label>
          <?php if (trim((string)$v('photo')) !== ''): ?>
            <div class="ph-apercu"><img src="<?= e((string)$v('photo')) ?>" alt="">
              <label class="ph-sup"><input type="checkbox" name="photo_retirer" value="1"> retirer</label>
            </div>
          <?php endif; ?>
          <input type="file" id="photo_f" name="photo_f" accept="image/*">
          <p class="aide">JPEG, PNG ou WebP, 400 Ko au maximum. Elle est stockée dans la fiche
             elle-même, comme le fait le dashboard: c'est ce qui permet la reprise sans perte.</p>
        </div>
        <?php
        ch('notes', 'Notes', $v('notes'), $err, ['type'=>'textarea','large'=>true,'rows'=>5,
           'aide'=>'Elles entrent aussi dans la recherche']);
        ?>
      </div>

      <?php require __DIR__ . '/_contact_listes.php'; ?>
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
      <div class="tete-c">
        <?php /* La photo est un data URI dans le dashboard, pas un chemin: on la
                 rend telle quelle. 60 fiches sur 8432 en portent une. */ ?>
        <?php if (trim((string)($k['photo'] ?? '')) !== ''): ?>
          <img class="ph-c" src="<?= e((string)$k['photo']) ?>" alt="">
        <?php endif; ?>
        <div>
          <h2 class="gros"><?= e($k['nom']) ?></h2>
          <?php if ($k['prenom'] || $k['nom_famille'] || $k['pronom']): ?>
            <p class="sst2"><?= e(trim(($k['prenom'] ?? '') . ' ' . ($k['nom_famille'] ?? ''))) ?><?php
              if ($k['pronom']): ?> <span class="pron"><?= e((string)$k['pronom']) ?></span><?php endif; ?></p>
          <?php endif; ?>
        </div>
      </div>
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
      $l('Instagram', $k['instagram']);
      $l('LinkedIn', $k['linkedin']);
      $l('Courriel pro', $k['email_pro1'], 'mailto:');
      $l('Courriel', $k['email1'], 'mailto:');
      $l('Autre courriel', $k['email2'], 'mailto:');
      $l('Téléphone pro', $k['tel_pro1'], 'tel:');
      $l('Téléphone', $k['tel1'], 'tel:');
      $l('Adresse', trim((string)($k['adresse'] ?? '') . ' ' . ($k['adresse2'] ?? '')
                        . ' ' . ($k['cp'] ?? '') . ' ' . ($k['ville'] ?? '')));
      $l('Département', $k['dept']);
      $l('Pays', $k['pays']);
      $l('Mots-clefs', $k['mots_cles']);
      $l('Description', $k['description']);
      $l('Référence', $k['ref']);
      ?>
      </div>

      <?php /* LES TROIS LISTES EN PASTILLES, comme dans le formulaire. Une chaîne
               « Chalon 2024, Jeune public, Carnet diffusion » se lit mal en une
               ligne; découpée, on voit d'un coup où l'on s'est croisés. */
      $past = function (string $titre, $val) {
          $v = trim((string)($val ?? ''));
          if ($v === '') return;
          echo '<div class="past"><div class="past-t">' . e($titre) . '</div><div class="past-g">';
          foreach (array_filter(array_map('trim', explode(',', $v))) as $x)
              echo '<span class="past-p">' . e($x) . '</span>';
          echo '</div></div>';
      };
      $past('Participations et rencontres', $k['participations']);
      $past('Mois envisagés ou confirmés', $k['date_mois']);
      $past('Directions artistiques liées', $k['directions']);
      ?>

      <?php if (trim((string)($k['date_notes'] ?? '')) !== ''): ?>
        <div class="bl"><h3>Précisions sur les dates</h3><p><?= nl2br(e((string)$k['date_notes'])) ?></p></div>
      <?php endif; ?>
      <?php if ($k['notes']): ?>
        <div class="bl"><h3>Notes</h3><p><?= nl2br(e($k['notes'])) ?></p></div>
      <?php endif; ?>
    </div>
    <style>
    .tete-c{display:flex;gap:16px;align-items:flex-start;margin-bottom:14px}
    .ph-c{width:64px;height:64px;object-fit:cover;border-radius:8px;flex:none;border:1px solid var(--trait)}
    .pron{font-size:12.5px;color:var(--doux);border:1px solid var(--trait);border-radius:10px;padding:1px 8px;margin-left:6px}
    .past{margin:16px 0 0}
    .past-t{font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--doux);margin-bottom:6px}
    .past-g{display:flex;flex-wrap:wrap;gap:6px}
    .past-p{font-size:13px;padding:3px 11px;border:1px solid var(--trait);border-radius:13px}
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

/* Trois filtres de plus, et ce sont eux qui servent à la diffusion: on ne
   cherche pas « un programmateur », on cherche « qui, dans cette région, a été
   croisé à Chalon et pourrait prendre Bestiarium ».

   `participations` et `directions` sont des chaînes à virgules: on y cherche
   en LIKE. Un index n'y servirait à rien tant qu'elles ne sont pas normalisées,
   et les normaliser demande d'abord de savoir si l'on s'en sert. */
$reg  = trim((string)($_GET['reg'] ?? ''));
$part = trim((string)($_GET['part'] ?? ''));
$dir  = trim((string)($_GET['dir'] ?? ''));
if ($reg  !== '') { $where[] = 'region = ?';           $args[] = $reg; }
if ($part !== '') { $where[] = 'participations LIKE ?'; $args[] = '%' . $part . '%'; }
if ($dir  !== '') { $where[] = 'directions LIKE ?';     $args[] = '%' . $dir . '%'; }

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
        $where[] = '(nom LIKE ? OR structure LIKE ? OR ville_struct LIKE ? OR email1 LIKE ?
                     OR email_pro1 LIKE ? OR prenom LIKE ? OR nom_famille LIKE ?)';
        array_push($args, $like, $like, $like, $like, $like, $like, $like);
    }
}

/* Les listes des trois filtres nouveaux. `participations` et `directions`
   sont des chaînes à virgules: on les découpe ici pour proposer les valeurs
   réellement présentes, plutôt qu'une liste écrite en dur qui vieillirait. */
$regions = DB::all("SELECT region, COUNT(*) n FROM contact
                    WHERE supprime_le IS NULL AND region IS NOT NULL AND region <> ''
                    GROUP BY region HAVING n >= 3 ORDER BY n DESC LIMIT 40");
$lesParts = $lesDirs = [];
foreach (DB::all("SELECT participations FROM contact
                  WHERE supprime_le IS NULL AND participations IS NOT NULL AND participations <> ''") as $r)
    foreach (array_map('trim', explode(',', (string)$r['participations'])) as $x)
        if ($x !== '') $lesParts[$x] = ($lesParts[$x] ?? 0) + 1;
foreach (DB::all("SELECT directions FROM contact
                  WHERE supprime_le IS NULL AND directions IS NOT NULL AND directions <> ''") as $r)
    foreach (array_map('trim', explode(',', (string)$r['directions'])) as $x)
        if ($x !== '') $lesDirs[$x] = ($lesDirs[$x] ?? 0) + 1;
arsort($lesParts); arsort($lesDirs);

/* LES ASSOCIATIONS SONT PROPOSÉES MÊME QUAND AUCUN CONTACT N'EN PORTE ENCORE.
   Les deux autres filtres se déduisent des fiches, et c'est juste pour eux: une
   participation qui n'existe sur personne n'est pas un filtre. Une association,
   si — elle existe indépendamment du carnet, et le filtre construit à partir
   des seules fiches disparaissait entièrement tant que personne n'avait coché,
   ce qui donne à l'écran l'air de n'avoir pas changé. On ajoute donc les
   associations connues à zéro, et le compte ne s'affiche que s'il y en a un. */
foreach (DB::all("SELECT nom FROM organisation
                   WHERE supprime_le IS NULL AND genre = 'association' AND nom <> ''
                   ORDER BY nom") as $r)
    $lesDirs[(string)$r['nom']] ??= 0;

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

<?php /* ── LA BARRE, SUR DEUX LIGNES ─────────────────────────────────────────
     [16.08.2026] Demandé par Anna. Les six contrôles se suivaient à la file et
     passaient à la ligne au hasard de la largeur de l'écran: le bouton
     « Chercher » et « nouveau contact » se retrouvaient tantôt à droite,
     tantôt au milieu de la deuxième ligne, jamais au même endroit d'un écran à
     l'autre. On cherche un bouton qu'on a déjà cliqué cent fois — il doit être
     là où on l'a laissé.

     Ligne du haut: le champ de recherche, large, et à droite les deux gestes
     qui terminent — chercher, ou créer. Ligne du bas: les cinq filtres, qui
     précisent une recherche sans jamais la déclencher seuls. */ ?>
<form class="filtres deux-lignes" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="contacts">

  <div class="fl-haut">
    <input type="search" name="q" value="<?= e($q) ?>"
           placeholder="Nom, structure, ville, mots-clefs, notes" autofocus>
    <button type="submit">Chercher</button>
    <a class="neuf" href="/dashboard.php?e=contacts&amp;mod=1">+ nouveau contact</a>
  </div>

  <div class="fl-bas">
  <select name="cat">
    <option value="">Toutes les catégories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= e($c['categorie']) ?>"<?= $cat === $c['categorie'] ? ' selected' : '' ?>><?=
        e($c['categorie']) ?> (<?= $c['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="reg">
    <option value="">Toutes les régions</option>
    <?php foreach ($regions as $r): ?>
      <option value="<?= e((string)$r['region']) ?>"<?= $reg === $r['region'] ? ' selected' : '' ?>><?=
        e((string)$r['region']) ?> (<?= (int)$r['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="part">
    <option value="">Toutes les participations</option>
    <?php foreach ($lesParts as $x => $n): ?>
      <option value="<?= e((string)$x) ?>"<?= $part === (string)$x ? ' selected' : '' ?>><?=
        e((string)$x) ?> (<?= (int)$n ?>)</option>
    <?php endforeach; ?>
  </select>
  <?php if ($lesDirs): ?>
  <select name="dir">
    <option value="">Toutes les associations</option>
    <?php foreach ($lesDirs as $x => $n): ?>
      <option value="<?= e((string)$x) ?>"<?= $dir === (string)$x ? ' selected' : '' ?>><?=
        e((string)$x) ?><?= $n ? ' (' . (int)$n . ')' : '' ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <select name="pays">
    <option value="">Tous les pays</option>
    <?php foreach ($payss as $p): ?>
      <option value="<?= e($p['pays_struct']) ?>"<?= $pays === $p['pays_struct'] ? ' selected' : '' ?>><?=
        e($p['pays_struct']) ?> (<?= $p['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <?php if ($q !== '' || $cat !== '' || $reg !== '' || $part !== '' || $dir !== '' || $pays !== ''): ?>
    <a class="vider" href="/dashboard.php?e=contacts">tout effacer</a>
  <?php endif; ?>
  </div>
</form>
<?php dash_flash_html(); ?>
<style>
.filtres.deux-lignes{display:block}
.fl-haut{display:flex;align-items:center;gap:10px;margin-bottom:9px}
/* Le champ prend toute la place restante: c'est le geste principal, et un
   champ court invite à taper court. `min-width:0` sinon un flex-item refuse de
   passer sous sa largeur intrinsèque et pousse les boutons hors de l'écran. */
.fl-haut input[type=search]{flex:1 1 auto;min-width:0}
.fl-haut button{white-space:nowrap}
/* `margin-left:0` annule le `margin-left:auto` d'origine: c'est le conteneur
   qui pousse maintenant, et laisser les deux ferait un trou entre le bouton et
   le lien. */
.neuf{margin-left:0;padding:8px 16px;background:var(--jaune);color:#0d0d0d;
  border-radius:4px;text-decoration:none;font-size:13.5px;font-weight:600;white-space:nowrap}
.fl-bas{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.fl-bas select{max-width:100%}
@media (max-width:640px){
  .fl-haut{flex-wrap:wrap}
  .fl-haut input[type=search]{flex:1 1 100%}
}
</style>

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
