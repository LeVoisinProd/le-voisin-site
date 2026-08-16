<?php
/**
 * Écran Associations et artistes. [16.08.2026]
 *
 * Anna: « Les profils de chaque asso / artiste regroupent toutes les infos déjà
 * créées dans le dashboard, plus tout ce qui se répète entre les shows: modèles
 * de contrat, modèles de deal, devises et frais de booking par artiste. »
 *
 * LES DEUX DANS UN MÊME ÉCRAN, comme elle l'a demandé. Une association est une
 * entité juridique de la maison, un artiste est une compagnie accompagnée; ils
 * vivent au même endroit du travail et une fiche passe parfois de l'un à
 * l'autre quand un projet grandit.
 *
 * CE QUE LA REPRISE A RÉVÉLÉ, et qui n'est pas un défaut de données: dix noms
 * existent des DEUX côtés. CRILE, Encontro, Tympan et sept autres sont à la fois
 * une association juridique et une compagnie accompagnée. C'est la réalité, pas
 * un doublon: la compagnie a monté son association. L'écran le montre au lieu de
 * choisir à la place de qui lit.
 */
declare(strict_types=1);

const PAR_PAGE = 60;

$STATUTS = ['actif' => 'actif', 'pause' => 'en pause', 'termine' => 'terminé'];
$GENRES  = ['association' => 'association', 'artiste' => 'artiste'];

$id = (int)($_GET['o'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// ENREGISTRER
// ═══════════════════════════════════════════════════════════════════════════

$CHAMPS = ['genre','nom','nom_legal','ide','registre','avs_employeur','ree','siret',
           'pays','canton','adresse','email','telephone','site','instagram',
           'banque_nom','banque_iban','banque_bic','devise_defaut','frais_booking',
           'marge_defaut','discipline','direction','debut_collab','statut','comite','notes',
    /* Ajoutés le 16.08.2026, migration 019: la conformité suisse des quatre
       onglets que la table ne portait pas. */
    'forme_juridique','date_creation','reference_poste','cp','ville',
    'contact_prenom','contact_nom',
    'rc_pro','rc_police','laa','lpp','ampg','assureur_laa','assureur_lpp','trianon',
    'avs_inscription','caisse_avs','convention_coll',
    'canton_fiscal','contribuable_cant','tva_ch','tva_ch_num','notes_fisc_ch',
    'rna','urssaf','audiens','tva_fr','tva_fr_num','notes_fisc_fr',
    'email_mdp','instagram_mdp'];
$err = $saisi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['decl'] ?? '') !== '' || ($_POST['is_act'] ?? '') !== '')) {
    Auth::requireCsrf();
    dash_exige_ecriture('associations');
    $an = (int)($_POST['annee'] ?? date('Y'));

    if (($_POST['decl'] ?? '') !== '') {
        /* Une ligne n'existe que si l'on a cliqué: ON DUPLICATE la crée au
           premier clic et la fait tourner aux suivants. */
        $t = (string)($_POST['type'] ?? ''); $pe = (string)($_POST['periode'] ?? '');
        $st = (string)($_POST['statut'] ?? 'a_faire');
        if (in_array($t, ['laa','avs'], true)
            && in_array($pe, ['T1','T2','T3','T4','annuel'], true)
            && in_array($st, ['a_faire','envoye','paye','sans_objet'], true)
            && $id > 0 && $an >= 2000 && $an <= 2100) {
            DB::pdo()->prepare(
                'INSERT INTO organisation_declaration (organisation_id,type,annee,periode,statut)
                 VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE statut=VALUES(statut)')
              ->execute([$id, $t, $an, $pe, $st]);
        }
    } elseif (($_POST['is_act'] ?? '') === 'ajouter' && $id > 0) {
        $c = strtoupper(trim((string)($_POST['canton'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $c)) {
            try {
                DB::insert('organisation_is', ['organisation_id'=>$id, 'canton'=>$c,
                    'compte'=>trim((string)($_POST['compte'] ?? '')) ?: null,
                    'notes'=>trim((string)($_POST['notes'] ?? '')) ?: null]);
                dash_flash('Compte cantonal ajouté.');
            } catch (Throwable $e) {
                /* La clef unique (association, canton) empêche deux comptes pour
                   le même canton: c'est voulu, il n'y en a qu'un. */
                dash_flash('Ce canton a déjà un compte pour cette association.', 'err');
            }
        }
    } elseif (($_POST['is_act'] ?? '') === 'retirer' && $id > 0) {
        DB::delete('organisation_is', 'id = ? AND organisation_id = ?',
                   [(int)($_POST['ligne'] ?? 0), $id]);
        dash_flash('Compte cantonal retiré.');
    }
    redirect('/dashboard.php?e=associations&o=' . $id . '&mod=1&an=' . $an);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('associations');
    foreach ($CHAMPS as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

    /* LES DEUX MOTS DE PASSE. Chiffrés par le même Crypto.php qui protège les
       IBAN et les AVS des fiches personnelles — un dump de cette base se lit
       sans clé, et l'on en produit un par jour qui part dans le Drive.

       Un champ laissé vide NE VIDE PAS le mot de passe enregistré: l'écran ne
       renvoie jamais le secret au navigateur, donc vide veut dire « je n'y ai
       pas touché » et non « efface ». Pour effacer, on écrit un espace. */
    $MDP = ['email_mdp', 'instagram_mdp'];
    foreach ($MDP as $m) {
        $brut = (string)($_POST[$m] ?? '');
        if ($brut === '') { unset($saisi[$m]); continue; }
        $saisi[$m] = trim($brut) === '' ? '' : Crypto::chiffrer(trim($brut));
    }

    if (($_POST['action'] ?? '') === 'supprimer' && $id > 0) {
        DB::pdo()->prepare('UPDATE organisation SET supprime_le = NOW() WHERE id = ?')->execute([$id]);
        dash_flash('Fiche supprimée. Elle reste en base et peut être rétablie.');
        redirect('/dashboard.php?e=associations');
    }

    if ($saisi['nom'] === '') $err['nom'] = 'Sans nom, la fiche ne se retrouve pas.';
    if (!isset($GENRES[$saisi['genre']]))     $saisi['genre']  = 'artiste';
    if (!isset($STATUTS[$saisi['statut']]))   $saisi['statut'] = 'actif';
    if ($saisi['devise_defaut'] === '')       $saisi['devise_defaut'] = 'CHF';

    if ($saisi['debut_collab'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saisi['debut_collab'])) {
        $err['debut_collab'] = 'Format attendu: AAAA-MM-JJ.';
    }
    foreach (['frais_booking', 'marge_defaut'] as $p) {
        if ($saisi[$p] === '') continue;
        $saisi[$p] = str_replace([',', '%', ' '], ['.', '', ''], $saisi[$p]);
        if (!is_numeric($saisi[$p])) $err[$p] = 'Un pourcentage, en chiffres.';
    }
    /* L'IDE se saisit avec ou sans les points, et sur un formulaire on tape ce
       qu'on a sous les yeux. On normalise plutôt que de refuser. */
    if ($saisi['ide'] !== '') {
        $n = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $saisi['ide']));
        if (preg_match('/^CHE(\d{9})$/', $n, $m)) {
            $saisi['ide'] = 'CHE-' . substr($m[1],0,3) . '.' . substr($m[1],3,3) . '.' . substr($m[1],6,3);
        }
    }

    if (!$err) {
        /* Les champs que le POST n'a pas apportés — les mots de passe laissés
           vides — sortent de la mise à jour au lieu d'y entrer à NULL. Sans
           cela, ouvrir la fiche et enregistrer effacerait le mot de passe sans
           que personne ne l'ait demandé. */
        $cols = array_values(array_filter($CHAMPS, fn($c) => array_key_exists($c, $saisi)));
        $vals = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $cols);
        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $cols));
            DB::pdo()->prepare("UPDATE organisation SET $set WHERE id = ?")->execute([...$vals, $id]);
            dash_flash('Fiche enregistrée.');
        } else {
            $q = implode(',', array_fill(0, count($cols), '?'));
            DB::pdo()->prepare('INSERT INTO organisation (' . implode(',', $cols) . ") VALUES ($q)")
                     ->execute($vals);
            $id = (int)DB::pdo()->lastInsertId();
            dash_flash('Fiche créée.');
        }
        redirect('/dashboard.php?e=associations&o=' . $id);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// LE FORMULAIRE
// ═══════════════════════════════════════════════════════════════════════════

if (isset($_GET['mod']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $o = $id > 0 ? DB::one('SELECT * FROM organisation WHERE id = ? AND supprime_le IS NULL', [$id]) : [];
    if ($id > 0 && !$o) { dash_haut('associations'); echo '<p class="vide">Cette fiche n\'existe pas.</p>'; dash_bas(); return; }
    $v = fn(string $c) => $saisi[$c] ?? ($o[$c] ?? '');

    dash_haut('associations', $id > 0 ? 'modifier' : 'nouvelle fiche');
    dash_form_style();
    if ($err) echo '<div class="flash err">Rien n\'a été enregistré: ' . count($err)
                 . ' champ(s) à corriger. Ce que vous aviez saisi est conservé.</div>';
    ?>
    <div class="fil"><a href="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>">← retour</a></div>
    <?php /* Les boutons radio sont ici, AVANT le formulaire, pour que le CSS
             atteigne aussi les panneaux des grilles, qui sont des formulaires
             à part — le HTML interdit de les imbriquer. */
    $annee = (int)($_GET['an'] ?? date('Y'));
    if ($annee < 2000 || $annee > 2100) $annee = (int)date('Y');
    $ecrit = dash_droit('associations', dash_role()) === 'ecrit';
    require __DIR__ . '/_assoc_barre.php'; ?>
    <form class="saisie" method="post"
          action="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>&amp;mod=1&amp;an=<?= $annee ?>">
      <?= Auth::csrfField() ?>
      <?php require __DIR__ . '/_assoc_onglets.php'; ?>
      <div class="actions">
        <button type="submit"><?= $id > 0 ? 'Enregistrer' : 'Créer' ?></button>
        <a class="sec2" href="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>">annuler</a>
        <?php if ($id > 0): ?>
        <a class="sup" href="#" onclick="if(confirm('Supprimer cette fiche ? Elle restera en base.')){
             var f=document.createElement('form');f.method='post';
             f.action='/dashboard.php?e=associations&o=<?= $id ?>&mod=1';
             f.innerHTML='<?= addslashes(Auth::csrfField()) ?><input name=action value=supprimer>';
             document.body.appendChild(f);f.submit();}return false;">supprimer</a>
        <?php endif; ?>
      </div>
    </form>
    <?php require __DIR__ . '/_assoc_grilles.php'; ?>
    <style>.fil{padding:12px 26px 0;font-size:13px}.fil a{color:var(--doux);text-decoration:none}</style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($id > 0) {
    $o = DB::one('SELECT * FROM organisation WHERE id = ? AND supprime_le IS NULL', [$id]);
    if (!$o) { dash_haut('associations'); echo '<p class="vide">Cette fiche n\'existe pas.</p>'; dash_bas(); return; }

    /* Les dates de cette organisation. C'est ce qui fait qu'une fiche sert:
       elle relie l'entité à ce qu'elle a joué. */
    /* ── LES DATES DE L'ASSOCIATION, PAR LE VRAI LIEN ───────────────────────
       [16.08.2026] Elle les cherchait en comparant des NOMS: `artiste = 'X' OR
       projet = 'X'`. Ça marchait par accident pour Encontro, dont l'association
       et l'artiste portent le même nom, et ne trouvait RIEN pour Gran
       Chicornia, dont les dates disent « Annina Mosimann ».

       Le lien existe depuis ce matin: `projet_prod.organisation_id` dit quelle
       association porte quelle pièce, et `booking.projet` porte le titre de la
       pièce. On passe donc par là. Une coïncidence de noms n'est pas un lien,
       et surtout: son absence n'est pas l'absence de dates.

       Le compte total est séparé de la liste — on n'affiche que douze, et dire
       « 12 » quand il y en a trente cacherait les dix-huit autres. */
    $st = DB::pdo()->prepare(
        "SELECT b.* FROM booking b
           JOIN projects p     ON p.title_fr = b.projet
           JOIN projet_prod pp ON pp.project_id = p.id
          WHERE b.supprime_le IS NULL AND pp.organisation_id = ?
          ORDER BY b.date_debut DESC LIMIT 12");
    $st->execute([$id]);
    $dates = $st->fetchAll();

    $st = DB::pdo()->prepare(
        "SELECT COUNT(*) FROM booking b
           JOIN projects p     ON p.title_fr = b.projet
           JOIN projet_prod pp ON pp.project_id = p.id
          WHERE b.supprime_le IS NULL AND pp.organisation_id = ?");
    $st->execute([$id]);
    $datesN = (int)$st->fetchColumn();

    /* La même entité vue par l'autre bout: association et artiste à la fois. */
    $st = DB::pdo()->prepare('SELECT id, genre, source FROM organisation
                               WHERE nom = ? AND id <> ? AND supprime_le IS NULL');
    $st->execute([$o['nom'], $id]);
    $jumelles = $st->fetchAll();

    dash_haut('associations', e($GENRES[$o['genre']]) . ' · ' . e($STATUTS[$o['statut']] ?? ''));
    ?>
    <div class="fil"><a href="/dashboard.php?e=associations">← toutes les fiches</a>
      <a class="mod" href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1">modifier</a></div>
    <?php dash_flash_html(); ?>
    <div class="zone">
      <h2 class="gros"><?= e($o['nom']) ?></h2>

      <?php if ($jumelles): ?>
      <div class="alerte">Cette entité existe aussi comme
        <?php foreach ($jumelles as $j): ?>
          <a href="/dashboard.php?e=associations&amp;o=<?= (int)$j['id'] ?>"><?= e($GENRES[$j['genre']]) ?></a>
        <?php endforeach; ?>.
        Ce n'est pas un doublon: la compagnie a monté sa propre association, et les
        deux fiches ne portent pas la même chose. Les rapprocher est une décision,
        pas un nettoyage.</div>
      <?php endif; ?>

      <div class="fiche">
      <?php
      $l = function (string $k, $v, string $note = '') {
          if ($v === null || $v === '') return;
          printf('<div class="l"><span class="k">%s</span><span class="v">%s%s</span></div>',
                 e($k), e((string)$v), $note ? '<span class="n">'.e($note).'</span>' : '');
      };
      $l('Nom légal', $o['nom_legal']);
      $l('Discipline', $o['discipline']);
      $l('Direction artistique', $o['direction']);
      $l('Début de collaboration', $o['debut_collab']);
      $l('IDE', $o['ide']);
      $l('Registre', $o['registre']);
      $l('AVS employeur', $o['avs_employeur']);
      $l('REE', $o['ree']);
      $l('SIRET', $o['siret']);
      $l('Pays', trim(($o['pays'] ?? '') . ' ' . ($o['canton'] ? '· ' . $o['canton'] : '')));
      $l('Adresse', $o['adresse']);
      $l('Courriel', $o['email']);
      $l('Téléphone', $o['telephone']);
      $l('Site', $o['site']);
      $l('Instagram', $o['instagram']);
      $l('Banque', $o['banque_nom']);
      $l('IBAN', $o['banque_iban'], 'figure sur les devis');
      $l('BIC', $o['banque_bic']);
      $l('Devise par défaut', $o['devise_defaut']);
      if ($o['frais_booking'] !== null) $l('Frais de booking', $o['frais_booking'] . ' %');
      if ($o['marge_defaut'] !== null)  $l('Marge', $o['marge_defaut'] . ' %');
      $l('Provenance', $o['source'] . ' · ' . $o['source_ref']);
      ?>
      </div>

      <?php if ($o['comite']): ?>
        <div class="bl"><h3>Comité</h3><p><?= nl2br(e($o['comite'])) ?></p></div><?php endif; ?>
      <?php if ($o['notes']): ?>
        <div class="bl"><h3>Notes</h3><p><?= nl2br(e($o['notes'])) ?></p></div><?php endif; ?>

      <h3 class="sect">Dates <span class="n"><?= $datesN ?></span><?php
        if ($datesN > count($dates)): ?> <span class="sec">les <?= count($dates) ?> dernières</span><?php endif; ?></h3>
      <?php if (!$dates): ?>
        <p class="sec">Aucune date rattachée. Le rapprochement se fait sur le nom:
           si les dates portent une autre orthographe, elles n'apparaissent pas ici.</p>
      <?php else: ?>
      <div class="tw"><table><tbody>
        <?php foreach ($dates as $d): ?>
        <tr>
          <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$d['id'] ?>"><?=
            e($d['date_texte'] ?: (string)$d['date_debut']) ?></a></td>
          <td><?= e($d['venue'] ?? '') ?></td>
          <td class="sec"><?= e($d['ville'] ?? '') ?></td>
          <td><span class="et <?= e($d['statut']) ?>"><?= e($d['statut']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
    <style>
    .fil{padding:12px 26px 0;font-size:13px;display:flex;gap:16px}
    .fil a{color:var(--doux);text-decoration:none}
    .fil a.mod{margin-left:auto;color:var(--encre);font-weight:600}
    h2.gros{font-size:21px;margin:0 0 16px}
    h3.sect{font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
      margin:30px 0 8px;border-bottom:1px solid var(--trait);padding-bottom:5px}
    h3.sect .n{font-weight:400}
    .fiche{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0 32px;max-width:940px}
    .fiche .l{display:flex;gap:12px;padding:7px 0;border-bottom:1px solid var(--trait)}
    .fiche .k{color:var(--doux);font-size:12.5px;min-width:150px}
    .fiche .v{font-size:14px}
    .fiche .n{color:var(--doux);font-size:12px;margin-left:8px}
    .bl{margin-top:22px;padding:12px 16px;background:var(--fond2);max-width:800px}
    .bl h3{font-size:13px;margin:0 0 6px}.bl p{margin:0;font-size:14px}
    .alerte{margin:0 0 18px;padding:11px 15px;background:var(--fond2);
      border-left:4px solid var(--orange);font-size:13.5px;max-width:80ch}
    .et{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait)}
    </style>
    <?php dash_bas(); return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q      = trim((string)($_GET['q'] ?? ''));
$genre  = trim((string)($_GET['g'] ?? ''));
$statut = trim((string)($_GET['st'] ?? ''));

/* ── L'ÉCRAN NE PORTE PLUS QUE LES ASSOCIATIONS ─────────────────────────────
   [16.08.2026] Anna: « na pagina Associations et artistes tirar os artistas ».

   Les lignes `genre = 'artiste'` venaient d'une reprise: le dashboard et le CMS
   du site ont chacun une liste d'artistes, et l'import les avait versées ici à
   côté des associations. Elles n'y avaient rien à faire — un artiste n'est pas
   une entité juridique, il n'a ni IDE, ni AVS employeur, ni régime fiscal, et
   les cinq onglets de conformité lui demandaient tout cela pour rien.

   ELLES NE SONT PAS SUPPRIMÉES, elles ne sont plus affichées. En production il
   n'y en a aucune de toute façon — mesuré: 15 lignes, toutes des associations.
   Les artistes vivent dans `artists`, côté site, où ils ont leur page publique. */
$where = ['supprime_le IS NULL', "genre = 'association'"]; $args = [];
if (isset($STATUTS[$statut])) { $where[] = 'statut = ?'; $args[] = $statut; }
if ($q !== '') {
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(nom LIKE ? OR nom_legal LIKE ? OR discipline LIKE ? OR direction LIKE ? OR ide LIKE ?)';
    array_push($args, $like, $like, $like, $like, $like);
}
$sql = implode(' AND ', $where);

$t0 = microtime(true);
$st = DB::pdo()->prepare("SELECT * FROM organisation WHERE $sql ORDER BY nom");
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$parGenre = DB::pdo()->query("SELECT genre, COUNT(*) n FROM organisation
                               WHERE supprime_le IS NULL GROUP BY genre")->fetchAll(PDO::FETCH_KEY_PAIR);
$doubles = (int)DB::pdo()->query("SELECT COUNT(*) FROM (SELECT nom FROM organisation
     WHERE supprime_le IS NULL GROUP BY nom HAVING COUNT(DISTINCT genre) > 1) x")->fetchColumn();

dash_haut('associations', count($lignes) . ' fiche' . (count($lignes)>1?'s':'') . ' · ' . $ms . ' ms');
?>
<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="associations">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, discipline, direction, IDE">
  <select name="st">
    <option value="">Tous les statuts</option>
    <?php foreach ($STATUTS as $k => $v): ?>
      <option value="<?= $k ?>"<?= $statut === $k ? ' selected' : '' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $statut !== ''): ?>
    <a class="vider" href="/dashboard.php?e=associations">tout effacer</a><?php endif; ?>
  <a class="neuf" href="/dashboard.php?e=associations&amp;mod=1">+ nouvelle fiche</a>
</form>
<?php dash_flash_html(); ?>

<?php if ($doubles): ?>
<div class="alerte"><strong><?= $doubles ?> noms existent comme association ET comme artiste.</strong>
  Ce n'est pas un doublon: chaque compagnie accompagnée a monté sa propre association, et les
  deux fiches ne portent pas la même chose. Les rapprocher est une décision, pas un nettoyage.</div>
<?php endif; ?>

<?php if (!$lignes): ?><p class="vide">Aucune fiche.</p><?php else: ?>
<div class="tw"><table>
  <?php /* ── LES CINQ COLONNES ─────────────────────────────────────────────
       [16.08.2026] Choisies par Anna: nom, direction, ville et canton,
       discipline, statut.

       L'IDE SORT DE LA LISTE. C'est un numéro à douze chiffres qu'on ne lit pas
       en balayant une colonne: on le cherche quand on remplit un formulaire, et
       on l'a alors sous les yeux dans la fiche. Une colonne qu'on ne lit jamais
       prend la largeur de celles qu'on lit.

       LA VILLE REMPLACE LE PAYS, avec le canton à côté. « Suisse » sur treize
       lignes sur quinze n'apprend rien; « Genève GE » situe l'association, et
       c'est le canton qui décide de l'impôt à la source. */ ?>
  <thead><tr><th>Nom</th><th>Direction</th><th>Ville, canton</th>
    <th>Discipline</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <td><a href="/dashboard.php?e=associations&amp;o=<?= (int)$r['id'] ?>"><?= e($r['nom']) ?></a>
        <?php if ($r['nom_legal'] && $r['nom_legal'] !== $r['nom']): ?>
          <div class="sec"><?= e($r['nom_legal']) ?></div><?php endif; ?></td>
      <td class="sec"><?= e($r['direction'] ?? '') ?></td>
      <?php /* Le pays ne suit que s'il n'est pas suisse: quinze lignes qui
           répètent « Suisse » cachent les deux qui ne le sont pas. */ ?>
      <td class="sec"><?php
        $lieu = trim((string)($r['ville'] ?? ''));
        if (($r['canton'] ?? '') !== '') $lieu = trim($lieu . ($lieu !== '' ? ' ' : '') . $r['canton']);
        $pays = trim((string)($r['pays'] ?? ''));
        $etr  = $pays !== '' && !in_array(mb_strtolower($pays), ['ch', 'suisse'], true);
        echo e($lieu ?: ($etr ? $pays : ''));
        if ($etr && $lieu !== '') echo '<div class="sec">' . e($pays) . '</div>';
      ?></td>
      <td class="sec"><?= e($r['discipline'] ?? '') ?></td>
      <td><span class="et s-<?= e($r['statut']) ?>"><?= e($STATUTS[$r['statut']] ?? '') ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<style>
.neuf{margin-left:auto;padding:8px 16px;background:var(--jaune);color:#0d0d0d;
  border-radius:4px;text-decoration:none;font-size:13.5px;font-weight:600}
.alerte{margin:16px 26px 0;padding:11px 16px;background:var(--fond2);
  border-left:4px solid var(--orange);font-size:13.5px;max-width:82ch}
.et{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.et.s-actif{background:#e7f6ea;border-color:#bfe3c8;color:#1c5c2e}
.et.s-pause{background:#fff6d9;border-color:#f0dfa3;color:#6b5312}
.et.s-termine{background:var(--fond2);color:var(--doux)}
</style>
<?php dash_bas(); ?>
