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

/* LES NEUF FONCTIONS DE LA SSA, AVEC LEUR CODE.  [Anna, 22.08.2026]
   Elles étaient saisies à la main dans un champ libre, et la légende sous la
   déclaration les rappelait en petit — donc chacun écrivait « MES », « mise en
   scène » ou « metteuse en scène » selon le jour. La déclaration officielle,
   elle, n'accepte que le code. */
$ROLES = [
    'A'   => 'Autrice/Auteur du texte original',
    'MES' => 'Metteur·euse en scène',
    'CH'  => 'Chorégraphe',
    'C'   => 'Compositrice/Compositeur',
    'AT'  => 'Traductrice/Traducteur',
    'AA'  => 'Adaptatrice/Adaptateur',
    'ALI' => 'Livret',
    'AAR' => 'Argument',
    'E'   => 'Édition',
];
/* Les quatre cartes ouvertes d'emblée sont les quatre fonctions qu'on déclare
   presque toujours. Chaque carte garde la liste entière: aucune n'est enfermée
   dans son rôle. */
$CARTES = ['A', 'MES', 'CH', 'C'];
?>
<h3>Partage des droits — <?= rtrim(rtrim(number_format($total, 2, ',', ' '), '0'), ',') ?> %</h3>

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
        <td class="sec"><?php $r = (string)($a['role'] ?? '');
             echo e(isset($ROLES[$r]) ? $r . ' — ' . $ROLES[$r] : $r); ?></td>
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
<?php /* ── QUATRE CARTES CÔTE À CÔTE ────────────────────────────────────────
     [Anna, 22.08.2026] « na página droits auteur: seguir a proporção da mise en
     page dessa imagem ». Une carte par fonction, le rôle en tête, la personne
     en dessous, puis la société, la part et le +.

     C'ÉTAIT UNE SEULE LIGNE DE QUATRE CHAMPS LIBRES, et elle demandait de
     retaper à chaque fois ce que le dashboard savait déjà: le nom de la
     personne, qui est dans l'équipe du projet, et le code de la fonction, qui
     est une liste fermée à la SSA. On tapait donc « mise en scène » là où la
     déclaration attend « MES », et l'écart ne se voyait qu'au moment de
     remplir le formulaire officiel.

     LA PERSONNE SE PROPOSE SANS S'IMPOSER, comme dans l'équipe: c'est un champ
     libre adossé à la liste. Un auteur du texte n'est pas toujours dans
     l'équipe du projet — un texte d'un auteur mort ne l'est jamais — et fermer
     la liste aurait rendu ces déclarations-là impossibles. */ ?>
<div class="dr-cartes">
  <?php foreach ($CARTES as $i => $defaut): ?>
    <form method="post" action="<?= e($lien('droits')) ?>" class="dr-c">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="pf" value="liste_ajouter">
      <input type="hidden" name="ou" value="droits.auteurs">
      <select name="l[role]" aria-label="Fonction">
        <?php foreach ($ROLES as $k => $lib): ?>
          <option value="<?= e($k) ?>" <?= $k === $defaut ? 'selected' : '' ?>>(<?= e($k) ?>) <?= e($lib) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="dr-a">Personne — choisis dans l'équipe ou écris le nom</p>
      <input type="text" name="l[nom]" list="lDroits<?= (int)$i ?>" placeholder="— Équipe —"
             autocomplete="off" required>
      <datalist id="lDroits<?= (int)$i ?>">
        <?php foreach (($d['equipe'] ?? []) as $m):
          $n = trim(((string)($m['prenom'] ?? '')) . ' ' . ((string)($m['nom'] ?? '')));
          if ($n === '') continue; ?>
          <option value="<?= e($n) ?>">
        <?php endforeach; ?>
      </datalist>
      <div class="dr-b">
        <input type="text" name="l[societe]" placeholder="Société (SSA, SACD…)">
        <input type="text" name="l[part]" placeholder="%" inputmode="decimal" required>
        <button type="submit" title="Ajouter">+</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>
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

<style>
form.sep2{margin-top:24px;padding-top:20px;border-top:1px solid var(--trait)}

/* QUATRE CARTES QUI SE PLIENT, ET NON QUATRE COLONNES FIXES. Le dashboard se
   regarde aussi sur un écran étroit, et une grille figée à quatre y produirait
   des champs de six caractères. `auto-fit` en garde autant que la largeur en
   permet et repasse à la ligne pour le reste. */
.dr-cartes{display:grid;grid-template-columns:repeat(auto-fit,minmax(238px,1fr));
  gap:14px;margin:16px 0 4px}
.dr-c{border:1px solid var(--trait);border-radius:8px;padding:12px;background:var(--fond2);
  display:flex;flex-direction:column;gap:8px}
/* `flex:0 0 auto` N'EST PAS DE LA PRÉCAUTION, C'EST UNE CORRECTION. Une règle
   générale du dashboard pose `input[type="text"]{flex:1 1 240px}` pour les
   lignes d'ajout, qui sont horizontales. Ici la carte est une colonne, et une
   base de 240 px s'y applique donc à la HAUTEUR: mesuré, le champ du nom faisait
   240 px de haut et la carte 338. */
.dr-c select,.dr-c input{width:100%;flex:0 0 auto;padding:7px 9px;font:inherit;font-size:13px;
  border:1px solid var(--trait);border-radius:5px;background:var(--papier);
  color:var(--encre);box-sizing:border-box}
.dr-c select{font-weight:600}
.dr-a{margin:0;font-size:11.5px;color:var(--doux)}
/* La société prend ce qui reste, la part juste ses trois chiffres, le + rien de
   plus que lui-même: c'est la proportion du modèle. */
.dr-b{display:flex;gap:6px;align-items:center}
.dr-b input[name="l[societe]"]{flex:1;min-width:0}
/* `min-width:0` SUR LES DEUX, ET NON SUR LE SEUL PREMIER. Un `input` a une
   largeur minimale automatique d'une vingtaine de caractères, et elle l'emporte
   sur la base de 52 px: mesuré, le champ des pour-cent occupait 180 px et
   écrasait celui de la société à 68. */
.dr-b input[name="l[part]"]{flex:0 0 52px;min-width:0;text-align:right}
.dr-b button[type=submit]{flex:0 0 auto;margin-top:0;padding:7px 12px;font-size:14px;
  line-height:1;background:var(--jaune,#FFD24D);color:var(--encre)}
</style>

<?php /* ── LA DÉCLARATION SSA ────────────────────────────────────────────────
     [16.08.2026] Elle est ici et pas dans un onglet à part: on remplit le
     partage des droits et on déclare dans le même mouvement. Un onglet de plus
     ferait oublier l'un des deux, et c'est toujours la déclaration qu'on
     oublie. */ ?>
<?php require __DIR__ . '/_prod_ssa.php'; ?>
