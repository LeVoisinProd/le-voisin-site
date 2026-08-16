<?php
/**
 * Écran Marketing. [16.08.2026]
 *
 * Les publications: ce qu'on annonce, quand, où, et si c'est parti.
 *
 * CE QUI LE REND UTILE PLUTÔT QUE DÉCORATIF: une publication se rattache à une
 * DATE. Le dashboard actuel garde `lv-marketing` à côté des tournées, sans lien,
 * et il faut donc se souvenir de ce qu'on annonce. Ici la liste des dates à
 * venir sans aucune publication est affichée en tête: c'est la seule question
 * que cet écran doit poser.
 *
 * L'ENVOI N'EST PAS ICI ET N'Y SERA PAS DE SITÔT. Publier demande les API des
 * plateformes, chacune avec son authentification. Cet écran prépare et suit; le
 * geste de publier reste manuel, et le dire évite d'attendre autre chose.
 */
declare(strict_types=1);

$ST = ['idee'=>'idée','a_ecrire'=>'à écrire','prete'=>'prête','publiee'=>'publiée','annulee'=>'annulée'];
$PF = ['Instagram','Facebook','LinkedIn','Newsletter','Site','Presse','Autre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    /* Le rôle décide aussi de l'écriture, et pas seulement de l'accès à
       l'écran: `production` lit les Finances sans les modifier. Le routeur
       ne peut pas le faire à notre place, lui ne voit pas les POST. */
    dash_exige_ecriture('marketing');
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'ajouter') {
        $t = trim((string)($_POST['titre'] ?? ''));
        if ($t !== '') {
            $d = trim((string)($_POST['date_prevue'] ?? ''));
            DB::pdo()->prepare('INSERT INTO publication (date_prevue,plateforme,titre,booking_id,statut)
                                VALUES (?,?,?,?,?)')
              ->execute([preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null,
                         trim((string)($_POST['plateforme'] ?? '')) ?: null, $t,
                         (int)($_POST['booking_id'] ?? 0) ?: null,
                         isset($ST[(string)($_POST['statut'] ?? '')]) ? $_POST['statut'] : 'idee']);
            dash_flash('Publication ajoutée.');
        } else dash_flash('Il faut au moins un titre.', 'err');
    }
    if ($a === 'statut') {
        $v = (string)($_POST['val'] ?? '');
        if (isset($ST[$v])) DB::pdo()->prepare('UPDATE publication SET statut = ? WHERE id = ?')
                                     ->execute([$v, (int)($_POST['id'] ?? 0)]);
    }
    if ($a === 'supprimer') {
        DB::pdo()->prepare('UPDATE publication SET supprime_le = NOW() WHERE id = ?')
                 ->execute([(int)($_POST['id'] ?? 0)]);
        dash_flash('Publication retirée.');
    }
    redirect('/dashboard.php?e=marketing');
}

$pubs = DB::all(
    "SELECT p.*, b.date_texte, b.date_debut AS b_date, b.venue, b.projet
       FROM publication p LEFT JOIN booking b ON b.id = p.booking_id
      WHERE p.supprime_le IS NULL
      ORDER BY p.statut = 'publiee', p.date_prevue IS NULL, p.date_prevue, p.id DESC");

/* Les dates à venir que rien n'annonce. C'est la seule question de cet écran. */
$muettes = DB::all(
    "SELECT b.id, b.date_debut, b.date_texte, b.projet, b.venue, b.ville,
            DATEDIFF(b.date_debut, CURDATE()) jours
       FROM booking b
      WHERE b.supprime_le IS NULL AND b.date_debut >= CURDATE()
        AND b.statut IN ('confirmed','option')
        AND NOT EXISTS (SELECT 1 FROM publication p
                         WHERE p.booking_id = b.id AND p.supprime_le IS NULL)
      ORDER BY b.date_debut LIMIT 10");

$dates = DB::all("SELECT id, date_debut, date_texte, projet, venue FROM booking
                   WHERE supprime_le IS NULL AND date_debut >= CURDATE()
                     AND statut <> 'canceled' ORDER BY date_debut LIMIT 60");

dash_haut('marketing', count($pubs) . ' publication' . (count($pubs) > 1 ? 's' : ''));
?>
<?php dash_flash_html(); ?>
<div class="zone">

<?php if ($muettes): ?>
<section class="bloc rouge">
  <h2>Dates à venir que rien n'annonce <span class="n"><?= count($muettes) ?></span></h2>
  <?php foreach ($muettes as $d): ?>
    <div class="lg">
      <span class="q"><?= (int)$d['jours'] ?> j</span>
      <span><?= e($d['projet'] ?: '(sans projet)') ?>
        <span class="sec"><?= e($d['venue'] ?? '') ?><?php if ($d['ville']): ?>,
          <?= e($d['ville']) ?><?php endif; ?></span></span>
      <form method="post" action="/dashboard.php?e=marketing" class="inline">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="ajouter">
        <input type="hidden" name="booking_id" value="<?= (int)$d['id'] ?>">
        <input type="hidden" name="titre" value="<?= e(($d['projet'] ?: 'Date') . ' · ' . ($d['venue'] ?? '')) ?>">
        <input type="hidden" name="statut" value="idee">
        <button type="submit" class="mini">créer une publication</button>
      </form>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<h3 class="sect">Les publications</h3>
<?php if (!$pubs): ?>
  <p class="sec">Aucune. Le bouton ci-dessus en crée une depuis une date.</p>
<?php else: ?>
<div class="tw"><table>
  <thead><tr><th>Prévue</th><th>Titre</th><th>Plateforme</th><th>Date liée</th>
    <th>Statut</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pubs as $p): ?>
    <tr class="<?= $p['statut'] === 'publiee' ? 'ok' : '' ?>">
      <td class="sec"><?= e($p['date_prevue'] ?? '') ?></td>
      <td><?= e($p['titre']) ?></td>
      <td class="sec"><?= e($p['plateforme'] ?? '') ?></td>
      <td class="sec"><?php if ($p['booking_id']): ?>
        <a href="/dashboard.php?e=bookings&amp;b=<?= (int)$p['booking_id'] ?>"><?=
          e($p['date_texte'] ?: (string)$p['b_date']) ?></a><?php endif; ?></td>
      <td>
        <form method="post" action="/dashboard.php?e=marketing" class="inline">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="statut">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <select name="val" onchange="this.form.submit()">
            <?php foreach ($ST as $k=>$v): ?>
              <option value="<?= $k ?>"<?= $p['statut']===$k?' selected':'' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td class="d">
        <form method="post" action="/dashboard.php?e=marketing" class="inline"
              onsubmit="return confirm('Retirer cette publication ?')">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="supprimer">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button type="submit" class="x">×</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<h3 class="sect">Ajouter</h3>
<form method="post" action="/dashboard.php?e=marketing" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="action" value="ajouter">
  <input type="date" name="date_prevue">
  <input type="text" name="titre" placeholder="Titre" required>
  <select name="plateforme"><option value="">Plateforme</option>
    <?php foreach ($PF as $x): ?><option><?= e($x) ?></option><?php endforeach; ?></select>
  <select name="booking_id"><option value="">Aucune date liée</option>
    <?php foreach ($dates as $d): ?>
      <option value="<?= (int)$d['id'] ?>"><?= e(substr((string)$d['date_debut'],0,10)) ?>
        · <?= e($d['projet'] ?: $d['venue'] ?: '') ?></option>
    <?php endforeach; ?></select>
  <select name="statut"><?php foreach ($ST as $k=>$v): ?>
    <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
  <button type="submit">ajouter</button>
</form>

<div class="avis">
  <h2>Publier reste un geste manuel</h2>
  <p>Cet écran prépare et suit. Envoyer demanderait les API de chaque plateforme,
     chacune avec son authentification, et cela n'est pas dans la file. Le dire
     évite d'attendre autre chose.</p>
</div>
</div>
<style>
h3.sect{font-size:12.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
  margin:26px 0 8px;border-bottom:1px solid var(--trait);padding-bottom:5px}
h3.sect .n{float:right;font-weight:400}
.bloc{margin-bottom:24px;border:1px solid var(--trait);border-radius:6px;padding:15px 17px}
.bloc.rouge{border-left:4px solid var(--orange)}
.bloc h2{font-size:14px;margin:0 0 8px;text-transform:uppercase;letter-spacing:.04em}
.bloc h2 .n{float:right;color:var(--doux);font-weight:400;text-transform:none}
.lg{display:flex;gap:12px;align-items:baseline;padding:7px 0;
  border-bottom:1px solid var(--trait);font-size:13.5px}
.lg:last-child{border-bottom:0}
.lg .q{flex:0 0 52px;text-align:right;font-weight:600;font-size:13px}
.lg .sec{color:var(--doux);font-size:12.5px;display:block}
.lg form{margin-left:auto}
button.mini{padding:4px 11px;font-size:12px}
button.x{background:none;color:var(--doux);padding:0 6px;font-size:16px;cursor:pointer}
form.inline{display:inline}
td.d{text-align:right}
tr.ok td{opacity:.5}
table select{font-size:12.5px;padding:3px 6px;border:1px solid var(--trait);
  border-radius:3px;background:var(--papier);color:var(--encre)}
form.ajl{display:flex;gap:8px;flex-wrap:wrap;align-items:center;max-width:980px}
form.ajl input,form.ajl select{padding:7px 10px;font-size:13.5px;font-family:inherit;
  border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
form.ajl input[name=titre]{flex:1;min-width:180px}
.avis{margin-top:26px;padding:14px 18px;background:var(--fond2);
  border-left:4px solid var(--jaune);max-width:76ch}
.avis h2{font-size:14px;margin:0 0 7px}.avis p{margin:0;font-size:13.5px;color:var(--doux)}
</style>
<?php ?>
<?php dash_bas(); ?>
