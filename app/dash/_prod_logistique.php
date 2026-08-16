<?php
/**
 * Onglet Logistique. [16.08.2026]
 *
 * Quatre volets, ceux du dashboard: voyages, hébergement, repas, transports.
 *
 * POURQUOI QUATRE LISTES ET PAS UNE AVEC UNE COLONNE « type ». Parce qu'on ne
 * les remplit pas au même moment ni avec les mêmes têtes: les voyages se
 * réservent des mois avant, les repas se comptent la semaine d'avant. Une
 * liste unique obligerait à filtrer pour faire n'importe lequel des deux
 * gestes, et à voir les trois autres pendant qu'on en fait un.
 *
 * Chaque ligne partage les mêmes champs, et c'est ce qui permet à la Feuille de
 * route de les imprimer toutes de la même façon.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */
?>
<?php foreach (ProdFiche::LOGI as $cle => $lib):
  $lignes = $d['logistique'][$cle] ?? []; ?>

  <h3<?= $cle === 'voyages' ? '' : ' class="sep"' ?>><?= e($lib) ?></h3>

  <?php if ($lignes): ?>
    <div class="tbl"><table>
      <thead><tr><th>Quand</th><th>Qui</th><th>Quoi</th><th>De</th><th>À</th>
        <th>Référence</th><th class="d">Montant</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lignes as $l): ?>
        <tr>
          <td class="sec"><?= e((string)($l['quand'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['qui'] ?? '')) ?></td>
          <td><?= e((string)($l['libelle'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['depart'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['arrivee'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['reference'] ?? '')) ?></td>
          <td class="d"><?= ($l['montant'] ?? '') !== ''
              ? e((string)$l['montant']) . ' ' . e((string)($l['devise'] ?? '')) : '' ?></td>
          <td class="d">
            <?php if ($ecrit): ?>
              <form method="post" action="<?= e($lien('logistique')) ?>" class="inline"
                    onsubmit="return confirm('Supprimer cette ligne ?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="pf" value="liste_retirer">
                <input type="hidden" name="ou" value="logistique.<?= e($cle) ?>">
                <input type="hidden" name="ligne" value="<?= e((string)($l['id'] ?? '')) ?>">
                <button type="submit" class="x">×</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="aide">Rien pour l'instant.</p>
  <?php endif; ?>

  <?php if ($ecrit): ?>
  <form method="post" action="<?= e($lien('logistique')) ?>" class="ajl">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="liste_ajouter">
    <input type="hidden" name="ou" value="logistique.<?= e($cle) ?>">
    <input type="text" name="l[quand]"     placeholder="Quand" size="12">
    <input type="text" name="l[qui]"       placeholder="Qui" size="12">
    <input type="text" name="l[libelle]"   placeholder="Quoi" size="18" required>
    <input type="text" name="l[depart]"    placeholder="De" size="9">
    <input type="text" name="l[arrivee]"   placeholder="À" size="9">
    <input type="text" name="l[reference]" placeholder="Référence" size="10">
    <input type="text" name="l[montant]"   placeholder="Montant" size="8">
    <select name="l[devise]"><option>CHF</option><option>EUR</option></select>
    <button type="submit">ajouter</button>
  </form>
  <?php endif; ?>
<?php endforeach; ?>
