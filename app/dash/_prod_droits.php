<?php
/**
 * Onglet Droits d'auteur. [16.08.2026]
 *
 * LE PARTAGE DOIT FAIRE 100 %, et l'écran le dit tant que ce n'est pas le cas.
 * C'est la seule vérification qui compte ici: une déclaration SSA ou SACD avec
 * un total à 90 % est refusée, et on s'en aperçoit des mois plus tard, au
 * moment où les droits devaient tomber.
 *
 * Les collaborateurs — `cols` dans le modèle du dashboard — sont ceux qui ont
 * contribué sans être auteurs déclarés. Ils ne comptent pas dans les 100 %,
 * mais ils figurent, parce qu'une contribution oubliée se réclame ensuite.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */

$total = ProdFiche::droitsTotal($d);
$ok = abs($total - 100.0) < 0.01;
?>
<h3>Partage des droits</h3>

<?php if ($d['droits']['auteurs']): ?>
  <div class="rap <?= $ok ? 'ok' : 'ecart' ?>">
    Total réparti: <strong><?= rtrim(rtrim(number_format($total, 2, ',', ' '), '0'), ',') ?> %</strong>.
    <?= $ok ? 'Le partage est complet.'
            : ($total < 100 ? 'Il manque ' . rtrim(rtrim(number_format(100 - $total, 2, ',', ' '), '0'), ',') . ' %.'
                            : 'Le partage dépasse 100 % de ' . rtrim(rtrim(number_format($total - 100, 2, ',', ' '), '0'), ',') . ' %.') ?>
    Une déclaration qui ne fait pas exactement 100 % est refusée.
  </div>

  <div class="tbl"><table>
    <thead><tr><th>Auteur</th><th>Rôle</th><th>Société</th><th class="d">Part</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['droits']['auteurs'] as $a): ?>
      <tr>
        <td><?= e((string)($a['nom'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($a['role'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($a['societe'] ?? '')) ?></td>
        <td class="d"><strong><?= e((string)($a['part'] ?? '0')) ?> %</strong></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('droits')) ?>" class="inline"
                  onsubmit="return confirm('Retirer cet auteur ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="droits.auteurs">
              <input type="hidden" name="ligne" value="<?= e((string)($a['id'] ?? '')) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="aide">Aucun auteur déclaré.</p>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('droits')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="droits.auteurs">
  <input type="text" name="l[nom]"     placeholder="Nom" size="18" required>
  <input type="text" name="l[role]"    placeholder="Rôle (MES, texte, musique…)" size="20">
  <input type="text" name="l[societe]" placeholder="Société (SSA, SACD…)" size="14">
  <input type="text" name="l[part]"    placeholder="Part %" size="6" required>
  <button type="submit">ajouter</button>
</form>
<?php endif; ?>

<h3 class="sep">Collaborateurs</h3>
<p class="aide">Ceux qui ont contribué sans être auteurs déclarés. Ils ne comptent pas dans
   les 100 %, mais ils figurent: une contribution oubliée se réclame ensuite.</p>

<?php if ($d['droits']['cols']): ?>
  <div class="tbl"><table>
    <thead><tr><th>Nom</th><th>Contribution</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['droits']['cols'] as $c): ?>
      <tr>
        <td><?= e((string)($c['nom'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($c['contribution'] ?? '')) ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('droits')) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="droits.cols">
              <input type="hidden" name="ligne" value="<?= e((string)($c['id'] ?? '')) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($lien('droits')) ?>" class="ajl">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="droits.cols">
  <input type="text" name="l[nom]"          placeholder="Nom" size="18" required>
  <input type="text" name="l[contribution]" placeholder="Contribution" size="28">
  <button type="submit">ajouter</button>
</form>
<?php endif; ?>

<form method="post" action="<?= e($lien('droits')) ?>" class="sep2">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="champs">
  <div class="ch">
    <label for="c-droits-editeur">Éditeur</label>
    <input type="text" id="c-droits-editeur" name="v[droits.editeur]"
           value="<?= e((string)$d['droits']['editeur']) ?>" <?= $ecrit ? '' : 'readonly' ?>>
  </div>
  <div class="ch">
    <label for="c-droits-repartition">Règle de répartition</label>
    <textarea id="c-droits-repartition" name="v[droits.repartition]" rows="3" <?= $ecrit ? '' : 'readonly' ?>><?= e((string)$d['droits']['repartition']) ?></textarea>
  </div>
  <div class="ch">
    <label for="c-droits-notes">Notes</label>
    <textarea id="c-droits-notes" name="v[droits.notes]" rows="3" <?= $ecrit ? '' : 'readonly' ?>><?= e((string)$d['droits']['notes']) ?></textarea>
  </div>
  <?php if ($ecrit): ?><button type="submit">Enregistrer</button><?php endif; ?>
</form>

<style>form.sep2{margin-top:24px;padding-top:20px;border-top:1px solid var(--trait)}</style>

<?php /* ── LA DÉCLARATION SSA ────────────────────────────────────────────────
     [16.08.2026] Elle est ici et pas dans un onglet à part: on remplit le
     partage des droits et on déclare dans le même mouvement. Un onglet de plus
     ferait oublier l'un des deux, et c'est toujours la déclaration qu'on
     oublie. */ ?>
<?php require __DIR__ . '/_prod_ssa.php'; ?>
