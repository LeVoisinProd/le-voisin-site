<?php
/**
 * Onglet Budget. [16.08.2026]
 *
 * Dépenses et recettes dans la même liste, séparées par une colonne `sens`.
 * C'est ce que fait le dashboard, et c'est le bon choix ici: un budget de
 * spectacle se lit en une fois, pas en deux tableaux qu'on additionne de tête.
 *
 * LE SOLDE EST AFFICHÉ MÊME QUAND IL EST NÉGATIF, et surtout à ce moment-là.
 * Un budget qui ne montre que le total des dépenses laisse croire que le
 * financement suit.
 *
 * CE N'EST PAS LA COMPTABILITÉ. Les écritures réelles vivent dans Bexio et
 * Banana; ceci est le budget prévisionnel, celui qu'on met dans un dossier.
 * Les confondre ferait deux comptabilités dont aucune ne serait juste.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */

$T = ProdFiche::budgetTotaux($d);
$NAT = ProdFiche::BUDGET_DEPENSE + ProdFiche::BUDGET_RECETTE;
?>
<p class="aide top">Le budget prévisionnel, celui qui va dans un dossier. Les écritures
   réelles restent dans Bexio et Banana: les confondre ferait deux comptabilités dont
   aucune ne serait juste.</p>

<?php if ($d['budget']): ?>
  <div class="rap <?= $T['solde'] < 0 ? 'ecart' : 'ok' ?>">
    Dépenses <strong><?= number_format($T['depenses'], 2, ',', ' ') ?></strong> ·
    Recettes <strong><?= number_format($T['recettes'], 2, ',', ' ') ?></strong> ·
    Solde <strong><?= number_format($T['solde'], 2, ',', ' ') ?></strong>.
    <?= $T['solde'] < 0
        ? 'Il manque ' . number_format(abs($T['solde']), 2, ',', ' ') . ' pour équilibrer.'
        : 'Le budget est équilibré ou excédentaire.' ?>
  </div>

  <div class="tbl"><table>
    <thead><tr><th>Nature</th><th>Libellé</th><th>Sens</th>
      <th class="d">Montant</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['budget'] as $b): $rec = ($b['sens'] ?? 'depense') === 'recette'; ?>
      <tr class="<?= $rec ? 'rec' : '' ?>">
        <td><?= e($NAT[$b['nature'] ?? ''] ?? '—') ?></td>
        <td class="sec"><?= e((string)($b['libelle'] ?? '')) ?></td>
        <td class="sec"><?= $rec ? 'recette' : 'dépense' ?></td>
        <td class="d"><?= $rec ? '+' : '−' ?><?=
          number_format((float)str_replace(',', '.', (string)($b['montant'] ?? 0)), 2, ',', ' ') ?>
          <?= e((string)($b['devise'] ?? '')) ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('budget')) ?>" class="inline"
                  onsubmit="return confirm('Supprimer cette ligne ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="budget">
              <input type="hidden" name="ligne" value="<?= e((string)($b['id'] ?? '')) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="aide">Aucune ligne de budget.</p>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('budget')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="budget">
  <select name="l[sens]">
    <option value="depense">dépense</option>
    <option value="recette">recette</option>
  </select>
  <select name="l[nature]">
    <optgroup label="Dépenses">
      <?php foreach (ProdFiche::BUDGET_DEPENSE as $k => $v): ?>
        <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
    </optgroup>
    <optgroup label="Recettes">
      <?php foreach (ProdFiche::BUDGET_RECETTE as $k => $v): ?>
        <option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
    </optgroup>
  </select>
  <input type="text" name="l[libelle]" placeholder="Libellé" size="24">
  <input type="text" name="l[montant]" placeholder="Montant" size="9" required>
  <select name="l[devise]"><option>CHF</option><option>EUR</option></select>
  <button type="submit">ajouter</button>
</form>
<p class="aide">Le sens et la nature se choisissent séparément: rien n'empêche de noter
   une « cession » en dépense, parce que cela arrive — une coproduction qu'on paie.</p>
<?php endif; ?>

<style>tr.rec td{background:var(--fond2)}</style>
