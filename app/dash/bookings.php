<?php
/**
 * Écran Bookings. [16.08.2026]
 *
 * Une date jouée, ou en cours de l'être. C'est l'objet central du dashboard, et
 * il n'existait nulle part comme donnée: `events` du CMS portait une chaîne
 * d'affichage sans cachet ni statut, `lv-tour` portait 35 lignes codées en dur
 * et EN LECTURE SEULE, sans aucun formulaire pour en créer une.
 *
 * DEUX VUES DANS UN SEUL FICHIER, choisies par ?b=<id>: la liste, et la fiche
 * avec ses cinq onglets. Elles partagent trop pour vivre séparées, et le
 * fichier reste lisible tant qu'il n'y a que ces deux-là.
 *
 * LES ONGLETS DE LA FICHE sont déclarés et vides pour l'instant. C'est le même
 * parti que le menu: montrer la carte plutôt que de la cacher. Chacun dit ce
 * qu'il portera et ce qui lui manque encore comme table.
 */
declare(strict_types=1);

const PAR_PAGE = 60;

/** Les onglets de la fiche, dans l'ordre demandé par Anna. */
const ONGLETS = [
    'apercu'    => 'Aperçu',
    'deal'      => 'Deal',
    'factures'  => 'Factures',
    'contrats'  => 'Contrats',
    'advancing' => 'Advancing',
    'voyage'    => 'Voyage',
];

$id = (int)($_GET['b'] ?? 0);

// ═══════════════════════════════════════════════════════════════════════════
// LA FICHE
// ═══════════════════════════════════════════════════════════════════════════

if ($id > 0) {
    $b = DB::one('SELECT * FROM booking WHERE id = ? AND supprime_le IS NULL', [$id]);
    if (!$b) { dash_haut('bookings'); echo '<p class="vide">Ce booking n\'existe pas.</p>'; dash_bas(); return; }

    $ong = (string)($_GET['o'] ?? 'apercu');
    if (!isset(ONGLETS[$ong])) $ong = 'apercu';

    $titre = trim(($b['projet'] ?? '') . ' · ' . ($b['venue'] ?? ''));
    dash_haut('bookings', e($b['date_texte'] ?: (string)$b['date_debut']) . ' · ' . e($b['ville'] ?? ''));
    ?>
    <div class="fil"><a href="/dashboard.php?e=bookings">← tous les bookings</a></div>

    <div class="onglets">
      <?php foreach (ONGLETS as $c => $lib): ?>
        <a href="/dashboard.php?e=bookings&amp;b=<?= $id ?>&amp;o=<?= $c ?>"
           class="<?= $c === $ong ? 'ici' : '' ?>"><?= e($lib) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="zone">
    <?php if ($ong === 'apercu'): ?>
      <h2 class="gros"><?= e($titre) ?></h2>
      <div class="fiche">
        <?php
        $st = ['option' => 'option', 'confirmed' => 'confirmé', 'canceled' => 'annulé', 'pending' => 'en attente'];
        $l = function (string $k, $v, string $note = '') {
            if ($v === null || $v === '') return;
            printf('<div class="l"><span class="k">%s</span><span class="v">%s%s</span></div>',
                   e($k), e((string)$v), $note ? '<span class="n">' . e($note) . '</span>' : '');
        };
        $l('Statut', $st[$b['statut']] ?? $b['statut']);
        $l('Date', $b['date_texte'] ?: $b['date_debut']);
        if ($b['heure']) $l('Heure', substr((string)$b['heure'], 0, 5));
        $l('Représentations', $b['representations']);
        $l('Projet', $b['projet']);
        $l('Artiste', $b['artiste']);
        $l('Lieu', $b['venue']);
        $l('Ville', trim(($b['ville'] ?? '') . ' ' . ($b['pays'] ? '· ' . $b['pays'] : '')));
        $l('Client', $b['client']);
        if ($b['prix_cession'] !== null)
            $l('Prix de cession', number_format((float)$b['prix_cession'], 2, ',', ' ') . ' ' . $b['devise']);
        if ($b['prix_vente'] !== null)
            $l('Prix de vente', number_format((float)$b['prix_vente'], 2, ',', ' ') . ' ' . $b['devise']);
        $l('Provenance', $b['source'] . ' · ' . $b['source_ref'],
           $b['source'] === 'events' ? 'agenda du site' : 'lv-tour du dashboard');
        ?>
      </div>

      <?php /* Les deux natures de notes, et la distinction est le point:
               l'une part avec l'artiste, l'autre jamais. Une seule colonne
               obligerait à se relire avant chaque partage. */ ?>
      <div class="notes">
        <div class="bloc">
          <h3>Notes artiste <span class="n">visibles par l'artiste</span></h3>
          <p><?= $b['notes_artiste'] ? nl2br(e($b['notes_artiste'])) : '<span class="n">rien</span>' ?></p>
        </div>
        <div class="bloc int">
          <h3>Notes internes <span class="n">l'équipe seulement</span></h3>
          <p><?= $b['notes_internes'] ? nl2br(e($b['notes_internes'])) : '<span class="n">rien</span>' ?></p>
        </div>
      </div>

    <?php else: ?>
      <?php
      /* Chaque onglet dit ce qu'il portera ET ce qui lui manque comme table.
         Un « bientôt » ne apprend rien; ceci apprend où en est la reprise. */
      $quoi = [
        'deal'      => ['Cachets, termes du deal et extras.',
                        'Demande une table `deal_item`, avec un type par ligne: cachet, frais de booking, voyage, hébergement, droits. Les modèles par artiste vivront dans l\'écran Associations et artistes.'],
        'factures'  => ['Générer et télécharger les factures de ce booking.',
                        'Demande la table `invoice` et la liaison bexio par API. Le client bexio actuel vit dans Apps Script: le porter en PHP est chiffré entre 12 h et 20 h pour le seul OAuth2.'],
        'contrats'  => ['Contrats, avec signature en ligne.',
                        'Le site sait déjà signer: `app/lib/Skribble.php` fonctionne et l\'espace collaborateur s\'en sert. Il manque la table `contract` et le lien vers ce booking.'],
        'advancing' => ['Fiches techniques, accueil et logistique du show.',
                        'C\'est la mécanique la plus intéressante d\'artistu: un formulaire construit champ par champ, envoyé au lieu, avec un état par champ (demandé, reçu, accepté) et un portail où le lieu répond. Rien d\'équivalent n\'existe ici.'],
        'voyage'    => ['Vols, transferts, hôtels.',
                        'Demande une table `logistique` rattachée au booking. Aujourd\'hui ces informations sont des catégories de documents dans l\'espace collaborateur: des fichiers, pas des données.'],
      ][$ong];
      ?>
      <div class="avis">
        <h2><?= e(ONGLETS[$ong]) ?></h2>
        <p><?= e($quoi[0]) ?></p>
        <p><?= e($quoi[1]) ?></p>
      </div>
    <?php endif; ?>
    </div>

    <style>
    .fil { padding:12px 26px 0; font-size:13px; }
    .fil a { color:var(--doux); text-decoration:none; }
    .onglets { display:flex; gap:2px; padding:12px 26px 0; border-bottom:1px solid var(--trait);
               overflow-x:auto; }
    .onglets a { padding:8px 15px; font-size:13.5px; text-decoration:none; white-space:nowrap;
               border-bottom:3px solid transparent; color:var(--doux); }
    .onglets a.ici { color:var(--encre); border-bottom-color:var(--jaune); font-weight:600; }
    h2.gros { font-size:20px; margin:0 0 18px; }
    .fiche { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
             gap:0 32px; max-width:900px; }
    .fiche .l { display:flex; gap:12px; padding:7px 0; border-bottom:1px solid var(--trait); }
    .fiche .k { color:var(--doux); font-size:12.5px; min-width:120px; }
    .fiche .v { font-size:14px; }
    .fiche .n, .notes .n { color:var(--doux); font-size:12px; font-weight:400; margin-left:8px; }
    .notes { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
             gap:18px; margin-top:26px; max-width:900px; }
    .notes .bloc { padding:14px 18px; background:var(--fond2); border-left:4px solid var(--jaune); }
    .notes .bloc.int { border-left-color:var(--orange); }
    .notes h3 { font-size:13.5px; margin:0 0 8px; }
    .notes p { margin:0; font-size:14px; }
    </style>
    <?php
    dash_bas();
    return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LA LISTE
// ═══════════════════════════════════════════════════════════════════════════

$q      = trim((string)($_GET['q'] ?? ''));
$statut = trim((string)($_GET['s'] ?? ''));
$annee  = trim((string)($_GET['a'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));

$where = ['supprime_le IS NULL'];
$args  = [];
if ($statut !== '' && isset(['option'=>1,'confirmed'=>1,'canceled'=>1,'pending'=>1][$statut])) {
    $where[] = 'statut = ?'; $args[] = $statut;
}
if ($annee !== '' && ctype_digit($annee)) { $where[] = 'YEAR(date_debut) = ?'; $args[] = (int)$annee; }
if ($q !== '') {
    /* Peu de lignes ici, quatre-vingt-six aujourd'hui et quelques centaines à
       terme: un LIKE suffit et évite d'ajouter un index FULLTEXT qu'il faudrait
       entretenir pour rien. */
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
    $where[] = '(venue LIKE ? OR projet LIKE ? OR artiste LIKE ? OR ville LIKE ? OR client LIKE ?)';
    array_push($args, $like, $like, $like, $like, $like);
}
$sqlWhere = implode(' AND ', $where);

$t0 = microtime(true);
$st = DB::pdo()->prepare("SELECT COUNT(*) FROM booking WHERE $sqlWhere");
$st->execute($args);
$total  = (int)$st->fetchColumn();
$pages  = max(1, (int)ceil($total / PAR_PAGE));
$page   = min($page, $pages);

$st = DB::pdo()->prepare("SELECT * FROM booking WHERE $sqlWhere
                          ORDER BY date_debut DESC, id DESC
                          LIMIT " . PAR_PAGE . " OFFSET " . (($page - 1) * PAR_PAGE));
$st->execute($args);
$lignes = $st->fetchAll();
$ms = (int)round((microtime(true) - $t0) * 1000);

$annees = DB::pdo()->query("SELECT YEAR(date_debut) a, COUNT(*) n FROM booking
                             WHERE supprime_le IS NULL AND date_debut IS NOT NULL
                             GROUP BY a ORDER BY a DESC")->fetchAll();
$parStatut = DB::pdo()->query("SELECT statut, COUNT(*) n FROM booking
                                WHERE supprime_le IS NULL GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);

/* Les dates présentes dans les DEUX sources. C'est la double saisie qu'Anna
   décrit: aujourd'hui la même date s'écrit à la main dans le CMS et dans le
   dashboard. On la compte et on la montre, sans fusionner: choisir laquelle
   gagne demande de lire les deux. */
$doublons = (int)DB::pdo()->query(
    "SELECT COUNT(*) FROM (SELECT date_debut, ville FROM booking
       WHERE supprime_le IS NULL AND date_debut IS NOT NULL
       GROUP BY date_debut, ville HAVING COUNT(DISTINCT source) > 1) x")->fetchColumn();

$lien = function (array $chg) use ($q, $statut, $annee, $page): string {
    $p = array_merge(['e'=>'bookings','q'=>$q,'s'=>$statut,'a'=>$annee,'page'=>$page], $chg);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 1);
    return '/dashboard.php?' . http_build_query($p);
};

$ETIQ = ['option'=>'option','confirmed'=>'confirmé','canceled'=>'annulé','pending'=>'en attente'];

dash_haut('bookings', number_format($total,0,',',' ') . ' booking' . ($total>1?'s':'') . ' · ' . $ms . ' ms');
?>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="bookings">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Lieu, projet, artiste, ville, client">
  <select name="s">
    <option value="">Tous les statuts</option>
    <?php foreach ($ETIQ as $k => $v): ?>
      <option value="<?= $k ?>"<?= $statut === $k ? ' selected' : '' ?>><?= e($v) ?> (<?= $parStatut[$k] ?? 0 ?>)</option>
    <?php endforeach; ?>
  </select>
  <select name="a">
    <option value="">Toutes les années</option>
    <?php foreach ($annees as $x): ?>
      <option value="<?= $x['a'] ?>"<?= $annee === (string)$x['a'] ? ' selected' : '' ?>><?= $x['a'] ?> (<?= $x['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Chercher</button>
  <?php if ($q !== '' || $statut !== '' || $annee !== ''): ?>
    <a class="vider" href="/dashboard.php?e=bookings">tout effacer</a>
  <?php endif; ?>
</form>

<?php if ($doublons > 0): ?>
<div class="alerte">
  <strong><?= $doublons ?> dates existent dans les deux sources</strong>, l'agenda du site et
  lv-tour du dashboard. C'est la double saisie: la même date écrite à la main des deux côtés.
  Elles sont laissées telles quelles, parce que choisir laquelle gagne demande de les lire.
</div>
<?php endif; ?>

<?php if (!$lignes): ?>
  <p class="vide">Aucun booking ne correspond.</p>
<?php else: ?>
<div class="tw">
<table>
  <thead><tr>
    <th>Date</th><th>Projet</th><th>Artiste</th><th>Lieu</th><th>Ville</th>
    <th>Statut</th><th class="d">Cession</th><th class="d">Vente</th><th>Client</th>
  </tr></thead>
  <tbody>
  <?php foreach ($lignes as $r): ?>
    <tr>
      <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$r['id'] ?>"><?=
        e($r['date_texte'] ?: (string)$r['date_debut']) ?></a>
        <?php if ($r['heure']): ?><div class="sec"><?= substr((string)$r['heure'],0,5) ?></div><?php endif; ?></td>
      <td><?= e($r['projet'] ?? '') ?></td>
      <td class="sec"><?= e($r['artiste'] ?? '') ?></td>
      <td><?= e($r['venue'] ?? '') ?></td>
      <td><?= e($r['ville'] ?? '') ?><?php if ($r['pays']): ?>
        <div class="sec"><?= e($r['pays']) ?></div><?php endif; ?></td>
      <td><span class="et <?= e($r['statut']) ?>"><?= e($ETIQ[$r['statut']] ?? $r['statut']) ?></span></td>
      <td class="d"><?= $r['prix_cession'] !== null
            ? number_format((float)$r['prix_cession'],0,',',' ') . ' ' . e($r['devise']) : '' ?></td>
      <td class="d"><?= $r['prix_vente'] !== null
            ? number_format((float)$r['prix_vente'],0,',',' ') . ' ' . e($r['devise']) : '' ?></td>
      <td class="sec"><?= e($r['client'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="pages">
  <?php if ($page > 1): ?><a href="<?= e($lien(['page'=>$page-1])) ?>">précédent</a><?php endif; ?>
  <span class="mut">page <?= $page ?> sur <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e($lien(['page'=>$page+1])) ?>">suivant</a><?php endif; ?>
</nav>
<?php endif; ?>

<style>
td.d, th.d { text-align:right; white-space:nowrap; }
.et { font-size:11.5px; padding:2px 8px; border-radius:10px; border:1px solid var(--trait);
      white-space:nowrap; }
.et.confirmed { background:#e7f6ea; border-color:#bfe3c8; color:#1c5c2e; }
.et.option    { background:#fff6d9; border-color:#f0dfa3; color:#6b5312; }
.et.pending   { background:var(--fond2); }
.et.canceled  { background:#fbe9e7; border-color:#f0c3bb; color:#7a2b1e; }
@media (prefers-color-scheme: dark) { :root:not([data-theme=light]) .et.confirmed,
  :root:not([data-theme=light]) .et.option, :root:not([data-theme=light]) .et.canceled {
  background:transparent; color:inherit; } }
.alerte { margin:16px 26px 0; padding:12px 16px; background:var(--fond2);
          border-left:4px solid var(--orange); font-size:13.5px; max-width:80ch; }
</style>

<?php dash_bas(); ?>
