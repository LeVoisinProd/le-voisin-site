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

$tot = 0.0;
foreach ($d['remuneration'] as $r) $tot += (float)str_replace(',', '.', (string)($r['montant'] ?? 0));
?>
<?php /* LE NOMBRE DE JOURS SE SAISIT ICI, PERSONNE PAR PERSONNE.  [Anna, 22.08.2026]
     Il y avait au-dessus un bandeau qui reprenait le compte de la grille du
     Planning — « le Planning compte N jours travaillés, c'est le nombre à
     reprendre ci-dessous ». La grille est partie: elle donnait UN nombre pour
     toute la production, et le régisseur qui monte deux jours avant n'a pas
     fait les mêmes journées que l'interprète qui arrive la veille. */ ?>
<p class="aide top">Une ligne par personne et par période. Le nombre de jours est celui de cette
   personne-là — ce n'est pas forcément la durée de la période.</p>

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
  <?php /* ── LA LISTE DES PERSONNES ────────────────────────────────────────
       [Anna, 22.08.2026] « ainda não tem a listagem da parte remuneration ».
       C'était un champ libre: on retapait un nom que le Personnel connaît, et
       « Alessandra » écrite deux fois ne se rattache à personne — une feuille
       de route ne peut alors ni écrire à la personne ni la payer.

       LA LISTE SE PARCOURT PAR NOM ARTISTIQUE, MAIS C'EST LE NOM OFFICIEL QUI
       S'INSCRIT, et la distinction n'est pas un détail: cette ligne finit en
       contrat, en fiche de salaire et en déclaration AVS, et aucun de ces trois
       documents n'accepte un nom de scène. L'option montre donc « nom de scène
       — Prénom Nom » pour qu'on reconnaisse la personne, et dépose le nom
       d'état civil dans le champ. Le nom artistique, lui, sert au Dossier.

       La fonction se pré-remplit depuis la fiche, et seulement si le champ est
       vide: un rôle déjà écrit sur cette production ne se fait pas remplacer
       par le métier. */ ?>
  <input type="text" name="l[personne]" id="qRem" list="lRem" size="20"
         placeholder="Personne — chercher dans le personnel" autocomplete="off" required>
  <datalist id="lRem">
    <?php foreach (DB::all("SELECT prenom, nom, nom_artistique, fonction FROM rh_employe
                            WHERE supprime_le IS NULL AND actif = 1
                            ORDER BY prenom, nom") as $emp):
      $officiel = trim(((string)$emp['prenom']) . ' ' . ((string)$emp['nom']));
      if ($officiel === '') continue;
      $scene = trim((string)($emp['nom_artistique'] ?? '')); ?>
      <option value="<?= e($officiel) ?>"
              label="<?= e($scene !== '' ? $scene . ' — ' . $officiel : $officiel) ?>"
              data-f="<?= e((string)($emp['fonction'] ?? '')) ?>">
    <?php endforeach; ?>
  </datalist>
  <input type="text" name="l[fonction]" id="fRem" placeholder="Fonction" size="14">
  <select name="l[contrat]">
    <option value="">— contrat —</option>
    <option value="CH">Suisse — CDDU mensuel</option>
    <option value="FR-jour">France — CDDU journalier</option>
    <option value="FR-heure">France — CDDU horaire</option>
    <option value="facture">Facture</option>
  </select>
  <input type="text" name="l[periode]" placeholder="Période" size="14">
  <input type="text" name="l[jours]"   placeholder="Jours" size="5">
  <input type="text" name="l[montant]" placeholder="Montant" size="9">
  <select name="l[devise]"><option>CHF</option><option>EUR</option></select>
  <button type="submit">ajouter</button>
</form>
<script>
/* Sans JavaScript la liste propose encore et les deux champs se saisissent à la
   main: on perd le raccourci, pas la capacité. */
(function () {
  var q = document.getElementById('qRem'), f = document.getElementById('fRem');
  if (!q || !f) return;
  q.addEventListener('input', function () {
    if (f.value.trim() !== '') return;
    var v = q.value.trim(), o = null;
    document.querySelectorAll('#lRem option').forEach(function (x) { if (x.value === v) o = x; });
    if (o) f.value = o.getAttribute('data-f') || '';
  });
})();
</script>
<?php endif; ?>
