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

/* Les libellés, pour que le tableau ne montre pas « CH-jour ». `CH` sans suffixe
   est l'ancien code du mensuel suisse, gardé en lecture seule. */
const CONTRATS = [
    'CH-mois'  => 'Suisse — CDDU mensuel',
    'CH-jour'  => 'Suisse — CDDU journalier',
    'CH-heure' => 'Suisse — CDDU horaire',
    'CH'       => 'Suisse — CDDU mensuel',
    'FR-mois'  => 'France — CDDU mensuel',
    'FR-jour'  => 'France — CDDU journalier',
    'FR-heure' => 'France — CDDU horaire',
    'facture'  => 'Facture',
];
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */

$etapesJ = ProdFiche::joursDuPlanning($d);
$JSEM    = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
$MOISC   = ['','jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'];

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
      <?php $coches = array_filter(explode(',', (string)($r['jours_dates'] ?? ''))); ?>
      <tr>
        <td><?= e((string)($r['personne'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['fonction'] ?? '')) ?></td>
        <td class="sec"><?= e(CONTRATS[(string)($r['contrat'] ?? '')] ?? (string)($r['contrat'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($r['periode'] ?? '')) ?></td>
        <td class="d sec"><?= $coches ? count($coches) : e((string)($r['jours'] ?? '')) ?></td>
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
      <?php /* ── LES JOURNÉES DE CETTE PERSONNE-LÀ ────────────────────────────
           [Anna, 22.08.2026] « no dashboard já estava tudo automatizado: quando
           incluíamos uma pessoa ele já pré-preenchia as datas que estavam
           preenchidas no planning do projeto ».

           ELLES SONT SOUS LA LIGNE ET NON DANS UNE COLONNE: trois journées
           tiennent dans une case, quinze n'y tiennent pas, et une tournée en a
           quinze. Une seconde rangée les laisse respirer sans étirer le tableau.

           CHAQUE PERSONNE A SON FORMULAIRE. Un seul pour toute la page ferait
           repartir les cases de tout le monde à chaque enregistrement, et le
           dernier à écrire gagnerait — c'est le défaut qu'on a déjà payé sur les
           paquets du site. */ ?>
      <?php if ($ecrit && $etapesJ): ?>
      <tr class="rj">
        <td colspan="7">
          <form method="post" action="<?= e($lien('remuneration')) ?>" class="jrs">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="pf" value="liste_modifier">
            <input type="hidden" name="ou" value="remuneration">
            <input type="hidden" name="ligne" value="<?= e((string)($r['id'] ?? '')) ?>">
            <?php /* Une case décochée n'envoie rien: sans ce champ vide, tout
                 décocher n'enverrait pas `jours_dates` du tout et la ligne
                 garderait ses anciennes journées. */ ?>
            <input type="hidden" name="l[jours_dates][]" value="">
            <?php foreach ($etapesJ as $et): ?>
              <div class="jrs-e">
                <span class="jrs-t"><?= e($et['titre']) ?></span>
                <?php foreach ($et['jours'] as $j): $ts = strtotime($j); ?>
                  <label class="jr<?= in_array((int)date('w', $ts), [0,6], true) ? ' we' : '' ?>">
                    <input type="checkbox" name="l[jours_dates][]" value="<?= e($j) ?>"
                           <?= in_array($j, $coches, true) ? 'checked' : '' ?>>
                    <?= $JSEM[(int)date('w', $ts)] ?> <?= (int)date('j', $ts) ?> <?= $MOISC[(int)date('n', $ts)] ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <button type="submit" class="jrs-b">enregistrer les jours</button>
          </form>
        </td>
      </tr>
      <?php endif; ?>
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
  <?php /* ── LES SIX CONTRATS, TROIS PAR PAYS.  [Anna, 22.08.2026] ─────────────
       « incluir suisse cddu horaire et journalier », puis « incluir france
       mensuel tb ». La liste n'en offrait que quatre: un seul contrat suisse,
       le mensuel, et deux français. Elle était donc bancale des deux côtés — on
       ne pouvait pas noter une journée suisse ni un mois français, alors que
       les deux existent.

       LE CODE PORTE LE PAYS ET LA MAILLE, `CH-jour`, `FR-mois`: c'est ce qui
       permettra de brancher un barème dessus sans le deviner à la lecture d'un
       libellé. L'ancien `CH`, qui voulait dire mensuel, est reconnu en lecture —
       aucune ligne ne le porte aujourd'hui (mesuré: zéro ligne de rémunération
       en base), mais une écrite entre-temps ne doit pas s'afficher vide.

       `optgroup` ET NON SIX LIGNES À PLAT: la première question est le pays,
       parce que c'est lui qui décide des charges, du barème et de la caisse. */ ?>
  <select name="l[contrat]">
    <option value="">— contrat —</option>
    <optgroup label="Suisse">
      <option value="CH-mois">CDDU mensuel</option>
      <option value="CH-jour">CDDU journalier</option>
      <option value="CH-heure">CDDU horaire</option>
    </optgroup>
    <optgroup label="France">
      <option value="FR-mois">CDDU mensuel</option>
      <option value="FR-jour">CDDU journalier</option>
      <option value="FR-heure">CDDU horaire</option>
    </optgroup>
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

<style>
/* LES JOURNÉES D'UNE PERSONNE. [22.08.2026] Une rangée sous sa ligne, groupée
   par étape du Planning: la date seule ne dit pas si l'on parle de la résidence
   ou du jeu, et c'est justement ce qui change qui vient et qui ne vient pas. */
tr.rj > td{padding-top:0;padding-bottom:12px;border-bottom:1px solid var(--trait)}
form.jrs{display:flex;flex-direction:column;gap:8px;align-items:flex-start}
.jrs-e{display:flex;flex-wrap:wrap;gap:5px 10px;align-items:center}
.jrs-t{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--doux);
  margin-right:4px}
label.jr{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;
  padding:3px 8px;border:1px solid var(--trait);border-radius:14px;cursor:pointer;
  white-space:nowrap}
label.jr:has(input:checked){background:var(--encre);color:var(--papier);border-color:var(--encre)}
label.jr.we{opacity:.7}
label.jr input{margin:0;width:auto}
/* En contour et non en plein: cocher des cases n'est pas l'action principale de
   l'écran, et deux boutons noirs par personne feraient une page de boutons. */
button.jrs-b{margin-top:2px;padding:4px 11px;font-size:12px;font-weight:500;
  background:transparent;color:var(--doux);border:1px solid var(--trait)}
button.jrs-b:hover{color:var(--encre);border-color:var(--encre);opacity:1}
</style>
