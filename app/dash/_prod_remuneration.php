<?php
/**
 * Onglet Rémunération. [16.08.2026]
 *
 * Une ligne par personne et par période. Le nombre de jours vient de la grille
 * du Planning quand elle est remplie, et c'est le point: on ne resaisit pas un
 * nombre que l'onglet d'à côté connaît déjà.
 *
 * IL NE CALCULE PAS LE SALAIRE. Les barèmes, les défraiements et les charges
 * vivent dans le dépôt de travail, et changent tous les ans. Ce que cet onglet
 * fait, c'est noter ce qui a été convenu, pour que la Feuille de route et le
 * Budget le reprennent sans qu'on ouvre un tableur.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */

$nJours = count($d['planning']['jours'] ?? []);
$tot = 0.0;
foreach ($d['remuneration'] as $r) $tot += (float)str_replace(',', '.', (string)($r['montant'] ?? 0));
?>
<?php if ($nJours > 0): ?>
  <p class="aide top">Le Planning compte <strong><?= $nJours ?></strong> jour<?= $nJours > 1 ? 's' : '' ?>
     travaillé<?= $nJours > 1 ? 's' : '' ?>. C'est le nombre à reprendre ci-dessous quand il s'applique.</p>
<?php else: ?>
  <p class="aide top">Aucun jour coché dans le Planning: le nombre de jours se saisit à la main
     ici, mais mieux vaut le poser là-bas — la Feuille de route et les défraiements s'en servent.</p>
<?php endif; ?>

<?php if ($d['remuneration']): ?>
  <div class="tbl"><table>
    <thead><tr><th>Personne</th><th>Fonction</th><th>Contrat</th><th>Période</th>
      <th class="d">Jours</th><th class="d">Montant</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['remuneration'] as $r): ?>
      <tr>
        <td><?= e((string)($r['personne'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['fonction'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['contrat'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['periode'] ?? '')) ?></td>
        <td class="d sec"><?= e((string)($r['jours'] ?? '')) ?></td>
        <td class="d"><?= ($r['montant'] ?? '') !== ''
            ? number_format((float)str_replace(',', '.', (string)$r['montant']), 2, ',', ' ')
              . ' ' . e((string)($r['devise'] ?? '')) : '' ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('remuneration')) ?>" class="inline"
                  onsubmit="return confirm('Supprimer cette ligne ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="remuneration">
              <input type="hidden" name="ligne" value="<?= e((string)($r['id'] ?? '')) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="5">Total</td>
      <td class="d"><strong><?= number_format($tot, 2, ',', ' ') ?></strong></td><td></td></tr></tfoot>
  </table></div>
<?php else: ?>
  <p class="aide">Aucune ligne.</p>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('remuneration')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="remuneration">
  <input type="text" name="l[personne]" placeholder="Personne" size="16" required>
  <input type="text" name="l[fonction]" placeholder="Fonction" size="14">
  <select name="l[contrat]">
    <option value="">— contrat —</option>
    <option value="CH">Suisse — CDDU mensuel</option>
    <option value="FR-jour">France — CDDU journalier</option>
    <option value="FR-heure">France — CDDU horaire</option>
    <option value="facture">Facture</option>
  </select>
  <input type="text" name="l[periode]" placeholder="Période" size="14">
  <input type="text" name="l[jours]"   placeholder="Jours" size="5"
         value="<?= $nJours > 0 ? $nJours : '' ?>">
  <input type="text" name="l[montant]" placeholder="Montant" size="9">
  <select name="l[devise]"><option>CHF</option><option>EUR</option></select>
  <button type="submit">ajouter</button>
</form>
<?php endif; ?>
