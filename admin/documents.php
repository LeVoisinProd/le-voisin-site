<?php
/**
 * Tous les documents de l'espace, en un seul écran.   [V44-DOCS] [13.08.2026]
 *
 * POURQUOI CETTE PAGE EXISTE
 *
 * Les documents n'étaient atteignables que par la fiche d'une personne. Avec
 * soixante-dix-sept comptes, chercher « les justificatifs de frais d'Encontro
 * en 2026 » voulait dire ouvrir soixante-dix-sept fiches, et une clôture
 * annuelle devenait un travail de plusieurs jours. Un doublon déposé deux fois
 * par la même personne, lui, ne se voyait jamais : les deux lignes étaient
 * dans la même fiche mais rien ne disait qu'elles portaient le même fichier.
 *
 * CE QU'ELLE SERT VRAIMENT, ET CE N'EST PAS LA SUPPRESSION
 *
 * En fin d'année les documents ne se jettent pas : ils se téléchargent en
 * bloc, et ils servent à la comptabilité et à l'archive. Ce dont le bureau a
 * besoin, c'est de les RETROUVER et de les CLASSER — y compris par un agent,
 * qui ne peut pas ouvrir soixante-dix-sept fiches ni deviner ce qu'il y a dans
 * un PDF. D'où les deux sorties de cette page :
 *
 *   L'INDEX CSV, qui est la vraie pièce. Une ligne par document, avec tout ce
 *   qui permet de le classer sans l'ouvrir : la personne, l'association, le
 *   projet, la rubrique, le montant, la date, le nom du fichier et son état.
 *   Le montant est LU DANS LE NOM DU FICHIER, parce qu'il n'a pas de colonne :
 *   la nomenclature du 13.08.2026 le met en tête, et c'est ce qui la rend
 *   exploitable par une machine.
 *
 *   L'ARCHIVE ZIP, qui reprend la sélection à l'écran et la range en dossiers
 *   Association / Année / Rubrique. Elle porte l'index CSV à sa racine, pour
 *   qu'une archive détachée du site reste lisible.
 *
 * La suppression reste possible, sans exception et y compris en lot : c'est
 * l'administration qui décide, un doublon est un doublon. Elle passe par un
 * écran de confirmation qui montre CE QUI VA PARTIR, nommément, parce qu'un
 * compte suffit à cacher une erreur de filtre.
 *
 * Fichier neuf, volontairement : sur ce serveur le cache d'opcode empêche la
 * mise à jour d'index.php, et un fichier neuf compile toujours.
 */
require __DIR__ . '/_inc.php';
Auth::requireAdmin();

$avecAssoc  = MemberDocs::colonneAssoc();
$avecStatut = MemberDocs::colonneStatut();

/* ---- Les filtres, tous facultatifs et tous dans l'adresse ------------------
   Dans l'adresse et pas en session : un filtre qui survit à la navigation
   finit par mentir. On croit voir tous les documents, on en voit un dixième,
   et l'on supprime « tout » en pensant vider une année. */
$fAnnee  = preg_replace('/[^0-9]/', '', (string)($_GET['annee'] ?? ''));
$fCat    = (string)($_GET['cat'] ?? '');
$fAssoc  = trim((string)($_GET['assoc'] ?? ''));
$fQui    = (int)($_GET['qui'] ?? 0);
$fTexte  = trim((string)($_GET['q'] ?? ''));
$fDouble = (string)($_GET['double'] ?? '') === '1';

if (!array_key_exists($fCat, MemberDocs::CATEGORIES)) $fCat = '';

$ou = [];
$ar = [];
if ($fAnnee !== '') { $ou[] = 'YEAR(d.created_at) = ?';  $ar[] = (int)$fAnnee; }
if ($fCat   !== '') { $ou[] = 'd.category = ?';          $ar[] = $fCat; }
if ($fQui    >  0)  { $ou[] = 'd.collaborator_id = ?';   $ar[] = $fQui; }
if ($avecAssoc && $fAssoc !== '') { $ou[] = 'd.assoc = ?'; $ar[] = $fAssoc; }
if ($fTexte !== '') {
    $ou[] = '(d.filename LIKE ? OR d.title LIKE ? OR c.name LIKE ?)';
    $like = '%' . $fTexte . '%';
    array_push($ar, $like, $like, $like);
}
$where = $ou ? 'WHERE ' . implode(' AND ', $ou) : '';

$docs = DB::all(
    'SELECT d.*, c.name AS personne, c.email AS courriel
       FROM member_documents d
       LEFT JOIN collaborators c ON c.id = d.collaborator_id
     ' . $where . '
      ORDER BY d.created_at DESC, d.id DESC', $ar);

/* ---- Les doublons ---------------------------------------------------------
   Deux documents de la MÊME personne, de la même rubrique et du même poids à
   l'octet près. Le nom ne sert pas de critère : la personne qui redépose son
   justificatif le renomme souvent, ou le site le renomme pour elle, et deux
   noms différents cachent alors le même fichier. Le poids, lui, ne ment pas. */
$clefs = [];
$douteux = [];
foreach ($docs as $d) {
    $k = (int)$d['collaborator_id'] . '|' . (string)$d['category'] . '|' . (int)$d['size'];
    if (isset($clefs[$k])) { $douteux[(int)$d['id']] = true; $douteux[$clefs[$k]] = true; }
    else $clefs[$k] = (int)$d['id'];
}
if ($fDouble) $docs = array_values(array_filter($docs, fn($d) => isset($douteux[(int)$d['id']])));

/**
 * Le montant écrit en tête du nom de fichier, s'il y est.
 *
 * La nomenclature du 13.08.2026 commence par « 2100_CHF_… » ou « 44.50_CHF_… ».
 * Rien avant cette date ne la suit, et ces lignes-là rendent simplement une
 * case vide : un tableau qui inventerait un montant serait pire que muet.
 */
function lv_doc_montant(string $nom): array
{
    return preg_match('/^([0-9]+(?:\.[0-9]+)?)_(CHF|EUR)_/', $nom, $m)
         ? [$m[1], $m[2]] : ['', ''];
}

/**
 * Une ligne d'index, la même pour l'écran, le CSV et l'archive.
 *
 * La table des projets est lue UNE fois et gardée : projetChoix() interroge la
 * base à chaque appel, et cette fonction-ci est appelée une fois par document.
 * Cinq cents pièces faisaient cinq cents fois la même requête, plus autant
 * pendant la fabrication de l'archive.
 */
function lv_doc_index(array $d, bool $avecStatut): array
{
    static $projets = null;
    if ($projets === null) $projets = MemberDocs::projetChoix('fr');

    [$montant, $devise] = lv_doc_montant((string)($d['filename'] ?? ''));
    return [
        'id'           => (int)$d['id'],
        'date'         => substr((string)($d['created_at'] ?? ''), 0, 10),
        'personne'     => (string)($d['personne'] ?? ''),
        'courriel'     => (string)($d['courriel'] ?? ''),
        'association'  => (string)($d['assoc'] ?? ''),
        'projet'       => (string)($projets[(int)($d['project_id'] ?? 0)] ?? ''),
        'rubrique'     => MemberDocs::catLabel((string)($d['category'] ?? ''), 'fr'),
        'rubrique_clef'=> (string)($d['category'] ?? ''),
        'montant'      => $montant,
        'devise'       => $devise,
        'fichier'      => (string)($d['filename'] ?? ''),
        'octets'       => (int)($d['size'] ?? 0),
        'deposé_par'   => (string)($d['uploaded_by'] ?? '') === 'member' ? 'la personne' : 'le bureau',
        'etat'         => $avecStatut ? (string)($d['status'] ?? '') : '',
        'signature'    => (string)($d['sign_status'] ?? ''),
    ];
}

/* ---- Sorties : l'index, puis l'archive ------------------------------------
   Avant toute écriture HTML, sinon l'entête est déjà partie. */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="documents_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    /* Le BOM, parce que ce fichier finira dans Excel et qu'Excel lit un CSV
       sans BOM en latin-1 : « Frédéric » y devient « FrÃ©dÃ©ric ». */
    fwrite($out, "\xEF\xBB\xBF");
    $entetes = null;
    foreach ($docs as $d) {
        $l = lv_doc_index($d, $avecStatut);
        if ($entetes === null) { $entetes = array_keys($l); fputcsv($out, $entetes, ';'); }
        fputcsv($out, array_values($l), ';');
    }
    if ($entetes === null) fputcsv($out, ['aucun document'], ';');
    fclose($out);
    exit;
}

if (($_GET['export'] ?? '') === 'zip') {
    if (!class_exists('ZipArchive')) { flash(ta('doc_no_zip'), 'err'); redirect('/admin/documents.php'); }

    /* Une archive se fabrique sur le disque et non en mémoire : soixante-dix
       fiches de salaire tiennent, sept cents contrats non, et le processus
       meurt sans rien dire. Le fichier temporaire part dès l'envoi terminé. */
    $tmp = tempnam(sys_get_temp_dir(), 'lvdocs');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        flash(ta('doc_no_zip'), 'err');
        redirect('/admin/documents.php');
    }

    $lignes = [];
    $manquants = 0;
    foreach ($docs as $d) {
        $l = lv_doc_index($d, $avecStatut);
        $lignes[] = $l;
        $chemin = MemberDocs::dir((int)$d['id']) . '/' . (string)$d['filename'];
        if (!is_file($chemin)) { $manquants++; continue; }
        /* Association / Année / Rubrique : l'ordre dans lequel la comptabilité
           cherche. Un dossier par personne ferait treize cents dossiers d'un
           document chacun. */
        $dossier = ($l['association'] !== '' ? $l['association'] : 'Sans association')
                 . '/' . (substr($l['date'], 0, 4) ?: 'Sans date')
                 . '/' . ($l['rubrique'] !== '' ? $l['rubrique'] : 'Sans rubrique');
        $dossier = preg_replace('#[^A-Za-z0-9 /._-]+#', '', $dossier);
        $zip->addFile($chemin, $dossier . '/' . $l['fichier']);
    }

    /* L'index voyage DANS l'archive. Une archive posée sur un disque dur trois
       ans plus tard n'a plus de site derrière elle pour expliquer ses dossiers. */
    $csv = "\xEF\xBB\xBF";
    if ($lignes) {
        $csv .= implode(';', array_keys($lignes[0])) . "\r\n";
        foreach ($lignes as $l) {
            $csv .= implode(';', array_map(
                fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $l)) . "\r\n";
        }
    }
    $zip->addFromString('index.csv', $csv);
    $zip->close();

    /* Un fichier absent du disque ne fait pas échouer l'archive : mieux vaut
       les mille neuf cents autres et une note, que rien du tout la veille d'un
       bouclement. La note va au même journal que le courrier, qui est celui
       qu'on lit dans les réglages. */
    if ($manquants > 0) {
        @file_put_contents(LV_APP . '/logs/mail.log',
            '[' . date('Y-m-d H:i:s') . "] Archive documents : $manquants fichier(s) absent(s) du disque\n",
            FILE_APPEND);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="documents_' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ---- Supprimer, en lot, avec confirmation nommée --------------------------
   Sans exception : l'administration décide. La confirmation ne montre pas un
   compte mais la liste, parce qu'un « 214 documents » ne dit pas si le filtre
   portait sur l'année ou sur l'association. */
$aSupprimer = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    $action = (string)($_POST['lv_action'] ?? '');

    if (!$ids) {
        flash(ta('doc_none_selected'), 'err');
    } elseif ($action === 'suppr_confirmer') {
        $aSupprimer = DB::all(
            'SELECT d.*, c.name AS personne FROM member_documents d
               LEFT JOIN collaborators c ON c.id = d.collaborator_id
              WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
              ORDER BY c.name, d.created_at DESC', $ids);
    } elseif ($action === 'suppr') {
        $n = 0;
        foreach ($ids as $i) { MemberDocs::delete($i); $n++; }
        flash(ta('doc_deleted', $n));
        redirect('/admin/documents.php');
    }
}

/* ---- Ce qui remplit les menus de filtre ---------------------------------- */
$annees = array_values(array_filter(array_map(
    fn($r) => (string)$r['a'],
    DB::all('SELECT DISTINCT YEAR(created_at) AS a FROM member_documents ORDER BY a DESC'))));
$gens = DB::all('SELECT id, name, email FROM collaborators ORDER BY name, email');
$assocs = $avecAssoc
    ? array_values(array_filter(array_map(fn($r) => (string)$r['assoc'],
        DB::all('SELECT DISTINCT assoc FROM member_documents WHERE assoc <> "" ORDER BY assoc'))))
    : [];

$total = 0;
foreach ($docs as $d) $total += (int)$d['size'];

$csrf = Auth::csrfField();
admin_top(ta('doc_title'), 'documents');
?>
<div class="page-head">
  <h1><?= e(ta('doc_title')) ?></h1>
  <div class="actions">
    <?php $q = $_GET; unset($q['export']); $qs = http_build_query($q); ?>
    <a class="btn small ghost" href="<?= e(admin_url('documents.php?' . ($qs ? $qs . '&' : '') . 'export=csv')) ?>"><?= e(ta('doc_csv')) ?></a>
    <a class="btn small" href="<?= e(admin_url('documents.php?' . ($qs ? $qs . '&' : '') . 'export=zip')) ?>"><?= e(ta('doc_zip')) ?></a>
  </div>
</div>
<p class="hint"><?= e(ta('doc_intro')) ?></p>

<?php if ($aSupprimer): ?>
<div class="panel doc-danger">
  <h2><?= e(ta('doc_del_confirm', count($aSupprimer))) ?></h2>
  <p class="hint"><?= e(ta('doc_del_warn')) ?></p>
  <div class="rowlist">
    <?php foreach ($aSupprimer as $d): ?>
    <div class="rowitem">
      <span class="row-main"><strong><?= e((string)($d['filename'] ?? '')) ?></strong>
        <em><?= e((string)($d['personne'] ?? '')) ?> ·
            <?= e(MemberDocs::catLabel((string)$d['category'], 'fr')) ?> ·
            <?= e(Dates::afficher((string)($d['created_at'] ?? ''))) ?></em></span>
    </div>
    <?php endforeach; ?>
  </div>
  <form method="post">
    <?= $csrf ?>
    <input type="hidden" name="lv_action" value="suppr">
    <?php foreach ($aSupprimer as $d): ?>
    <input type="hidden" name="ids[]" value="<?= (int)$d['id'] ?>">
    <?php endforeach; ?>
    <p>
      <button class="btn ce-del" type="submit"><?= e(ta('doc_del_go')) ?></button>
      <a class="btn ghost" href="<?= e(admin_url('documents.php')) ?>"><?= e(ta('doc_cancel')) ?></a>
    </p>
  </form>
</div>
<?php endif; ?>

<form class="panel doc-filtres" method="get">
  <div class="doc-f-grille">
    <label class="f"><span class="f-label"><?= e(ta('doc_f_year')) ?></span>
      <select name="annee">
        <option value=""><?= e(ta('doc_f_all')) ?></option>
        <?php foreach ($annees as $a): ?>
        <option value="<?= e($a) ?>"<?= $fAnnee === $a ? ' selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="f"><span class="f-label"><?= e(ta('doc_f_cat')) ?></span>
      <select name="cat">
        <option value=""><?= e(ta('doc_f_all')) ?></option>
        <?php foreach (MemberDocs::CATEGORIES as $k => $lab): ?>
        <option value="<?= e($k) ?>"<?= $fCat === $k ? ' selected' : '' ?>><?= e(MemberDocs::catLabel($k, 'fr')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($assocs): ?>
    <label class="f"><span class="f-label"><?= e(ta('doc_f_assoc')) ?></span>
      <select name="assoc">
        <option value=""><?= e(ta('doc_f_all')) ?></option>
        <?php foreach ($assocs as $a): ?>
        <option value="<?= e($a) ?>"<?= $fAssoc === $a ? ' selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <label class="f"><span class="f-label"><?= e(ta('doc_f_who')) ?></span>
      <select name="qui">
        <option value="0"><?= e(ta('doc_f_all')) ?></option>
        <?php foreach ($gens as $g): ?>
        <option value="<?= (int)$g['id'] ?>"<?= $fQui === (int)$g['id'] ? ' selected' : '' ?>><?= e($g['name'] ?: $g['email']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="f"><span class="f-label"><?= e(ta('doc_f_text')) ?></span>
      <input type="search" name="q" value="<?= e($fTexte) ?>" placeholder="<?= e(ta('doc_f_text_ph')) ?>">
    </label>
  </div>
  <p class="doc-f-bas">
    <label class="doc-f-case">
      <input type="checkbox" name="double" value="1"<?= $fDouble ? ' checked' : '' ?>>
      <?= e(ta('doc_f_double', count($douteux))) ?>
    </label>
    <button class="btn small" type="submit"><?= e(ta('doc_f_go')) ?></button>
    <a class="btn small ghost" href="<?= e(admin_url('documents.php')) ?>"><?= e(ta('doc_f_reset')) ?></a>
  </p>
</form>

<?php if (!$docs): ?>
<p class="hint"><?= e(ta('doc_empty')) ?></p>
<?php else: ?>
<form method="post" class="panel">
  <?= $csrf ?>
  <input type="hidden" name="lv_action" value="suppr_confirmer">
  <p class="doc-compte">
    <strong><?= count($docs) ?></strong> <?= e(ta('doc_count')) ?> · <?= e(Docs::human($total)) ?>
    <label class="doc-f-case"><input type="checkbox" class="doc-tout"> <?= e(ta('doc_all')) ?></label>
  </p>
  <div class="rowlist">
    <?php foreach ($docs as $d): $l = lv_doc_index($d, $avecStatut); ?>
    <div class="rowitem<?= isset($douteux[(int)$d['id']]) ? ' doc-double' : '' ?>">
      <input class="doc-case" type="checkbox" name="ids[]" value="<?= (int)$d['id'] ?>">
      <span class="row-main">
        <strong><?= e($l['fichier']) ?><?php if (isset($douteux[(int)$d['id']])): ?>
          <span class="badge warn"><?= e(ta('doc_dup')) ?></span><?php endif; ?></strong>
        <em><?= e(implode(' · ', array_filter([
              $l['personne'],
              $l['association'],
              $l['rubrique'],
              $l['montant'] !== '' ? $l['montant'] . ' ' . $l['devise'] : '',
              Dates::afficher((string)($d['created_at'] ?? '')),
              Docs::human($l['octets']),
            ]))) ?></em>
      </span>
      <a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . (int)$d['collaborator_id'] . '&dl=' . (int)$d['id'])) ?>"><?= e(ta('com_download')) ?></a>
      <a class="btn small ghost" href="<?= e(admin_url('collaborator-edit.php?id=' . (int)$d['collaborator_id'])) ?>"><?= e(ta('doc_fiche')) ?></a>
    </div>
    <?php endforeach; ?>
  </div>
  <p><button class="btn ce-del" type="submit"><?= e(ta('doc_del_sel')) ?></button></p>
</form>
<script>
/* Tout cocher. Un script de trois lignes plutôt qu'un attribut onclick : la
   page en aurait porté un par ligne, et l'administration a déjà une politique
   de contenu qui les refuse. */
document.querySelectorAll('.doc-tout').forEach(function (t) {
  t.addEventListener('change', function () {
    document.querySelectorAll('.doc-case').forEach(function (c) { c.checked = t.checked; });
  });
});
</script>
<?php endif; ?>
<?php admin_bottom();
