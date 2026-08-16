<?php
/**
 * Écran Calendrier. [16.08.2026]
 *
 * Anna: « Le calendrier est le cœur de la plateforme. Chaque show confirmé ou en
 * attente y figure, aux côtés des voyages et de la logistique, si bien que toute
 * votre opération est visible d'un coup d'œil. »
 *
 * CE QU'IL MONTRE AUJOURD'HUI: les bookings, groupés par mois, avec leur statut.
 * Les voyages et la logistique viendront quand la table existera; l'onglet
 * Voyage d'un booking dit déjà ce qui lui manque.
 *
 * LA SAISON PLUTÔT QUE L'ANNÉE CIVILE. Une saison de spectacle va de septembre à
 * août, et couper au 31 décembre sépare l'automne du printemps d'une même
 * tournée. Le dashboard actuel le fait déjà ainsi, dans 15_calendrier.js.
 *
 * PAS DE GRILLE MENSUELLE À CASES. Une grille de sept colonnes montre les jours
 * vides, qui sont la grande majorité: sur 86 bookings répartis sur trois ans,
 * une année civile compte trois cent trente jours sans rien. La liste par mois
 * montre ce qui existe, et rien d'autre.
 */
declare(strict_types=1);

/** Le mois où commence la saison. */
const MOIS_SAISON = 9;

$MOIS = [1=>'janvier','février','mars','avril','mai','juin','juillet','août',
         'septembre','octobre','novembre','décembre'];
$ETIQ = ['option'=>'option','confirmed'=>'confirmé','canceled'=>'annulé','pending'=>'en attente'];

/* La saison courante: si l'on est avant septembre, on est encore dans la saison
   ouverte l'automne précédent. */
$auj      = new DateTimeImmutable('today');
$saisonAuj = (int)$auj->format('n') >= MOIS_SAISON
           ? (int)$auj->format('Y') : (int)$auj->format('Y') - 1;

$saison = isset($_GET['s']) && ctype_digit((string)$_GET['s']) ? (int)$_GET['s'] : $saisonAuj;
$statut = trim((string)($_GET['st'] ?? ''));

$debut = sprintf('%04d-%02d-01', $saison, MOIS_SAISON);
$fin   = sprintf('%04d-%02d-01', $saison + 1, MOIS_SAISON);

$where = ['supprime_le IS NULL', 'date_debut >= ?', 'date_debut < ?'];
$args  = [$debut, $fin];
if (isset($ETIQ[$statut])) { $where[] = 'statut = ?'; $args[] = $statut; }

$t0 = microtime(true);
$st = DB::pdo()->prepare('SELECT * FROM booking WHERE ' . implode(' AND ', $where)
                       . ' ORDER BY date_debut, heure, id');
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

/* Les saisons qui portent quelque chose, pour ne proposer que celles-là. */
$saisons = DB::pdo()->query(
    'SELECT DISTINCT YEAR(date_debut) - (MONTH(date_debut) < ' . MOIS_SAISON . ') AS s,
            COUNT(*) n
       FROM booking WHERE supprime_le IS NULL AND date_debut IS NOT NULL
      GROUP BY s ORDER BY s DESC')->fetchAll();

/* Le compte par statut sur la saison affichée: c'est le coup d'œil qu'Anna
   décrit, et il doit porter sur ce qu'on regarde et non sur toute la base. */
$st2 = DB::pdo()->prepare('SELECT statut, COUNT(*) n FROM booking
                            WHERE supprime_le IS NULL AND date_debut >= ? AND date_debut < ?
                            GROUP BY statut');
$st2->execute([$debut, $fin]);
$compte = $st2->fetchAll(PDO::FETCH_KEY_PAIR);

// Regroupement par mois, dans l'ordre de la saison.
$parMois = [];
foreach ($lignes as $r) {
    $parMois[substr((string)$r['date_debut'], 0, 7)][] = $r;
}

$total = count($lignes);
$horsCH = count(array_filter($lignes, fn($r) => $r['pays'] && !in_array($r['pays'], ['CH', 'Suisse'], true)));

/* ── DEUX AGENDAS, ET NON UN ────────────────────────────────────────────────
   [16.08.2026] Anna: « separar agenda projets et agenda rappels (é o to do do
   voisin) ». Le Calendrier montre les DATES — ce qui se joue, où, quand.
   L'agenda des rappels montre ce qu'il faut FAIRE. On ouvre l'un pour savoir
   où l'on sera en mars, l'autre pour savoir quoi faire ce matin; les mêler
   donnait une liste où l'on cherchait l'un en lisant l'autre. */
$rEcrit = dash_droit('calendrier', dash_role()) === 'ecrit';

if (($_GET['v'] ?? '') === 'rappels') {

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['rp'] ?? '') !== '') {
        Auth::requireCsrf();
        dash_exige_ecriture('calendrier');
        $qui = (string)(Auth::user()['name'] ?? '');
        $act = (string)$_POST['rp'];
        $j   = (int)($_GET['j'] ?? 30);

        if ($act === 'creer') {
            /* Le contact se choisit dans une liste qui finit par « #12 ». On ne
               lit que ce numéro: le texte devant peut être n'importe quoi, y
               compris une saisie libre qui ne correspond à personne. */
            $cid = 0;
            if (preg_match('/#(\d+)\s*$/', (string)($_POST['contact_q'] ?? ''), $mm)) $cid = (int)$mm[1];
            $id = Rappels::creer([
                'quand'    => (string)($_POST['quand'] ?? ''),
                'texte'    => (string)($_POST['texte'] ?? ''),
                'note'     => (string)($_POST['note'] ?? ''),
                'pour_qui' => (string)($_POST['pour_qui'] ?? ''),
                'contact_id' => $cid,
            ], $qui);
            dash_flash($id ? 'Rappel ajouté.' : 'Il faut au moins une date et un texte.', $id ? '' : 'err');
        } elseif ($act === 'fait') {
            Rappels::fait((int)($_POST['id'] ?? 0), $qui);
            dash_flash('Fait.');
        } elseif ($act === 'reporter') {
            Rappels::reporter((int)($_POST['id'] ?? 0), (int)($_POST['jours'] ?? 7));
            dash_flash('Repoussé.');
        }
        redirect('/dashboard.php?e=calendrier&v=rappels&j=' . $j);
    }

    $enRetard = Rappels::enRetard();
    dash_haut('calendrier', '<a href="/dashboard.php?e=calendrier" class="ret">les dates</a> · <strong>les rappels</strong>'
        . ($enRetard ? ' · <strong>' . $enRetard . '</strong> en retard' : ''));
    dash_flash_html();
    require __DIR__ . '/_rappels.php';
    dash_bas();
    return;
}

dash_haut('calendrier',
    $total . ' date' . ($total > 1 ? 's' : '') . ' · saison ' . $saison . '-' . ($saison + 1) . ' · ' . $ms . ' ms');
?>

<?php /* Le chemin vers l'autre agenda. Le compte des retards est dessus: sans
     lui, « les rappels » ne donne aucune raison de cliquer aujourd'hui. */ ?>
<?php $enRet = Rappels::enRetard(); ?>
<p class="ag-bascule"><strong>Les dates</strong>
  <a href="/dashboard.php?e=calendrier&amp;v=rappels">Les rappels<?php
    if ($enRet): ?> <span class="cpt"><?= $enRet ?></span><?php endif; ?></a></p>
<style>
.ag-bascule{display:flex;gap:16px;align-items:center;margin:0 0 12px;font-size:13.5px}
.ag-bascule a{color:var(--doux);text-decoration:none}
.ag-bascule a:hover{color:var(--encre)}
.ag-bascule .cpt{padding:1px 8px;border-radius:9px;background:#c8452f;color:#fff;
  font-size:11.5px;font-weight:600}
</style>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="calendrier">
  <select name="s" onchange="this.form.submit()">
    <?php foreach ($saisons as $x): ?>
      <option value="<?= $x['s'] ?>"<?= $saison === (int)$x['s'] ? ' selected' : '' ?>>
        Saison <?= $x['s'] ?>-<?= $x['s'] + 1 ?> (<?= $x['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="st" onchange="this.form.submit()">
    <option value="">Tous les statuts</option>
    <?php foreach ($ETIQ as $k => $v): ?>
      <option value="<?= $k ?>"<?= $statut === $k ? ' selected' : '' ?>><?=
        e($v) ?> (<?= $compte[$k] ?? 0 ?>)</option>
    <?php endforeach; ?>
  </select>
  <span class="resume">
    <?php foreach ($ETIQ as $k => $v): if (empty($compte[$k])) continue; ?>
      <span class="et <?= $k ?>"><?= $compte[$k] ?> <?= e($v) ?></span>
    <?php endforeach; ?>
    <?php if ($horsCH): ?><span class="et hors"><?= $horsCH ?> hors Suisse</span><?php endif; ?>
  </span>
  <a class="neuf" href="/dashboard.php?e=bookings&amp;mod=1">+ nouvelle date</a>
</form>

<?php if (!$parMois): ?>
  <p class="vide">Aucune date sur cette saison.</p>
<?php else: ?>
<div class="agenda">
<?php foreach ($parMois as $ym => $rows):
    [$an, $mo] = array_map('intval', explode('-', $ym)); ?>
  <section class="mois">
    <h2><?= e($MOIS[$mo]) ?> <span class="an"><?= $an ?></span>
        <span class="n"><?= count($rows) ?></span></h2>
    <?php foreach ($rows as $r):
        $d = new DateTimeImmutable((string)$r['date_debut']);
        $passe = $d < $auj; ?>
      <a class="date <?= $passe ? 'passe' : '' ?>"
         href="/dashboard.php?e=bookings&amp;b=<?= (int)$r['id'] ?>">
        <span class="jour"><?= $d->format('d') ?><em><?= ['dim','lun','mar','mer','jeu','ven','sam'][(int)$d->format('w')] ?></em></span>
        <span class="corps">
          <strong><?= e($r['projet'] ?: '(sans projet)') ?></strong>
          <?php if ($r['artiste']): ?><span class="sec"> · <?= e($r['artiste']) ?></span><?php endif; ?>
          <span class="lieu"><?= e($r['venue'] ?? '') ?><?php
            if ($r['ville']): ?>, <?= e($r['ville']) ?><?php endif; ?><?php
            if ($r['pays'] && !in_array($r['pays'], ['CH','Suisse'], true)): ?>
            <span class="pays"><?= e($r['pays']) ?></span><?php endif; ?></span>
        </span>
        <span class="fin">
          <?php if ($r['representations'] > 1): ?>
            <span class="rep"><?= (int)$r['representations'] ?> repr.</span>
          <?php endif; ?>
          <?php if ($r['prix_cession'] !== null): ?>
            <span class="prix"><?= number_format((float)$r['prix_cession'], 0, ',', ' ') ?> <?= e($r['devise']) ?></span>
          <?php endif; ?>
          <span class="et <?= e($r['statut']) ?>"><?= e($ETIQ[$r['statut']] ?? $r['statut']) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.resume { display:flex; gap:6px; flex-wrap:wrap; }
.neuf { margin-left:auto; padding:8px 16px; background:var(--jaune); color:#0d0d0d;
        border-radius:4px; text-decoration:none; font-size:13.5px; font-weight:600; }
.agenda { padding:8px 26px 40px; max-width:1100px; }
.mois { margin-top:26px; }
.mois h2 { font-size:14px; margin:0 0 6px; text-transform:uppercase; letter-spacing:.06em;
           border-bottom:2px solid var(--encre); padding-bottom:5px; }
.mois h2 .an { color:var(--doux); font-weight:400; }
.mois h2 .n { float:right; color:var(--doux); font-weight:400; }
a.date { display:flex; gap:14px; align-items:center; padding:9px 4px;
         border-bottom:1px solid var(--trait); text-decoration:none; }
a.date:hover { background:var(--fond2); }
a.date.passe { opacity:.5; }
.jour { width:40px; flex:0 0 40px; text-align:center; font-size:17px; font-weight:600;
        line-height:1.1; }
.jour em { display:block; font-size:10px; font-style:normal; color:var(--doux);
        text-transform:uppercase; letter-spacing:.04em; }
.corps { flex:1; min-width:0; font-size:14px; }
.corps .lieu { display:block; color:var(--doux); font-size:12.5px; }
.corps .pays { border:1px solid var(--trait); border-radius:3px; padding:0 4px;
        font-size:10.5px; margin-left:4px; }
.fin { display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
.fin .prix { font-size:13px; white-space:nowrap; }
.fin .rep { font-size:11.5px; color:var(--doux); white-space:nowrap; }
.et { font-size:11px; padding:2px 8px; border-radius:10px; border:1px solid var(--trait);
      white-space:nowrap; }
.et.confirmed { background:#e7f6ea; border-color:#bfe3c8; color:#1c5c2e; }
.et.option    { background:#fff6d9; border-color:#f0dfa3; color:#6b5312; }
.et.pending   { background:var(--fond2); }
.et.canceled  { background:#fbe9e7; border-color:#f0c3bb; color:#7a2b1e; }
.et.hors      { background:var(--fond2); }
@media (max-width:640px) {
  .fin { width:100%; justify-content:flex-start; padding-left:54px; }
  a.date { flex-wrap:wrap; }
}
</style>

<?php dash_bas(); ?>
