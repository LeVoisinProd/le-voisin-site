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
<?php if ($etapes): ?>
  <div class="tbl"><table>
    <thead><tr><th>Du</th><th>Au</th><th>Phase</th><th>Lieu</th><th>Ville</th><th>Pays</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($etapes as $r): $rid = (string)($r['id'] ?? ''); ?>
      <tr>
        <td class="sec"><?= $r['debut'] ? e(date('d.m.Y', strtotime((string)$r['debut']))) : '—' ?></td>
        <td class="sec"><?= $r['fin'] ? e(date('d.m.Y', strtotime((string)$r['fin']))) : '' ?></td>
        <td><span class="ph"><?= e(ProdFiche::PHASES[$r['phase'] ?? ''] ?? '—') ?></span></td>
        <td class="sec"><?= e((string)($r['lieu'] ?? '')) ?>
          <?php if ($r['adresse'] ?? ''): ?><br><span class="pt"><?= e((string)$r['adresse']) ?></span><?php endif; ?></td>
        <td class="sec"><?= e((string)($r['ville'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['pays'] ?? '')) ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('planning')) ?>" class="inline"
                  onsubmit="return confirm('Supprimer cette étape ?')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="planning.dates">
              <input type="hidden" name="ligne" value="<?= e($rid) ?>">
              <button type="submit" class="x">×</button>
            </form>
          <?php endif; ?>
        </td>
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
.ph{font-size:11.5px;padding:2px 8px;border-radius:10px;border:1px solid var(--trait);white-space:nowrap}
label.min{font-size:12.5px;color:var(--doux);display:inline-flex;align-items:center;gap:6px}
label.min input{width:auto}
</style>
