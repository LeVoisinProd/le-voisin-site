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

$ETATS_ENC = ['attendu'=>'attendu','recu'=>'reçu','partiel'=>'partiel','sans_objet'=>'sans objet'];
$ETATS_VER = ['attendu'=>'attendu','verse'=>'versé','partiel'=>'partiel','sans_objet'=>'sans objet'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    $bid = (int)($_POST['id'] ?? 0);
    $col = (string)($_POST['col'] ?? '');
    $val = (string)($_POST['val'] ?? '');
    $ok  = ($col === 'encaissement' && isset($ETATS_ENC[$val]))
        || ($col === 'versement'    && isset($ETATS_VER[$val]));
    if ($bid && $ok) {
        $dcol = $col === 'encaissement' ? 'encaisse_le' : 'verse_le';
        $fait = in_array($val, ['recu', 'verse'], true) ? date('Y-m-d') : null;
        DB::pdo()->prepare("UPDATE booking SET $col = ?, $dcol = ? WHERE id = ?")
                 ->execute([$val, $fait, $bid]);
    }
    redirect('/dashboard.php?e=finances&s=' . (int)($_POST['s'] ?? 0) . '&v=releve');
}

$vue = ($_GET['v'] ?? '') === 'releve' ? 'releve' : 'apercu';

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

/* LE RELEVÉ. Une ligne par date, le détail en colonnes, le total en bas.
   C'est la forme du « Statement » d'artistu, qu'Anna a montrée comme modèle, et
   c'est la seule vue qui répond à la question de fin de période: qui attend
   encore son argent.

   Les colonnes se calculent depuis deal_item, en une requête et non en une par
   ligne: sur soixante dates, une requête par ligne se sentirait. */
$releve = DB::all(
    "SELECT b.id, b.date_debut, b.date_texte, b.projet, b.artiste, b.venue, b.ville,
            b.prix_cession, b.devise, b.statut, b.encaissement, b.versement,
            COALESCE(SUM(CASE WHEN d.type='cachet'        THEN d.montant END),0) cachet,
            COALESCE(SUM(CASE WHEN d.type='frais_booking' THEN d.montant END),0) booking,
            COALESCE(SUM(CASE WHEN d.type='voyage'        THEN d.montant END),0) voyage,
            COALESCE(SUM(CASE WHEN d.type NOT IN ('cachet','frais_booking','voyage')
                              THEN d.montant END),0) autres,
            COALESCE(SUM(CASE WHEN d.charge='nous' THEN d.montant END),0) a_nous,
            COUNT(d.id) n_lignes
       FROM booking b
       LEFT JOIN deal_item d ON d.booking_id = b.id
      WHERE b.supprime_le IS NULL AND b.date_debut >= ? AND b.date_debut < ?
        AND b.statut <> 'canceled'
      GROUP BY b.id ORDER BY b.date_debut", [$debut, $fin]);

dash_haut('finances', 'saison ' . $saison . '-' . ($saison + 1) . ' · ' . count($lignes) . ' dates');
?>
<div class="onglets">
  <a href="/dashboard.php?e=finances&amp;s=<?= $saison ?>" class="<?= $vue==='apercu'?'ici':'' ?>">Aperçu</a>
  <a href="/dashboard.php?e=finances&amp;s=<?= $saison ?>&amp;v=releve" class="<?= $vue==='releve'?'ici':'' ?>">Relevé</a>
</div>
<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="finances">
  <select name="s" onchange="this.form.submit()">
    <?php foreach ($saisons as $x): ?>
      <option value="<?= $x['s'] ?>"<?= $saison === (int)$x['s'] ? ' selected' : '' ?>>
        Saison <?= $x['s'] ?>-<?= $x['s'] + 1 ?> (<?= $x['n'] ?>)</option>
    <?php endforeach; ?>
  </select>
</form>

<?php if ($vue === 'releve'): ?>
<div class="zone">
  <div class="tw"><table class="rel">
    <thead><tr>
      <th>Date</th><th>Projet</th><th>Lieu</th>
      <th class="d">Cachets</th><th class="d">Frais booking</th><th class="d">Voyage</th>
      <th class="d">Autres</th><th class="d">À notre charge</th>
      <th class="d">Prix de cession</th><th>Encaissement</th><th>Versement</th>
    </tr></thead>
    <tbody>
    <?php
    $T = ['cachet'=>0.0,'booking'=>0.0,'voyage'=>0.0,'autres'=>0.0,'a_nous'=>0.0,'prix'=>0.0];
    foreach ($releve as $r):
      foreach (['cachet','booking','voyage','autres','a_nous'] as $k) $T[$k] += (float)$r[$k];
      $T['prix'] += (float)$r['prix_cession'];
      $ss = $r['n_lignes'] == 0; ?>
      <tr class="<?= $ss ? 'nul' : '' ?>">
        <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$r['id'] ?>&amp;o=deal"><?=
          e($r['date_texte'] ?: (string)$r['date_debut']) ?></a></td>
        <td><?= e($r['projet'] ?? '') ?><?php if ($r['artiste']): ?>
          <div class="sec"><?= e($r['artiste']) ?></div><?php endif; ?></td>
        <td class="sec"><?= e($r['venue'] ?? '') ?><?php if ($r['ville']): ?>
          <div class="sec"><?= e($r['ville']) ?></div><?php endif; ?></td>
        <?php foreach (['cachet','booking','voyage','autres','a_nous'] as $k): ?>
          <td class="d<?= $k==='a_nous' ? ' neg' : '' ?>"><?=
            (float)$r[$k] ? $fmt($r[$k]) : '<span class="tiret">·</span>' ?></td>
        <?php endforeach; ?>
        <td class="d fort"><?= $r['prix_cession'] !== null
            ? $fmt($r['prix_cession']) . ' ' . e($r['devise']) : '<span class="tiret">·</span>' ?></td>
        <?php foreach ([['encaissement',$ETATS_ENC],['versement',$ETATS_VER]] as [$col,$choix]): ?>
        <td>
          <form method="post" action="/dashboard.php?e=finances" class="inline">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id"  value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="s"   value="<?= $saison ?>">
            <input type="hidden" name="col" value="<?= $col ?>">
            <select name="val" class="p-<?= e($r[$col]) ?>" onchange="this.form.submit()">
              <?php foreach ($choix as $k => $v): ?>
                <option value="<?= $k ?>"<?= $r[$col]===$k?' selected':'' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr>
      <td colspan="3"><strong>Total</strong></td>
      <?php foreach (['cachet','booking','voyage','autres','a_nous'] as $k): ?>
        <td class="d"><strong><?= $T[$k] ? $fmt($T[$k]) : '' ?></strong></td>
      <?php endforeach; ?>
      <td class="d"><strong><?= $fmt($T['prix']) ?></strong></td>
      <td colspan="2"></td>
    </tr></tfoot>
  </table></div>

  <?php $sansDetail = count(array_filter($releve, fn($r) => $r['n_lignes'] == 0)); ?>
  <?php if ($sansDetail): ?>
  <div class="alerte"><strong><?= $sansDetail ?> dates sur <?= count($releve) ?> n'ont
    aucune ligne de détail.</strong> Elles apparaissent en gris et ne comptent dans
    aucune colonne sauf le prix. Un relevé qui ne dit pas ce qu'il ignore ment par
    omission.</div>
  <?php endif; ?>

  <div class="avis">
    <h2>Deux états et pas un</h2>
    <p><strong>Encaissement</strong> dit si le lieu a payé. <strong>Versement</strong> dit
       si l'artiste a été payé. Les deux ne vont pas ensemble: une date peut être
       encaissée sans que l'artiste soit payé, et c'est le cas normal pendant quelques
       semaines. L'inverse arrive aussi, quand on avance le salaire avant que le lieu
       ne règle, et c'est exactement le trou de trésorerie que la réserve doit couvrir.</p>
    <p>Une seule colonne « payé » ne saurait pas dire lequel des deux.</p>
  </div>
</div>

<style>
.onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait)}
.onglets a{padding:8px 15px;font-size:13.5px;text-decoration:none;
  border-bottom:3px solid transparent;color:var(--doux)}
.onglets a.ici{color:var(--encre);border-bottom-color:var(--jaune);font-weight:600}
table.rel{font-size:13px}
table.rel td.fort{font-weight:600}
table.rel td.neg{color:var(--orange)}
table.rel tr.nul td{opacity:.45}
table.rel tfoot td{border-top:2px solid var(--encre);background:var(--fond2);padding-top:10px}
.tiret{color:var(--trait)}
form.inline{display:inline}
table.rel select{font-size:12px;padding:2px 5px;border:1px solid var(--trait);
  border-radius:3px;background:var(--papier);color:var(--encre)}
table.rel select.p-recu,table.rel select.p-verse{background:#e7f6ea;border-color:#bfe3c8}
table.rel select.p-partiel{background:#fff6d9;border-color:#f0dfa3}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]) table.rel select{
  background:var(--papier)!important}}
</style>
<?php dash_bas(); return; ?>
<?php endif; ?>

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
.onglets{display:flex;gap:2px;padding:12px 26px 0;border-bottom:1px solid var(--trait)}
.onglets a{padding:8px 15px;font-size:13.5px;text-decoration:none;
  border-bottom:3px solid transparent;color:var(--doux)}
.onglets a.ici{color:var(--encre);border-bottom-color:var(--jaune);font-weight:600}
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
