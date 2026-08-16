<?php
/**
 * Écran Tableau de bord. [16.08.2026]
 *
 * CE QU'IL RÉPOND: qu'est-ce qui demande une décision aujourd'hui.
 *
 * Pas « voici de jolis chiffres ». Un tableau de bord qui affiche des totaux
 * qu'on ne peut rien faire de se regarde une semaine puis s'ignore. Celui-ci ne
 * montre que ce sur quoi on peut agir, avec le lien qui y mène, et il dit
 * combien de choses il n'a pas pu vérifier.
 *
 * L'ORDRE EST CELUI DU COÛT D'UN OUBLI, pas celui des modules:
 *   1. ce qui est en retard et coûte de l'argent ou une amende
 *   2. ce qui arrive et demande un geste
 *   3. ce qui manque dans les données et fausse tout le reste
 *
 * IRIS EST EN BAS et non en haut. Aujourd'hui, dans le dashboard Apps Script,
 * elle assemble un rôle, un extrait de données et une question, copie le tout
 * dans le presse-papier et ouvre claude.ai: aucun appel d'API. Le même
 * mécanisme est repris ici, en disant ce qu'il est. Le jour où l'on branche une
 * API, seule cette section change.
 */
declare(strict_types=1);

const A1_DELAI = 28;
const MOIS_SAISON = 9;

$auj  = new DateTimeImmutable('today');
$mois = date('Y-m');
$saison = (int)$auj->format('n') >= MOIS_SAISON
        ? (int)$auj->format('Y') : (int)$auj->format('Y') - 1;
$debut = sprintf('%04d-%02d-01', $saison, MOIS_SAISON);
$fin   = sprintf('%04d-%02d-01', $saison + 1, MOIS_SAISON);

// ── 1. Ce qui presse ────────────────────────────────────────────────────────

/* Les A1 en retard: une date hors de Suisse à moins de vingt-huit jours dont
   personne n'a l'attestation. Le délai est légal, pas indicatif. */
$a1Retard = DB::all(
    "SELECT b.id, b.date_debut, b.date_texte, b.venue, b.ville, b.pays,
            DATEDIFF(b.date_debut, CURDATE()) jours,
            (SELECT COUNT(*) FROM a1_demande a
              WHERE a.booking_id = b.id AND a.etat IN ('recu','sans_objet')) ok
       FROM booking b
      WHERE b.supprime_le IS NULL AND b.date_debut >= CURDATE()
        AND DATEDIFF(b.date_debut, CURDATE()) <= " . A1_DELAI . "
        AND b.pays IS NOT NULL AND b.pays NOT IN ('CH','Suisse','SUISSE')
        AND b.statut IN ('confirmed','option')
      HAVING ok = 0 ORDER BY b.date_debut");

/* Les obligations administratives dépassées ce mois. */
$adminTard = DB::all(
    "SELECT t.id, m.libelle, t.territoire, o.nom org, t.echeance
       FROM admin_tache t
       LEFT JOIN admin_modele m ON m.id = t.modele_id
       LEFT JOIN organisation o ON o.id = t.organisation_id
      WHERE t.etat = 'a_faire' AND t.echeance IS NOT NULL AND t.echeance < CURDATE()
      ORDER BY t.echeance LIMIT 12");
$adminTardN = (int)DB::pdo()->query(
    "SELECT COUNT(*) FROM admin_tache
      WHERE etat = 'a_faire' AND echeance IS NOT NULL AND echeance < CURDATE()")->fetchColumn();

/* L'argent qui n'est pas rentré alors que la date est passée. */
$impayes = DB::all(
    "SELECT id, date_debut, date_texte, venue, ville, projet, prix_cession, devise
       FROM booking
      WHERE supprime_le IS NULL AND date_debut < CURDATE() AND statut = 'confirmed'
        AND encaissement = 'attendu' AND prix_cession IS NOT NULL
      ORDER BY date_debut LIMIT 10");
$impayesTot = DB::one(
    "SELECT COUNT(*) n, COALESCE(SUM(prix_cession),0) t FROM booking
      WHERE supprime_le IS NULL AND date_debut < CURDATE() AND statut = 'confirmed'
        AND encaissement = 'attendu' AND prix_cession IS NOT NULL");

// ── 2. Ce qui arrive ────────────────────────────────────────────────────────

$prochaines = DB::all(
    "SELECT id, date_debut, date_texte, projet, artiste, venue, ville, pays, statut,
            DATEDIFF(date_debut, CURDATE()) jours
       FROM booking
      WHERE supprime_le IS NULL AND date_debut >= CURDATE() AND statut <> 'canceled'
      ORDER BY date_debut LIMIT 8");

$moisAdmin = DB::one(
    "SELECT COUNT(*) n, SUM(etat = 'fait') f FROM admin_tache WHERE periode = ?", [$mois]);

// ── 3. Ce qui manque dans les données ───────────────────────────────────────

$trous = [
  ['Dates sans prix de cession',
   (int)DB::pdo()->query("SELECT COUNT(*) FROM booking WHERE supprime_le IS NULL
      AND prix_cession IS NULL AND date_debut >= '$debut' AND date_debut < '$fin'")->fetchColumn(),
   '/dashboard.php?e=bookings', 'Elles comptent dans le calendrier et dans aucun total'],
  ['Dates sans aucune ligne de deal',
   (int)DB::pdo()->query("SELECT COUNT(*) FROM booking b WHERE b.supprime_le IS NULL
      AND b.date_debut >= '$debut' AND b.date_debut < '$fin'
      AND NOT EXISTS (SELECT 1 FROM deal_item d WHERE d.booking_id = b.id)")->fetchColumn(),
   '/dashboard.php?e=finances&v=releve', 'Leur prix est un nombre sans composition'],
  ['Projets sans couche production',
   (int)DB::pdo()->query("SELECT COUNT(*) FROM projects pr
      LEFT JOIN projet_prod pp ON pp.project_id = pr.id WHERE pp.project_id IS NULL")->fetchColumn(),
   '/dashboard.php?e=projets', 'Personne n\'a dit qui les porte ni à quelle phase'],
  ['Dates présentes dans les deux sources',
   (int)DB::pdo()->query("SELECT COUNT(*) FROM (SELECT date_debut, ville FROM booking
      WHERE supprime_le IS NULL AND date_debut IS NOT NULL
      GROUP BY date_debut, ville HAVING COUNT(DISTINCT source) > 1) x")->fetchColumn(),
   '/dashboard.php?e=bookings', 'La même date saisie à la main des deux côtés'],
  ['Noms qui sont association ET artiste',
   (int)DB::pdo()->query("SELECT COUNT(*) FROM (SELECT nom FROM organisation
      WHERE supprime_le IS NULL GROUP BY nom HAVING COUNT(DISTINCT genre) > 1) x")->fetchColumn(),
   '/dashboard.php?e=associations', 'Ce n\'est pas un doublon, c\'est à trancher'],
];

$urgent = count($a1Retard) + $adminTardN + (int)$impayesTot['n'];
$fmt = fn($v) => number_format((float)$v, 0, ',', ' ');

dash_haut('accueil', $urgent
    ? $urgent . ' chose' . ($urgent > 1 ? 's' : '') . ' demandent une décision'
    : 'rien d\'urgent');
?>
<div class="zone">

<?php if (!$urgent): ?>
  <div class="calme">Rien en retard, rien d'impayé, aucune attestation A1 hors délai.</div>
<?php endif; ?>

<?php if ($a1Retard): ?>
<section class="bloc rouge">
  <h2>Attestations A1 hors délai <span class="n"><?= count($a1Retard) ?></span></h2>
  <p class="ex">La demande prend quatre semaines. Détacher quelqu'un dans l'Union sans A1
     expose à un contrôle sur place et à une amende.</p>
  <?php foreach ($a1Retard as $d): ?>
    <a class="lg" href="/dashboard.php?e=administration&amp;t=a1">
      <span class="q"><?= (int)$d['jours'] ?> j</span>
      <span><?= e($d['date_texte'] ?: (string)$d['date_debut']) ?>
        <span class="sec"><?= e($d['venue'] ?? '') ?>, <?= e($d['ville'] ?? '') ?>
          · <?= e($d['pays']) ?></span></span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($adminTardN): ?>
<section class="bloc rouge">
  <h2>Obligations administratives dépassées <span class="n"><?= $adminTardN ?></span></h2>
  <?php foreach ($adminTard as $t): ?>
    <a class="lg" href="/dashboard.php?e=administration&amp;m=<?= e(substr((string)$t['echeance'],0,7)) ?>">
      <span class="q"><?= e(substr((string)$t['echeance'], 8, 2)) ?>.<?= e(substr((string)$t['echeance'], 5, 2)) ?></span>
      <span><?php if ($t['territoire']): ?><span class="tg"><?= e($t['territoire']) ?></span> <?php endif; ?>
        <?= e($t['libelle'] ?? '') ?> <span class="sec"><?= e($t['org'] ?? '') ?></span></span>
    </a>
  <?php endforeach; ?>
  <?php if ($adminTardN > count($adminTard)): ?>
    <p class="ex">et <?= $adminTardN - count($adminTard) ?> autres.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ((int)$impayesTot['n']): ?>
<section class="bloc orange">
  <h2>Dates jouées et non encaissées
    <span class="n"><?= (int)$impayesTot['n'] ?> · <?= $fmt($impayesTot['t']) ?></span></h2>
  <?php foreach ($impayes as $d): ?>
    <a class="lg" href="/dashboard.php?e=finances&amp;v=releve">
      <span class="q"><?= $d['prix_cession'] !== null ? $fmt($d['prix_cession']) : '' ?></span>
      <span><?= e($d['date_texte'] ?: (string)$d['date_debut']) ?>
        <span class="sec"><?= e($d['venue'] ?? '') ?> · <?= e($d['projet'] ?? '') ?></span></span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="deux">
  <section class="bloc">
    <h2>Les prochaines dates</h2>
    <?php if (!$prochaines): ?><p class="ex">Aucune date à venir.</p><?php else: ?>
      <?php foreach ($prochaines as $d): ?>
        <a class="lg" href="/dashboard.php?e=bookings&amp;b=<?= (int)$d['id'] ?>">
          <span class="q"><?= (int)$d['jours'] ?> j</span>
          <span><?= e($d['projet'] ?: '(sans projet)') ?>
            <span class="sec"><?= e($d['venue'] ?? '') ?><?php if ($d['ville']): ?>,
              <?= e($d['ville']) ?><?php endif; ?></span></span>
          <span class="et <?= e($d['statut']) ?>"><?= e($d['statut']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="bloc">
    <h2>Le mois administratif <span class="n"><?= e($mois) ?></span></h2>
    <?php if (!(int)$moisAdmin['n']): ?>
      <p class="ex">Pas encore généré.</p>
      <a class="bt" href="/dashboard.php?e=administration">Ouvrir l'administration</a>
    <?php else: ?>
      <?php $pc = (int)$moisAdmin['n'] ? round(100 * (int)$moisAdmin['f'] / (int)$moisAdmin['n']) : 0; ?>
      <div class="jauge"><div style="width:<?= $pc ?>%"></div></div>
      <p class="ex"><?= (int)$moisAdmin['f'] ?> faites sur <?= (int)$moisAdmin['n'] ?>,
         soit <?= $pc ?> %.</p>
      <a class="bt" href="/dashboard.php?e=administration">Continuer</a>
    <?php endif; ?>
  </section>
</div>

<section class="bloc">
  <h2>Ce que les données ne disent pas encore</h2>
  <p class="ex">Un tableau de bord qui cache ses trous donne confiance à tort. Ceux-ci
     faussent les totaux du reste tant qu'ils ne sont pas comblés.</p>
  <?php foreach ($trous as [$lib, $n, $url, $pourquoi]): if (!$n) continue; ?>
    <a class="lg" href="<?= e($url) ?>">
      <span class="q"><?= $n ?></span>
      <span><?= e($lib) ?> <span class="sec"><?= e($pourquoi) ?></span></span>
    </a>
  <?php endforeach; ?>
  <?php if (!array_filter(array_column($trous, 1))): ?>
    <p class="ex">Aucun trou connu.</p><?php endif; ?>
</section>

<section class="bloc iris">
  <h2>IRIS</h2>
  <p class="ex">IRIS assemble un rôle, un extrait de vos données réelles et votre
     question, et vous rend le tout à coller dans une conversation. <strong>Elle
     n'appelle aucune API et n'envoie rien toute seule</strong>: c'est vous qui
     décidez ce qui sort d'ici. C'est déjà ainsi qu'elle fonctionne dans le
     dashboard actuel, et le dire évite de croire à une magie qui n'existe pas.</p>
  <?php
  /* Le contexte est monté ici et non côté navigateur: il vient de la base, il
     est donc exact au moment où on le copie. */
  $ctx = [];
  $ctx[] = "CONTEXTE LE VOISIN, au " . $auj->format('d.m.Y');
  $ctx[] = "";
  $ctx[] = "Saison $saison-" . ($saison + 1) . ": " . (int)DB::pdo()->query(
      "SELECT COUNT(*) FROM booking WHERE supprime_le IS NULL
        AND date_debut >= '$debut' AND date_debut < '$fin'")->fetchColumn() . " dates";
  $ctx[] = "Organisations: " . (int)DB::pdo()->query(
      "SELECT COUNT(*) FROM organisation WHERE supprime_le IS NULL")->fetchColumn()
      . ", contacts: " . (int)DB::pdo()->query(
      "SELECT COUNT(*) FROM contact WHERE supprime_le IS NULL")->fetchColumn();
  if ($a1Retard)   $ctx[] = "URGENT: " . count($a1Retard) . " attestations A1 hors délai";
  if ($adminTardN) $ctx[] = "URGENT: $adminTardN obligations administratives dépassées";
  if ((int)$impayesTot['n']) $ctx[] = "URGENT: " . (int)$impayesTot['n']
      . " dates jouées non encaissées, " . $fmt($impayesTot['t']);
  $ctx[] = "";
  $ctx[] = "Prochaines dates:";
  foreach (array_slice($prochaines, 0, 6) as $d) {
      $ctx[] = "  " . ($d['date_debut'] ?? '') . "  " . ($d['projet'] ?? '')
             . "  " . ($d['venue'] ?? '') . ", " . ($d['ville'] ?? '') . "  [" . $d['statut'] . "]";
  }
  ?>
  <textarea id="iris" rows="9" readonly><?= e(implode("\n", $ctx)) ?></textarea>
  <div class="irisbt">
    <button type="button" onclick="var t=document.getElementById('iris');t.select();
      document.execCommand('copy');this.textContent='copié';">Copier le contexte</button>
    <a class="bt" href="https://claude.ai" target="_blank" rel="noopener">Ouvrir Claude</a>
  </div>
</section>

</div>

<style>
.zone{padding:22px 26px 46px;max-width:1080px}
.calme{padding:14px 18px;background:var(--fond2);border-left:4px solid var(--jaune);
  font-size:14px;margin-bottom:22px}
.bloc{margin-bottom:26px;border:1px solid var(--trait);border-radius:6px;padding:16px 18px}
.bloc.rouge{border-left:4px solid var(--orange)}
.bloc.orange{border-left:4px solid var(--jaune)}
.bloc h2{font-size:14px;margin:0 0 8px;text-transform:uppercase;letter-spacing:.04em}
.bloc h2 .n{float:right;color:var(--doux);font-weight:400;text-transform:none;letter-spacing:0}
.ex{font-size:13px;color:var(--doux);margin:0 0 10px;max-width:76ch;line-height:1.5}
a.lg{display:flex;gap:12px;align-items:baseline;padding:7px 0;text-decoration:none;
  border-bottom:1px solid var(--trait);font-size:13.5px}
a.lg:last-child{border-bottom:0}
a.lg:hover{background:var(--fond2)}
a.lg .q{flex:0 0 66px;text-align:right;font-weight:600;font-size:13px}
a.lg .sec{color:var(--doux);font-size:12.5px;display:block}
a.lg .et{margin-left:auto;font-size:10.5px;padding:1px 7px;border-radius:9px;
  border:1px solid var(--trait);color:var(--doux)}
.tg{font-size:10px;border:1px solid var(--trait);border-radius:3px;padding:0 4px;color:var(--doux)}
.deux{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:0 22px}
.jauge{height:7px;background:var(--trait);border-radius:4px;overflow:hidden;margin:4px 0 8px}
.jauge div{height:100%;background:var(--jaune)}
a.bt,.irisbt button{display:inline-block;padding:7px 15px;font-size:13px;font-family:inherit;
  background:var(--encre);color:var(--papier);border:0;border-radius:4px;
  text-decoration:none;cursor:pointer}
.iris textarea{width:100%;font-family:ui-monospace,Menlo,monospace;font-size:12px;
  padding:10px;border:1px solid var(--trait);border-radius:4px;
  background:var(--fond2);color:var(--encre);resize:vertical}
.irisbt{display:flex;gap:10px;margin-top:10px}
</style>
<?php dash_bas(); ?>
