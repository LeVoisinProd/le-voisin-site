<?php
/**
 * Finances — Demandes de fonds. [16.08.2026]
 *
 * La troisième vue, à côté de l'Aperçu et du Relevé. Elle répond à ce
 * qu'aucune des deux autres ne dit: combien on a demandé, à qui, pour quoi, et
 * ce qui est revenu.
 *
 * LES TROIS CHIFFRES DU HAUT NE SONT PAS CEUX DE L'APERÇU, et il faut le dire
 * pour qu'on ne les additionne pas: ici « demandé » est ce qu'on a osé
 * demander, « accordé » ce qui est tombé, et l'écart entre les deux est le
 * taux d'obtention réel — le seul chiffre qui aide à préparer la demande
 * suivante.
 *
 * L'ORDRE PAR DÉFAUT SUIT L'URGENCE et non la date de saisie: ce qui a un
 * délai proche d'abord, puis la priorité, puis le reste. La question qu'on se
 * pose en ouvrant cet écran est « qu'est-ce qui va me passer sous le nez ».
 */
declare(strict_types=1);
/** @var bool $ecrit */

$PRIOS   = ['P0'=>'P0','P1'=>'P1','P2'=>'P2','P3'=>'P3','P4'=>'P4'];
$STATUTS_F = [
    'a-preparer' => 'À préparer', 'en-cours' => 'En cours', 'soumis' => 'Soumis',
    'en-attente' => 'En attente', 'en-suspens' => 'En suspens',
    'accorde'    => 'Accordé', 'refuse' => 'Refusé', 'decompte' => 'Décompte',
];

$fAsso   = trim((string)($_GET['fa'] ?? ''));
$fStatut = (string)($_GET['fs'] ?? '');
$fPrio   = (string)($_GET['fp'] ?? '');

$where = ['supprime_le IS NULL']; $args = [];
if ($fAsso !== '')                     { $where[] = 'asso = ?';     $args[] = $fAsso; }
if (isset($STATUTS_F[$fStatut]))       { $where[] = 'statut = ?';   $args[] = $fStatut; }
if (isset($PRIOS[$fPrio]))             { $where[] = 'priorite = ?'; $args[] = $fPrio; }

$dem = DB::all('SELECT * FROM demande_fonds WHERE ' . implode(' AND ', $where) .
    /* L'urgence d'abord: un délai proche, puis la priorité. Les sans-délai
       vont à la fin, pas au début — ce sont celles qui n'attendent personne. */
    " ORDER BY COALESCE(delai,'9999-12-31'), priorite, inst", $args);

$assos = DB::all("SELECT DISTINCT asso FROM demande_fonds WHERE supprime_le IS NULL ORDER BY asso");

$tDem = $tAcc = 0.0; $nAcc = 0; $enRetard = 0; $auj = date('Y-m-d');
foreach ($dem as $d) {
    $tDem += (float)$d['demande'];
    $tAcc += (float)$d['accorde'];
    if ((float)$d['accorde'] > 0) $nAcc++;
    if ($d['delai'] && (string)$d['delai'] < $auj
        && in_array($d['statut'], ['a-preparer','en-cours'], true)) $enRetard++;
}
$taux = $tDem > 0 ? $tAcc / $tDem * 100 : 0.0;
$fm = fn($v) => number_format((float)$v, 0, ',', ' ');
?>

<div class="kpi">
  <div class="k k-dem"><span class="kl">Demandé</span><span class="kv"><?= $fm($tDem) ?> CHF</span>
    <span class="kn"><?= count($dem) ?> demande<?= count($dem) > 1 ? 's' : '' ?></span></div>
  <div class="k k-acc"><span class="kl">Accordé</span><span class="kv"><?= $fm($tAcc) ?> CHF</span>
    <span class="kn"><?= $nAcc ?> obtenue<?= $nAcc > 1 ? 's' : '' ?></span></div>
  <div class="k k-tx"><span class="kl">Taux d'obtention</span>
    <span class="kv"><?= number_format($taux, 0, ',', ' ') ?> %</span>
    <span class="kn">sur ce qui est filtré</span></div>
</div>

<?php if ($enRetard): ?>
  <div class="rap ecart"><strong><?= $enRetard ?></strong>
    demande<?= $enRetard > 1 ? 's' : '' ?> dont le délai est passé et qui
    <?= $enRetard > 1 ? 'sont' : 'est' ?> encore à préparer ou en cours.</div>
<?php endif; ?>

<form class="filtres" method="get" action="/dashboard.php">
  <input type="hidden" name="e" value="finances">
  <input type="hidden" name="v" value="fonds">
  <select name="fa"><option value="">Toutes les assos</option>
    <?php foreach ($assos as $a): ?>
      <option value="<?= e((string)$a['asso']) ?>" <?= $fAsso === $a['asso'] ? 'selected' : '' ?>><?= e((string)$a['asso']) ?></option>
    <?php endforeach; ?></select>
  <select name="fs"><option value="">Tous les statuts</option>
    <?php foreach ($STATUTS_F as $k => $l): ?>
      <option value="<?= $k ?>" <?= $fStatut === $k ? 'selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?></select>
  <select name="fp"><option value="">Toutes priorités</option>
    <?php foreach ($PRIOS as $k => $l): ?>
      <option value="<?= $k ?>" <?= $fPrio === $k ? 'selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?></select>
  <button type="submit">filtrer</button>
  <?php if ($fAsso !== '' || $fStatut !== '' || $fPrio !== ''): ?>
    <a class="vider" href="/dashboard.php?e=finances&amp;v=fonds">tout voir</a>
  <?php endif; ?>
</form>

<?php if ($dem): ?>
  <div class="tw"><table>
    <thead><tr>
      <th>Pr.</th><th>Association</th><th>Institution / bailleur</th><th>Projet</th>
      <th>Type</th><th class="d">Demandé</th><th class="d">Accordé</th>
      <th>Statut</th><th>Délai</th><th>Réponse</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($dem as $d): $did = (int)$d['id'];
          $ret = $d['delai'] && (string)$d['delai'] < $auj
                 && in_array($d['statut'], ['a-preparer','en-cours'], true); ?>
      <tr>
        <td><span class="pr pr-<?= e($d['priorite']) ?>"><?= e($d['priorite']) ?></span></td>
        <td class="sec"><?= e((string)$d['asso']) ?></td>
        <td><strong><?= e((string)$d['inst']) ?></strong>
          <?php if ($d['canton']): ?><span class="np"><?= e((string)$d['canton']) ?></span><?php endif; ?></td>
        <td class="sec"><?= e((string)($d['proj'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($d['type'] ?? '')) ?></td>
        <td class="d"><?= $d['demande'] !== null ? $fm($d['demande']) . ' ' . e($d['devise']) : '—' ?></td>
        <td class="d"><?= $d['accorde'] !== null && (float)$d['accorde'] > 0
            ? '<strong>' . $fm($d['accorde']) . '</strong> ' . e($d['devise']) : '—' ?></td>
        <td><span class="st st-<?= e($d['statut']) ?>"><?= e($STATUTS_F[$d['statut']] ?? $d['statut']) ?></span></td>
        <td class="sec <?= $ret ? 'retard' : '' ?>">
          <?= $d['delai'] ? e(date('d.m.Y', strtotime((string)$d['delai']))) : '—' ?>
          <?php if ($ret): ?><br><span class="np">dépassé</span><?php endif; ?></td>
        <td class="sec"><?= $d['reponse'] ? e(date('d.m.Y', strtotime((string)$d['reponse']))) : '—' ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="/dashboard.php?e=finances&amp;v=fonds" class="inline"
                  onsubmit="return confirm('Supprimer cette demande ? Elle restera en base.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="df" value="supprimer">
              <input type="hidden" name="ligne" value="<?= $did ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="vide">Aucune demande<?= ($fAsso !== '' || $fStatut !== '' || $fPrio !== '') ? ' avec ces filtres' : '' ?>.</p>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="/dashboard.php?e=finances&amp;v=fonds" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="df" value="ajouter">
  <select name="priorite">
    <?php foreach ($PRIOS as $k => $l): ?><option value="<?= $k ?>" <?= $k==='P2'?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
  </select>
  <input type="text" name="asso" placeholder="Association" size="14" required
         list="l-assos" value="<?= e($fAsso) ?>">
  <datalist id="l-assos"><?php foreach ($assos as $a): ?><option value="<?= e((string)$a['asso']) ?>"><?php endforeach; ?></datalist>
  <input type="text" name="inst" placeholder="Institution / bailleur" size="22" required>
  <input type="text" name="proj" placeholder="Projet" size="14">
  <input type="text" name="type" placeholder="Type" size="10">
  <input type="text" name="canton" placeholder="Canton" size="5">
  <input type="text" name="demande" placeholder="Demandé" size="8">
  <select name="statut">
    <?php foreach ($STATUTS_F as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?>
  </select>
  <input type="date" name="delai" title="Délai de dépôt">
  <button type="submit">ajouter</button>
</form>
<p class="aide-f">Le <strong>délai</strong> est la date limite du financeur; la <strong>réponse</strong>
   est celle à laquelle il répond. Ce sont deux calendriers différents, et l'on se fait
   avoir par les deux.</p>
<?php endif; ?>

<style>
.kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px}
.k{border:1px solid var(--trait);border-radius:8px;padding:14px 16px;display:flex;
  flex-direction:column;gap:2px}
.kl{font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:var(--doux)}
.kv{font-size:24px;font-weight:600;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.kn{font-size:12px;color:var(--doux)}
.k-acc .kv{color:#5c8f28}
.k-tx .kv{color:#3a6fd0}
.pr{font-size:11px;font-weight:700;padding:2px 6px;border-radius:3px;border:1px solid var(--trait)}
.pr-P0{border-color:#e2653a;color:#c1441a}
.pr-P1{border-color:#d9a800;color:#8a6a00}
.st{font-size:11.5px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.st-accorde{border-color:#7bb33a;color:#4d7a1e;font-weight:600}
.st-refuse{opacity:.6}
.st-en-cours{border-color:#e2653a;color:#c1441a}
.st-soumis,.st-en-attente{border-color:#3a6fd0;color:#2c56a8}
.st-decompte{border-color:#9a7a2a;color:#7a5f18;font-weight:600}
td.retard{color:#e2653a}
.np{font-size:10.5px;border:1px solid var(--trait);border-radius:3px;padding:0 4px;
  margin-left:6px;color:var(--doux)}
.aide-f{font-size:12.5px;color:var(--doux);margin:8px 0 0;max-width:80ch}
</style>
