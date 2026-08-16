<?php
/**
 * Onglet Devis. [16.08.2026]
 *
 * IL NE REFAIT PAS LE CALCUL. Les devis de cession se calculent dans le dépôt
 * de travail, par la skill `/devis`, à partir des barèmes en vigueur, des
 * tarifs négociés par personne et de la grille de 1 à 6 représentations.
 * Refaire cette chaîne ici donnerait deux calculs qui divergeraient au premier
 * barème changé — et un barème change tous les ans.
 *
 * Ce que l'onglet fait: montrer les dates de ce spectacle et ce qu'elles
 * portent comme prix, pour qu'on voie d'un coup ce qui a été vendu et à
 * combien. C'est la question qu'on se pose en ouvrant « Devis » sur une fiche.
 */
declare(strict_types=1);
/** @var array $p */ /** @var int $pid */

$titre = trim((string)($p['title_fr'] ?: $p['title_en']));
$dates = $titre === '' ? [] : DB::all(
    "SELECT id, date_debut, date_texte, venue, ville, pays, prix_cession, devise, statut,
            representations
       FROM booking
      WHERE supprime_le IS NULL AND projet = ?
      ORDER BY COALESCE(date_debut,'9999-12-31')", [$titre]);

$tot = 0.0;
foreach ($dates as $x) $tot += (float)$x['prix_cession'];
?>
<p class="aide top">Le calcul d'un prix de cession vit dans le dépôt de travail, avec les
   barèmes en vigueur et les tarifs par personne: le refaire ici donnerait deux calculs qui
   divergeraient au premier barème changé. Cet onglet montre ce qui a été vendu.</p>

<?php if ($dates): ?>
  <div class="rap ok">
    <strong><?= count($dates) ?></strong> date<?= count($dates) > 1 ? 's' : '' ?> pour ce spectacle,
    <strong><?= number_format($tot, 2, ',', ' ') ?></strong> de prix de cession cumulé.
  </div>
  <div class="tbl"><table>
    <thead><tr><th>Date</th><th>Lieu</th><th>Représentations</th>
      <th class="d">Prix de cession</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($dates as $x): ?>
      <tr>
        <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$x['id'] ?>&amp;o=deal"><?=
          e($x['date_texte'] ?: (string)$x['date_debut']) ?></a></td>
        <td class="sec"><?= e((string)$x['venue']) ?><?php if ($x['ville']): ?>, <?= e((string)$x['ville']) ?><?php endif; ?></td>
        <td class="sec"><?= (int)$x['representations'] ?></td>
        <td class="d"><?= $x['prix_cession'] !== null
            ? number_format((float)$x['prix_cession'], 2, ',', ' ') . ' ' . e($x['devise']) : '—' ?></td>
        <td class="sec"><?= e((string)$x['statut']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="aide">Aucune date ne porte ce titre. L'appariement se fait sur le nom du
     spectacle: si les dates existent sous un autre libellé, elles n'apparaissent pas ici.</p>
<?php endif; ?>
