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
           'marge_defaut','discipline','direction','debut_collab','statut','comite','notes'];
$err = $saisi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    foreach ($CHAMPS as $c) $saisi[$c] = trim((string)($_POST[$c] ?? ''));

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
        $vals = array_map(fn($c) => $saisi[$c] === '' ? null : $saisi[$c], $CHAMPS);
        if ($id > 0) {
            $set = implode(',', array_map(fn($c) => "$c=?", $CHAMPS));
            DB::pdo()->prepare("UPDATE organisation SET $set WHERE id = ?")->execute([...$vals, $id]);
            dash_flash('Fiche enregistrée.');
        } else {
            $q = implode(',', array_fill(0, count($CHAMPS), '?'));
            DB::pdo()->prepare('INSERT INTO organisation (' . implode(',', $CHAMPS) . ") VALUES ($q)")
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
    <form class="saisie" method="post"
          action="/dashboard.php?e=associations<?= $id > 0 ? '&amp;o=' . $id : '' ?>&amp;mod=1">
      <?= Auth::csrfField() ?>
      <div class="grille">
        <div class="titre-bloc">Identité</div>
        <?php
        ch('genre', 'Nature', $v('genre') ?: 'artiste', $err, ['type'=>'select','choix'=>$GENRES,
           'aide' => 'Association: une entité juridique de la maison']);
        ch('nom', 'Nom', $v('nom'), $err, ['requis'=>true]);
        ch('nom_legal', 'Nom légal', $v('nom_legal'), $err, ['aide'=>'S\'il diffère du nom d\'usage']);
        ch('statut', 'Statut', $v('statut') ?: 'actif', $err, ['type'=>'select','choix'=>$STATUTS]);
        ch('discipline', 'Discipline', $v('discipline'), $err);
        ch('direction', 'Direction artistique', $v('direction'), $err);
        ch('debut_collab', 'Début de collaboration', $v('debut_collab'), $err, ['type'=>'date']);

        echo '<div class="titre-bloc">Administration</div>';
        ch('ide', 'IDE', $v('ide'), $err, ['placeholder'=>'CHE-123.456.789',
           'aide'=>'Avec ou sans les points, il est remis en forme']);
        ch('registre', 'Registre', $v('registre'), $err);
        ch('avs_employeur', 'AVS employeur', $v('avs_employeur'), $err);
        ch('ree', 'REE', $v('ree'), $err);
        ch('siret', 'SIRET', $v('siret'), $err, ['aide'=>'Les entités françaises']);
        ch('pays', 'Pays', $v('pays'), $err, ['aide'=>'Décide des obligations sociales et du A1']);
        ch('canton', 'Canton', $v('canton'), $err);
        ch('adresse', 'Adresse', $v('adresse'), $err, ['large'=>true]);

        echo '<div class="titre-bloc">Contact</div>';
        ch('email', 'Courriel', $v('email'), $err, ['type'=>'email']);
        ch('telephone', 'Téléphone', $v('telephone'), $err);
        ch('site', 'Site', $v('site'), $err);
        ch('instagram', 'Instagram', $v('instagram'), $err);

        echo '<div class="titre-bloc">Banque</div>';
        ch('banque_nom', 'Banque', $v('banque_nom'), $err);
        ch('banque_iban', 'IBAN', $v('banque_iban'), $err,
           ['aide'=>'Figure sur chaque devis et chaque contrat']);
        ch('banque_bic', 'BIC', $v('banque_bic'), $err);

        echo '<div class="titre-bloc">Ce qui se répète entre les shows</div>';
        ch('devise_defaut', 'Devise', $v('devise_defaut') ?: 'CHF', $err,
           ['type'=>'select','choix'=>['CHF'=>'CHF','EUR'=>'EUR']]);
        ch('frais_booking', 'Frais de booking', $v('frais_booking'), $err,
           ['aide'=>'En pourcentage du cachet']);
        ch('marge_defaut', 'Marge', $v('marge_defaut'), $err,
           ['aide'=>'10 % dans la maison depuis le 14.08.2026']);

        echo '<div class="titre-bloc">Le reste</div>';
        ch('comite', 'Comité', $v('comite'), $err, ['type'=>'textarea','large'=>true,'rows'=>2]);
        ch('notes', 'Notes', $v('notes'), $err, ['type'=>'textarea','large'=>true]);
        ?>
      </div>
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
    $st = DB::pdo()->prepare("SELECT * FROM booking WHERE supprime_le IS NULL
                               AND (artiste = ? OR projet = ?) ORDER BY date_debut DESC LIMIT 12");
    $st->execute([$o['nom'], $o['nom']]);
    $dates = $st->fetchAll();

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

      <h3 class="sect">Dates <span class="n"><?= count($dates) ?><?= count($dates) === 12 ? ' dernières' : '' ?></span></h3>
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

$where = ['supprime_le IS NULL']; $args = [];
if (isset($GENRES[$genre]))   { $where[] = 'genre = ?';  $args[] = $genre; }
if (isset($STATUTS[$statut])) { $where[] = 'statut = ?'; $args[] = $statut; }
if ($q !== '') {
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(nom LIKE ? OR nom_legal LIKE ? OR discipline LIKE ? OR direction LIKE ? OR ide LIKE ?)';
    array_push($args, $like, $like, $like, $like, $like);
}
$sql = implode(' AND ', $where);

$t0 = microtime(true);
$st = DB::pdo()->prepare("SELECT * FROM organisation WHERE $sql ORDER BY genre, nom");
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
  <select name="g">
    <option value="">Tout</option>
    <?php foreach ($GENRES as $k => $v): ?>
      <option value="<?= $k ?>"<?= $genre === $k ? ' selected' : '' ?>><?=
        e(ucfirst($v)) ?>s (<?= $parGenre[$k] ?? 0 ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="st">
    <option value="">Tous les statuts</option>
    <?php foreach ($STATUTS as $k => $v): ?>
      <option value="<?= $k ?>"<?= $statut === $k ? ' selected' : '' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $genre !== '' || $statut !== ''): ?>
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
  <thead><tr><th>Nom</th><th>Nature</th><th>Discipline</th><th>Direction</th>
    <th>Pays</th><th>IDE</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <td><a href="/dashboard.php?e=associations&amp;o=<?= (int)$r['id'] ?>"><?= e($r['nom']) ?></a>
        <?php if ($r['nom_legal'] && $r['nom_legal'] !== $r['nom']): ?>
          <div class="sec"><?= e($r['nom_legal']) ?></div><?php endif; ?></td>
      <td class="sec"><?= e($GENRES[$r['genre']]) ?></td>
      <td class="sec"><?= e($r['discipline'] ?? '') ?></td>
      <td class="sec"><?= e($r['direction'] ?? '') ?></td>
      <td class="sec"><?= e(trim(($r['pays'] ?? '') . ' ' . ($r['canton'] ?? ''))) ?></td>
      <td class="sec"><?= e($r['ide'] ?? '') ?></td>
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
@media (prefers-color-scheme:dark){:root:not([data-theme=light]) .et{
  background:transparent!important;color:inherit!important}}
</style>
<?php dash_bas(); ?>
