<?php
/**
 * Écran Finances. [16.08.2026]
 *
 * Anna: « Les totaux se mettent à jour au fil de la saisie, les budgets restent
 * donc transparents sans tableur à côté. »
 *
 * CE QU'IL FAIT AUJOURD'HUI: la vue d'ensemble de ce que les dates rapportent,
 * saison par saison, et l'écart entre le prix annoncé et la somme des lignes.
 *
 * CE QU'IL NE FAIT PAS ENCORE: les factures et le lien bexio. Le client bexio
 * actuel vit dans Apps Script, et le porter en PHP est chiffré entre 12 h et
 * 20 h pour le seul OAuth2, plus 6 h à 10 h par endpoint. Ce n'est pas un
 * oubli, c'est une file d'attente, et l'écran le dit au lieu de faire semblant.
 *
 * DEUX MISES EN GARDE QUI VIENNENT DU MODÈLE ÉCONOMIQUE DU 15.08.2026 et qui
 * sont écrites dans l'écran, pas seulement ici:
 *
 * 1. Un prix de cession est encaissé par L'ASSOCIATION PRODUCTRICE, pas par Le
 *    Voisin CH. Additionner les cessions ne donne donc pas le chiffre d'affaires
 *    du bureau. Comment le temps du bureau remonte jusqu'à ses comptes n'est
 *    documenté nulle part, et c'est la question ouverte la plus chère de la
 *    maison.
 * 2. Une somme de prix n'est pas un résultat: il manque les charges. Le modèle
 *    complet est dans dados/business_plan_2027_2030/ du dépôt de travail.
 */
declare(strict_types=1);

const MOIS_SAISON = 9;

$auj = new DateTimeImmutable('today');
$saisonAuj = (int)$auj->format('n') >= MOIS_SAISON
           ? (int)$auj->format('Y') : (int)$auj->format('Y') - 1;
$saison = isset($_GET['s']) && ctype_digit((string)$_GET['s']) ? (int)$_GET['s'] : $saisonAuj;

$debut = sprintf('%04d-%02d-01', $saison, MOIS_SAISON);
$fin   = sprintf('%04d-%02d-01', $saison + 1, MOIS_SAISON);

$saisons = DB::all('SELECT DISTINCT YEAR(date_debut) - (MONTH(date_debut) < ' . MOIS_SAISON . ') AS s,
                           COUNT(*) n
                      FROM booking WHERE supprime_le IS NULL AND date_debut IS NOT NULL
                     GROUP BY s ORDER BY s DESC');

/* Le total par statut. Une option n'est pas un encaissement, et les additionner
   donnerait une saison plus riche qu'elle n'est. */
$parStatut = DB::all(
    "SELECT statut, COUNT(*) n,
            SUM(CASE WHEN devise='CHF' THEN prix_cession ELSE 0 END) chf,
            SUM(CASE WHEN devise='EUR' THEN prix_cession ELSE 0 END) eur,
            SUM(prix_cession IS NULL) sans
       FROM booking
      WHERE supprime_le IS NULL AND date_debut >= ? AND date_debut < ?
      GROUP BY statut", [$debut, $fin]);

$lignes = DB::all(
    "SELECT b.*,
            (SELECT COALESCE(SUM(d.montant),0) FROM deal_item d
              WHERE d.booking_id = b.id AND d.charge = 'incluse') AS somme_lignes,
            (SELECT COUNT(*) FROM deal_item d WHERE d.booking_id = b.id) AS n_lignes
       FROM booking b
      WHERE b.supprime_le IS NULL AND b.date_debut >= ? AND b.date_debut < ?
      ORDER BY b.date_debut", [$debut, $fin]);

/* La ventilation par nature, toutes dates de la saison confondues. */
$parType = DB::all(
    "SELECT d.type, d.charge, COUNT(*) n, SUM(d.montant) total
       FROM deal_item d JOIN booking b ON b.id = d.booking_id
      WHERE b.supprime_le IS NULL AND b.date_debut >= ? AND b.date_debut < ?
      GROUP BY d.type, d.charge ORDER BY SUM(d.montant) DESC", [$debut, $fin]);

$TY = ['cachet'=>'cachet','frais_booking'=>'frais de booking','voyage'=>'voyage',
       'hebergement'=>'hébergement','per_diem'=>'per diem','droits'=>'droits',
       'materiel'=>'matériel','catering'=>'catering','marge'=>'marge','autre'=>'autre'];
$CG = ['incluse'=>'incluse','lieu'=>'charge du lieu','nous'=>'à notre charge'];
$ETIQ = ['option'=>'option','confirmed'=>'confirmé','canceled'=>'annulé','pending'=>'en attente'];

$sansPrix = count(array_filter($lignes, fn($l) => $l['prix_cession'] === null));
$ecarts   = array_filter($lignes, fn($l) => $l['n_lignes'] > 0 && $l['prix_cession'] !== null
                       && abs((float)$l['prix_cession'] - (float)$l['somme_lignes']) > 0.5);

$fmt = fn($v) => number_format((float)$v, 0, ',', ' ');

dash_haut('finances', 'saison ' . $saison . '-' . ($saison + 1) . ' · ' . count($lignes) . ' dates');
?>
<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="finances">
  <select name="s" onchange="this.form.submit()">
    <?php foreach ($saisons as $x): ?>
      <option value="<?= $x['s'] ?>"<?= $saison === (int)$x['s'] ? ' selected' : '' ?>>
        Saison <?= $x['s'] ?>-<?= $x['s'] + 1 ?> (<?= $x['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
</form>

<div class="zone">

  <h3 class="sect">Prix de cession par statut</h3>
  <div class="tw"><table>
    <thead><tr><th>Statut</th><th class="d">Dates</th><th class="d">CHF</th>
      <th class="d">EUR</th><th class="d">Sans prix</th></tr></thead>
    <tbody>
    <?php $tCHF = $tEUR = 0; foreach ($parStatut as $s):
      if ($s['statut'] === 'confirmed') { $tCHF = (float)$s['chf']; $tEUR = (float)$s['eur']; } ?>
      <tr class="s-<?= e($s['statut']) ?>">
        <td><span class="et <?= e($s['statut']) ?>"><?= e($ETIQ[$s['statut']]) ?></span></td>
        <td class="d"><?= (int)$s['n'] ?></td>
        <td class="d"><?= $s['chf'] > 0 ? $fmt($s['chf']) : '' ?></td>
        <td class="d"><?= $s['eur'] > 0 ? $fmt($s['eur']) : '' ?></td>
        <td class="d sec"><?= (int)$s['sans'] ?: '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <div class="avis">
    <h2>Ce que ces totaux ne disent pas</h2>
    <p><strong>Un prix de cession est encaissé par l'association productrice, pas par
       Le Voisin CH.</strong> Additionner les cessions ne donne donc pas le chiffre
       d'affaires du bureau. Comment le temps du bureau, quatre heures à 80 CHF par
       date plus une demi-journée d'administration, remonte jusqu'aux comptes du
       Voisin n'est documenté nulle part: c'est la question ouverte la plus chère de
       la maison, et le modèle économique du 15.08 a buté dessus.</p>
    <p>Et une somme de prix n'est pas un résultat: il y manque les charges. Le calcul
       complet, avec la capacité de travail et le seuil d'équilibre, est dans
       <code>dados/business_plan_2027_2030/</code>.</p>
  </div>

  <?php if ($sansPrix): ?>
  <div class="alerte"><strong><?= $sansPrix ?> dates de cette saison n'ont pas de prix.</strong>
    Elles comptent dans le calendrier et pas dans ces totaux.</div>
  <?php endif; ?>

  <?php if ($parType): ?>
  <h3 class="sect">Ventilation par nature <span class="n">sur les dates détaillées</span></h3>
  <div class="tw"><table>
    <thead><tr><th>Nature</th><th>Charge</th><th class="d">Lignes</th><th class="d">Total</th></tr></thead>
    <tbody>
    <?php foreach ($parType as $t): ?>
      <tr class="c-<?= e($t['charge']) ?>">
        <td><?= e($TY[$t['type']] ?? $t['type']) ?></td>
        <td class="sec"><?= e($CG[$t['charge']]) ?></td>
        <td class="d sec"><?= (int)$t['n'] ?></td>
        <td class="d"><?= $t['total'] !== null ? $fmt($t['total']) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <?php if ($ecarts): ?>
  <h3 class="sect">Écarts entre le prix annoncé et le détail
    <span class="n"><?= count($ecarts) ?></span></h3>
  <p class="sec pt">Un écart n'est pas une erreur en soi: il dit que le prix et les
     lignes ne se sont pas encore parlé. Il devient une erreur le jour où l'on
     facture l'un en croyant l'autre.</p>
  <div class="tw"><table>
    <thead><tr><th>Date</th><th>Lieu</th><th class="d">Annoncé</th>
      <th class="d">Détail</th><th class="d">Écart</th></tr></thead>
    <tbody>
    <?php foreach ($ecarts as $l): $ec = (float)$l['prix_cession'] - (float)$l['somme_lignes']; ?>
      <tr>
        <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$l['id'] ?>&amp;o=deal"><?=
          e($l['date_texte'] ?: (string)$l['date_debut']) ?></a></td>
        <td class="sec"><?= e($l['venue'] ?? '') ?></td>
        <td class="d"><?= $fmt($l['prix_cession']) ?></td>
        <td class="d sec"><?= $fmt($l['somme_lignes']) ?></td>
        <td class="d ec"><?= $fmt($ec) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <h3 class="sect">Factures et bexio</h3>
  <div class="avis">
    <h2>Pas encore repris</h2>
    <p>Les factures et les relevés par artiste attendent le lien bexio. Le client
       actuel vit dans Apps Script, où l'authentification vient gratuitement avec
       Google. En PHP sans Composer, il faut écrire un client OAuth2 à la main,
       chiffré entre 12 h et 20 h, plus 6 h à 10 h par endpoint.</p>
    <p>Ce n'est pas un oubli: c'est une file d'attente. En attendant, la voie bexio
       actuelle fonctionne et reste où elle est.</p>
  </div>
</div>

<style>
h3.sect{font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
  margin:30px 0 8px;border-bottom:1px solid var(--trait);padding-bottom:5px}
h3.sect:first-child{margin-top:0}
h3.sect .n{font-weight:400;text-transform:none;letter-spacing:0}
td.d,th.d{text-align:right;white-space:nowrap}
td.ec{color:var(--orange);font-weight:600}
tr.s-canceled td,tr.c-lieu td,tr.c-nous td{opacity:.6}
.et{font-size:11px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.et.confirmed{background:#e7f6ea;border-color:#bfe3c8;color:#1c5c2e}
.et.option{background:#fff6d9;border-color:#f0dfa3;color:#6b5312}
.et.canceled{background:#fbe9e7;border-color:#f0c3bb;color:#7a2b1e}
.avis{margin:20px 0;padding:15px 19px;background:var(--fond2);
  border-left:4px solid var(--jaune);max-width:78ch}
.avis h2{font-size:14.5px;margin:0 0 8px}
.avis p{margin:0 0 8px;font-size:13.5px;color:var(--doux)}
.avis p:last-child{margin:0}
.avis code{font-size:12.5px}
.alerte{margin:16px 0;padding:11px 15px;background:var(--fond2);
  border-left:4px solid var(--orange);font-size:13.5px;max-width:78ch}
.pt{margin:0 0 10px;max-width:76ch}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]) .et{
  background:transparent!important;color:inherit!important}}
</style>
<?php dash_bas(); ?>
