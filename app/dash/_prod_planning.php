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
 *   LE DÉPART ET LE RETOUR — les deux dates du déplacement, que la feuille de
 *   route imprime.
 *
 * IL Y AVAIT UNE TROISIÈME CHOSE ICI, une grille d'un bouton par jour à cocher.
 * Elle est partie le 22.08.2026: le nombre de jours travaillés n'est pas le même
 * pour toute l'équipe, et il se saisit désormais ligne par ligne dans l'onglet
 * Rémunération.
 */
declare(strict_types=1);
/** @var array $d */ /** @var int $pid */ /** @var bool $ecrit */ /** @var callable $lien */

$etapes = $d['planning']['dates'] ?? [];
usort($etapes, fn($a, $b) => (string)($a['debut'] ?? '9999') <=> (string)($b['debut'] ?? '9999'));
$jours = $d['planning']['jours'] ?? [];
?>

<h3>Étapes de travail</h3>
<?php /* ── UNE ÉTAPE SE CORRIGE SUR PLACE.  [Anna, 22.08.2026] ─────────────────
     « uma vez que se coloca uma etapa não se pode mudar, só apagar e criar
     outra; por favor colocar a possibilidade de editar o que já foi incluído ».

     Elle a raison, et le coût de l'absence est plus grand qu'il n'en a l'air:
     une étape mal saisie porte des dates, et ces dates sont désormais les
     journées que chaque personne coche dans la Rémunération. La supprimer pour
     la refaire décoche donc tout le monde, et personne ne s'en aperçoit avant
     de compter des salaires.

     LE FORMULAIRE EST NOMMÉ, LES CHAMPS LE NOMMENT. Un `<form>` ne peut pas
     entourer les cellules d'une ligne de tableau — le HTML l'interdit — mais un
     champ qui porte `form="fEt-xxx"` lui appartient d'où qu'il soit. C'est le
     même mécanisme que la Synthèse, et il évite de casser le tableau en une
     pile de blocs.

     UN FORMULAIRE PAR LIGNE, jamais un pour toutes: un seul renverrait les sept
     champs de chaque étape à chaque enregistrement, et deux corrections faites
     à la suite se marcheraient dessus. */ ?>
<?php if ($etapes): ?>
  <div class="tbl"><table class="et">
    <thead><tr><th>Du</th><th>Au</th><th>Phase</th><th>Lieu</th><th>Adresse</th>
      <th>Ville</th><th>Pays</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($etapes as $r): $rid = (string)($r['id'] ?? ''); $f = 'fEt-' . $rid; ?>
      <tr>
        <?php if ($ecrit): ?>
          <td><input type="date" name="l[debut]" form="<?= e($f) ?>"
                     value="<?= e((string)($r['debut'] ?? '')) ?>"></td>
          <td><input type="date" name="l[fin]" form="<?= e($f) ?>"
                     value="<?= e((string)($r['fin'] ?? '')) ?>"></td>
          <td><select name="l[phase]" form="<?= e($f) ?>">
                <?php foreach (ProdFiche::PHASES as $k => $v): ?>
                  <option value="<?= $k ?>" <?= ($r['phase'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select></td>
          <?php foreach (['lieu' => 'Lieu', 'adresse' => 'Adresse',
                          'ville' => 'Ville', 'pays' => 'Pays'] as $c => $ph): ?>
            <td><input type="text" name="l[<?= $c ?>]" form="<?= e($f) ?>" placeholder="<?= e($ph) ?>"
                       value="<?= e((string)($r[$c] ?? '')) ?>"></td>
          <?php endforeach; ?>
          <td class="d et-act">
            <form method="post" action="<?= e($lien('planning')) ?>" id="<?= e($f) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_modifier">
              <input type="hidden" name="ou" value="planning.dates">
              <input type="hidden" name="ligne" value="<?= e($rid) ?>">
              <button type="submit" class="et-b">enregistrer</button>
            </form>
            <form method="post" action="<?= e($lien('planning')) ?>" class="inline"
                  onsubmit="return confirm('Supprimer cette étape ? Les journées cochées dans la Rémunération ne la retrouveront pas.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="planning.dates">
              <input type="hidden" name="ligne" value="<?= e($rid) ?>">
              <button type="submit" class="x">×</button>
            </form>
          </td>
        <?php else: ?>
          <td class="sec"><?= $r['debut'] ? e(date('d.m.Y', strtotime((string)$r['debut']))) : '—' ?></td>
          <td class="sec"><?= $r['fin'] ? e(date('d.m.Y', strtotime((string)$r['fin']))) : '' ?></td>
          <td><span class="ph"><?= e(ProdFiche::PHASES[$r['phase'] ?? ''] ?? '—') ?></span></td>
          <?php foreach (['lieu','adresse','ville','pays'] as $c): ?>
            <td class="sec"><?= e((string)($r[$c] ?? '')) ?></td>
          <?php endforeach; ?>
          <td></td>
        <?php endif; ?>
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

<?php /* LA GRILLE DES JOURS EST PARTIE D'ICI.  [Anna, 22.08.2026]
     « porque tem etapes de travail et jours travailles? na verdade os jours
     travailles será uma informação de cada pessoa na parte remuneração ».

     Elle a raison, et l'ancienne note en tête de ce fichier disait déjà le
     pourquoi sans en tirer la conséquence: ce nombre sert à la rémunération et
     aux défraiements. Or il n'est pas le même pour tout le monde — le régisseur
     monte deux jours avant l'arrivée des interprètes, et quelqu'un ne fait que
     la première. Un nombre unique pour toute la production était donc faux dès
     que l'équipe n'entre pas et ne sort pas ensemble.

     RIEN N'A ÉTÉ PERDU EN LA RETIRANT, et c'est mesuré et non supposé: aucun
     des 21 projets n'avait un seul jour coché. La colonne « Jours » de l'onglet
     Rémunération existait déjà, une par ligne, donc par personne.

     LES DATES D'ARRIVÉE ET DE RETOUR RESTENT, et ce n'est pas un oubli:
     `_prod_imprimer.php` les imprime dans la feuille de route, et une équipe a
     bien un départ commun même quand ses journées diffèrent. */ ?>

<?php /* « DÉPART ET RETOUR » A ÉTÉ RETIRÉ.  [Anna, 22.08.2026]
     « tirar a parte depart et retour, é um doublon, não tem porque estar ali ».

     Elle a raison, et je l'avais gardé la veille en croyant protéger la feuille
     de route, qui les imprime. Mesuré avant de les retirer: AUCUN des 21 projets
     n'a jamais rempli ces deux champs. Le bloc imprimé était donc toujours vide,
     et je protégeais une ligne qui n'a jamais existé.

     Les étapes, juste au-dessus, portent déjà des dates de début et de fin. La
     feuille de route en tire maintenant la fenêtre du déplacement — la première
     date et la dernière — au lieu de demander de les retaper. Deux endroits pour
     la même chose, c'est deux endroits qui divergent.

     Les clefs `dateArrivee` et `dateRetour` restent dans le modèle, vides, et ne
     sont plus ni lues ni écrites. */ ?>

<style>
/* LE TABLEAU DES ÉTAPES EST SAISISSABLE. [22.08.2026] Les champs prennent la
   largeur de leur colonne et perdent le cadre épais des formulaires: on lit
   d'abord un tableau, et on corrige au passage. Le cadre revient au survol et à
   la saisie, pour qu'on voie ce qu'on touche. */
table.et td{padding:4px 8px 4px 0;vertical-align:middle}
table.et input,table.et select{width:100%;min-width:0;box-sizing:border-box;
  padding:5px 7px;font:inherit;font-size:13px;border:1px solid transparent;
  border-radius:4px;background:transparent;color:var(--encre)}
table.et input:hover,table.et select:hover{border-color:var(--trait)}
table.et input:focus,table.et select:focus{border-color:var(--encre);
  background:var(--papier);outline:none}
table.et td.et-act{white-space:nowrap;width:1%}
button.et-b{padding:4px 10px;font-size:12px;font-weight:500;background:transparent;
  color:var(--doux);border:1px solid var(--trait);border-radius:4px;cursor:pointer;
  font-family:inherit;margin:0 4px 0 0}
button.et-b:hover{color:var(--encre);border-color:var(--encre)}

.ph{font-size:11.5px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
label.min{font-size:12.5px;color:var(--doux);display:inline-flex;align-items:center;gap:6px}
label.min input{width:auto}
</style>
