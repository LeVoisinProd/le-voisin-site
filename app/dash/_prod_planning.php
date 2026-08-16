<?php
/**
 * Onglet Planning. [16.08.2026]
 *
 * DEUX CHOSES QUI NE SE CONFONDENT PAS, et le dashboard les sépare déjà:
 *
 *   LES ÉTAPES DE TRAVAIL — conception, résidences, répétitions, montage, jeu,
 *   démontage — avec leurs dates et leur lieu. C'est ce qui va dans le dossier
 *   de subvention, et c'est ce que la feuille de route reprend.
 *
 *   LA GRILLE DES JOURS — du départ au retour, un bouton par jour, cochés ceux
 *   qui sont effectivement travaillés. C'est ce qui sert à la rémunération et
 *   aux défraiements: entre le 3 et le 12 mars, une équipe ne travaille pas
 *   forcément dix jours.
 */
declare(strict_types=1);
/** @var array $d */ /** @var int $pid */ /** @var bool $ecrit */ /** @var callable $lien */

$etapes = $d['planning']['dates'] ?? [];
usort($etapes, fn($a, $b) => (string)($a['debut'] ?? '9999') <=> (string)($b['debut'] ?? '9999'));
$jours = $d['planning']['jours'] ?? [];
?>

<h3>Étapes de travail</h3>
<?php if ($etapes): ?>
  <div class="tbl"><table>
    <thead><tr><th>Du</th><th>Au</th><th>Phase</th><th>Lieu</th><th>Ville</th><th>Pays</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($etapes as $r): $rid = (string)($r['id'] ?? ''); ?>
      <tr>
        <td class="sec"><?= $r['debut'] ? e(date('d.m.Y', strtotime((string)$r['debut']))) : '—' ?></td>
        <td class="sec"><?= $r['fin'] ? e(date('d.m.Y', strtotime((string)$r['fin']))) : '' ?></td>
        <td><span class="ph"><?= e(ProdFiche::PHASES[$r['phase'] ?? ''] ?? '—') ?></span></td>
        <td class="sec"><?= e((string)($r['lieu'] ?? '')) ?>
          <?php if ($r['adresse'] ?? ''): ?><br><span class="pt"><?= e((string)$r['adresse']) ?></span><?php endif; ?></td>
        <td class="sec"><?= e((string)($r['ville'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['pays'] ?? '')) ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('planning')) ?>" class="inline"
                  onsubmit="return confirm('Supprimer cette étape ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="planning.dates">
              <input type="hidden" name="ligne" value="<?= e($rid) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="aide">Aucune étape. Ajoutez-les ci-dessous: ce sont elles que le Dossier
     imprime en calendrier et que la Feuille de route reprend.</p>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('planning')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="planning.dates">
  <input type="date" name="l[debut]" title="Début" required>
  <input type="date" name="l[fin]" title="Fin">
  <select name="l[phase]">
    <?php foreach (ProdFiche::PHASES as $k => $v): ?>
      <option value="<?= $k ?>" <?= $k === 'jeu' ? 'selected' : '' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="l[lieu]"    placeholder="Lieu" size="16">
  <input type="text" name="l[adresse]" placeholder="Adresse" size="18">
  <input type="text" name="l[ville]"   placeholder="Ville" size="10">
  <input type="text" name="l[pays]"    placeholder="Pays" size="8">
  <button type="submit">ajouter</button>
</form>
<?php endif; ?>

<h3 class="sep">Jours travaillés</h3>
<p class="aide">Entre le départ et le retour, cochez les jours effectivement travaillés.
   C'est ce nombre qui sert à la rémunération et aux défraiements, et il n'est pas
   toujours celui de la période.</p>

<form method="post" action="<?= e($lien('planning')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="champs">
  <label class="min">Départ <input type="date" name="v[planning.dateArrivee]"
    value="<?= e((string)$d['planning']['dateArrivee']) ?>" <?= $ecrit ? '' : 'readonly' ?>></label>
  <label class="min">Retour <input type="date" name="v[planning.dateRetour]"
    value="<?= e((string)$d['planning']['dateRetour']) ?>" <?= $ecrit ? '' : 'readonly' ?>></label>
  <?php if ($ecrit): ?><button type="submit">définir</button><?php endif; ?>
</form>

<?php
$a = (string)$d['planning']['dateArrivee'];
$r = (string)$d['planning']['dateRetour'];
if ($a !== '' && $r !== '' && $a <= $r):
    $JOUR = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    $MOIS = ['','jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'];
    $t = strtotime($a . ' 12:00:00'); $fin = strtotime($r . ' 12:00:00');
    /* 400 jours de garde: une faute de frappe dans une année produirait sinon
       une grille de plusieurs milliers de boutons. */
    $n = 0;
?>
  <p class="cpt"><strong><?= count($jours) ?></strong> jour<?= count($jours) > 1 ? 's' : '' ?> coché<?= count($jours) > 1 ? 's' : '' ?></p>
  <div class="grille">
  <?php while ($t <= $fin && $n++ < 400): $ds = date('Y-m-d', $t); $sel = in_array($ds, $jours, true); ?>
    <?php if ($ecrit): ?>
      <form method="post" action="<?= e($lien('planning')) ?>" class="inline">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="pf" value="jour">
        <input type="hidden" name="jour" value="<?= $ds ?>">
        <button type="submit" class="j <?= $sel ? 'on' : '' ?><?= in_array((int)date('w', $t), [0,6], true) ? ' we' : '' ?>">
          <span class="dd"><?= (int)date('j', $t) ?></span>
          <span class="dm"><?= $JOUR[(int)date('w', $t)] ?> <?= $MOIS[(int)date('n', $t)] ?></span>
        </button>
      </form>
    <?php else: ?>
      <span class="j <?= $sel ? 'on' : '' ?>"><span class="dd"><?= (int)date('j', $t) ?></span>
        <span class="dm"><?= $JOUR[(int)date('w', $t)] ?> <?= $MOIS[(int)date('n', $t)] ?></span></span>
    <?php endif; ?>
    <?php $t = strtotime('+1 day', $t); endwhile; ?>
  </div>
<?php else: ?>
  <p class="aide">Indiquez le départ et le retour pour voir la grille.</p>
<?php endif; ?>

<style>
.ph{font-size:11.5px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
.grille{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.j{display:inline-flex;flex-direction:column;align-items:center;min-width:46px;
  padding:5px 7px;border:1px solid var(--trait);border-radius:6px;background:var(--papier);
  color:var(--doux);cursor:pointer;font:inherit;line-height:1.15}
.j.on{background:var(--encre);color:var(--papier);border-color:var(--encre);font-weight:700}
.j.we{opacity:.62}
.j .dd{font-size:14px;font-weight:700}
.j .dm{font-size:10.5px}
.cpt{font-size:13px;color:var(--doux);margin:14px 0 0}
label.min{font-size:12.5px;color:var(--doux);display:inline-flex;align-items:center;gap:6px}
label.min input{width:auto}
</style>
