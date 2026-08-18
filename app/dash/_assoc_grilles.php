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

  <?php /* ── L'ATTESTATION D'AFFILIATION AU DEUXIÈME PILIER ──────── [18.08.2026]
       Anna: « colocar um campo attestation d'affiliation année en cours (…)
       deixar espaço para se escolher ano e depositar a atestação em pdf ».

       CE N'EST PAS UN ÉTAT, C'EST UNE PIÈCE. La fiche dit déjà si la LPP est
       souscrite; ça, c'est le PDF que la caisse émet chaque année et qu'on
       redemande à chaque dossier de subvention et à chaque contrôle. Une case
       à cocher ne le ressort pas quand on en a besoin.

       UNE PAR AN, ET LES ANCIENNES RESTENT. Un contrôle porte sur l'exercice
       qu'il contrôle, pas sur l'année en cours: écraser 2025 en déposant 2026
       ferait perdre la seule preuve de 2025. Déposer deux fois LA MÊME année,
       en revanche, remplace — on ne corrige pas une attestation, on en reçoit
       une meilleure, et deux versions de la même année laisseraient quelqu'un
       choisir la mauvaise devant un contrôle. */ ?>
  <?php $lpp = OrgPieces::liste($id, 'lpp_affiliation'); $anCour = OrgPieces::anneeDefaut(); ?>
  <div class="grille-h">
    <h4>Attestation d’affiliation LPP</h4>
    <p class="aide-b">Attestation d’affiliation à une institution de prévoyance du deuxième
       pilier, émise chaque année par la caisse. Une par exercice — les anciennes restent.</p>

    <?php if (!OrgPieces::dispo()): ?>
      <p class="aide-b alerte">La table des pièces manque encore. Lancer <code>php db/migrer.php</code>.</p>
    <?php else: ?>

    <?php if ($lpp): ?>
    <div class="tbl"><table>
      <thead><tr><th>Année</th><th>Fichier</th><th>Note</th><th>Déposée</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lpp as $pc): ?>
        <tr<?= (int)$pc['annee'] === $anCour ? ' class="ici"' : '' ?>>
          <td><strong><?= (int)$pc['annee'] ?></strong><?= (int)$pc['annee'] === $anCour ? ' <span class="sec">année en cours</span>' : '' ?></td>
          <td><a href="/dashboard.php?e=associations&amp;piece_dl=<?= (int)$pc['id'] ?>"><?=
              e((string)$pc['fichier']) ?></a>
            <span class="sec"><?= number_format((int)$pc['taille'] / 1024, 0, ',', ' ') ?> Ko</span></td>
          <td class="sec"><?= e((string)$pc['note']) ?></td>
          <td class="sec"><?= e(substr((string)$pc['cree_le'], 0, 10)) ?><?php
              if ($pc['depose_par']): ?> · <?= e((string)$pc['depose_par']) ?><?php endif; ?></td>
          <td class="d">
            <?php if ($ecrit): ?>
            <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1"
                  class="inline-form" onsubmit="return confirm('Retirer l’attestation <?= (int)$pc['annee'] ?> ? Le fichier est supprimé.')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="piece" value="retirer">
              <input type="hidden" name="ligne" value="<?= (int)$pc['id'] ?>">
              <button type="submit" class="x">retirer</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php else: ?>
      <p class="aide-b">Aucune attestation déposée.</p>
    <?php endif; ?>

    <?php if ($ecrit): ?>
    <form method="post" action="/dashboard.php?e=associations&amp;o=<?= $id ?>&amp;mod=1"
          enctype="multipart/form-data" class="ajl piece-f">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="piece" value="deposer">
      <input type="hidden" name="type" value="lpp_affiliation">
      <label>Année
        <select name="annee">
          <?php for ($a = $anCour + 1; $a >= $anCour - 6; $a--): ?>
            <option value="<?= $a ?>"<?= $a === $anCour ? ' selected' : '' ?>><?= $a ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <label class="fic">Fichier
        <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png" required></label>
      <label class="nt">Note
        <input type="text" name="note" maxlength="300" placeholder="Caisse, n° de contrat…"></label>
      <button type="submit">déposer</button>
    </form>
    <p class="aide-b">PDF, JPG ou PNG, 25 Mo au maximum. Le fichier est rangé hors du web —
       il porte un numéro de contrat de prévoyance et ne doit pas être servi par adresse.</p>
    <?php endif; ?>

    <?php endif; ?>
  </div>
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
