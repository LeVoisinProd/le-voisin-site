<?php
/**
 * Onglet Équipe, montré à l'intérieur de la Synthèse. [16.08.2026]
 *
 * Il est là et pas dans la barre parce que le dashboard le montre ainsi: on
 * regarde qui fait le spectacle en même temps qu'on regarde ce qu'il est, pas
 * dans un onglet à part.
 *
 * `empId` relie à une fiche de collaborateur quand elle existe. Le nom reste
 * saisi en clair à côté: une partie de l'équipe n'a pas de fiche chez nous, et
 * exiger le lien empêcherait de noter qui joue.
 */
declare(strict_types=1);
/** @var array $d */ /** @var int $pid */ /** @var bool $ecrit */ /** @var callable $lien */
?>
<?php if ($d['equipe']): ?>
  <div class="tbl"><table>
    <thead><tr><th>Prénom</th><th>Nom</th><th>Fonction</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['equipe'] as $m): ?>
      <tr>
        <td><?= e((string)($m['prenom'] ?? '')) ?></td>
        <td><?= e((string)($m['nom'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($m['fonction'] ?? '')) ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('synthese')) ?>" class="inline"
                  onsubmit="return confirm('Retirer cette personne de l\'équipe ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="equipe">
              <input type="hidden" name="ligne" value="<?= e((string)($m['id'] ?? '')) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="aide">Personne pour l'instant.</p>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('synthese')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="equipe">
  <input type="text" name="l[prenom]"   placeholder="Prénom" size="12">
  <input type="text" name="l[nom]"      placeholder="Nom" size="14" required>
  <input type="text" name="l[fonction]" placeholder="Fonction" size="20">
  <button type="submit">ajouter</button>
</form>
<?php endif; ?>
