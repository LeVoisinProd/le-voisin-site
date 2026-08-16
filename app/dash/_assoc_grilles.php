<?php
/**
 * Les comptes d'impôt à la source et les grilles de déclaration. [16.08.2026]
 *
 * Séparés du grand formulaire parce que ce sont des LIGNES et non des champs,
 * et que le HTML interdit d'imbriquer un formulaire dans un autre. Ils vivent
 * dans les mêmes panneaux d'onglets: les boutons radio sont posés avant les
 * deux, donc le CSS les atteint tous.
 *
 * LES GRILLES NE CRÉENT UNE LIGNE QUE SI L'ON CLIQUE. Remplir d'avance quatre
 * trimestres par an et par association ferait des milliers de « rien à
 * signaler » qu'il faudrait ensuite distinguer de ce qui veut dire quelque
 * chose.
 *
 * Attend $id, $ecrit, $annee.
 */
declare(strict_types=1);
/** @var int $id */ /** @var bool $ecrit */ /** @var int $annee */

$ETATS_D = ['a_faire'=>'à faire','envoye'=>'envoyé','paye'=>'payé','sans_objet'=>'sans objet'];
$SUIV    = ['a_faire'=>'envoye','envoye'=>'paye','paye'=>'sans_objet','sans_objet'=>'a_faire'];

$decl = [];
if ($id > 0) {
    foreach (DB::all('SELECT * FROM organisation_declaration WHERE organisation_id = ? AND annee = ?',
                     [$id, $annee]) as $d) {
        $decl[$d['type']][$d['periode']] = $d;
    }
}

/** Une grille d'une année, pour un type de déclaration. */
$grille = function (string $type, string $titre, array $periodes)
                    use ($id, $ecrit, $annee, $decl, $ETATS_D, $SUIV): void { ?>
  <div class="grl">
    <div class="grl-nav">
      <a class="an" href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1&amp;an=<?= $annee - 1 ?>">&lsaquo;</a>
      <strong><?= $annee ?></strong>
      <a class="an" href="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1&amp;an=<?= $annee + 1 ?>">&rsaquo;</a>
      <span class="sec"><?= $ecrit ? 'Cliquez sur une case pour changer le statut' : '' ?></span>
    </div>
    <div class="grl-t"><?= e($titre) ?> — <?= $annee ?></div>
    <div class="grl-c">
      <?php foreach ($periodes as $p): $d = $decl[$type][$p] ?? null;
            $st = (string)($d['statut'] ?? 'a_faire'); ?>
        <div class="cel">
          <div class="cel-l"><?= e($p) ?></div>
          <?php if ($ecrit): ?>
            <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1&amp;an=<?= $annee ?>">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="decl" value="1">
              <input type="hidden" name="type" value="<?= e($type) ?>">
              <input type="hidden" name="periode" value="<?= e($p) ?>">
              <input type="hidden" name="annee" value="<?= $annee ?>">
              <input type="hidden" name="statut" value="<?= e($SUIV[$st]) ?>">
              <button type="submit" class="cel-b e-<?= e($st) ?>"><?= $d ? e($ETATS_D[$st]) : '—' ?></button>
            </form>
          <?php else: ?>
            <span class="cel-b e-<?= e($st) ?>"><?= $d ? e($ETATS_D[$st]) : '—' ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php };
?>

<div class="pane pane-laa">
  <?php $grille('laa', 'Déclaration trimestrielle LAA · LPP · AMPG', ['T1','T2','T3','T4']); ?>
</div>

<div class="pane pane-avs">
  <?php $grille('avs', 'Déclaration AVS', ['T1','T2','T3','T4','annuel']); ?>
</div>

<div class="pane pane-is">
  <?php $comptes = $id > 0
        ? DB::all('SELECT * FROM organisation_is WHERE organisation_id = ? ORDER BY canton', [$id]) : []; ?>
  <?php if ($comptes): ?>
    <div class="tbl"><table>
      <thead><tr><th>Canton</th><th>N° de compte / DPI</th><th>Notes</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($comptes as $c): ?>
        <tr>
          <td><strong><?= e((string)$c['canton']) ?></strong></td>
          <td class="sec"><?= e((string)($c['compte'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($c['notes'] ?? '')) ?></td>
          <td class="d">
            <?php if ($ecrit): ?>
              <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1"
                    onsubmit="return confirm('Retirer ce compte cantonal ?')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="is_act" value="retirer">
                <input type="hidden" name="ligne" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="x">×</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="aide-b">Aucun compte IS enregistré pour cette association.</p>
  <?php endif; ?>

  <?php if ($ecrit && $id > 0): ?>
    <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1" class="ajl">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="is_act" value="ajouter">
      <select name="canton" required>
        <option value="">— Canton —</option>
        <?php foreach (CANTONS as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="compte" placeholder="N° attribué par le canton" size="24">
      <input type="text" name="notes" placeholder="Notes" size="20">
      <button type="submit">ajouter un compte canton</button>
    </form>
  <?php elseif ($ecrit): ?>
    <p class="aide-b">Enregistrez d'abord la fiche: un compte cantonal s'attache à une
       association qui existe.</p>
  <?php endif; ?>
</div>
