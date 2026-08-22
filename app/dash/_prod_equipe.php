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
  <div class="tbl eq"><table>
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
<?php /* ── LE NOM SE CHOISIT DANS LE PERSONNEL ─────────────────────────────────
     [16.08.2026] Anna: « na parte Équipe du projet, no campo prenom + nom —
     puxar da lista de emplo ». Les 49 collaborateurs ont une fiche, avec leur
     e-mail, leur IBAN et leur AVS; les retaper à la main ici produisait des
     « Alessandra » qui ne se rattachaient à personne, et une feuille de route
     ne peut alors ni écrire à la personne ni la payer.

     C'EST UN `datalist` ET NON UN `select` FERMÉ, et c'est délibéré: une partie
     de l'équipe n'a pas de fiche chez nous — un régisseur du lieu, un stagiaire,
     quelqu'un qu'on rencontre la semaine d'avant — et fermer la liste
     empêcherait de noter qui joue. On propose, on n'impose pas.

     Le nom complet est découpé au premier espace: `collaborators.name` porte
     « Alessandra Souto Domingues » d'un seul tenant, et le prénom est ce qui
     précède. Un nom composé part donc du bon côté, et les deux champs restent
     modifiables après. */ ?>
<form method="post" action="<?= e($lien('synthese')) ?>" class="ajl eq-aj" id="fEquipe">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="equipe">
  <input type="text" name="qui" id="qEquipe" list="lEquipe" size="26"
         placeholder="Chercher dans le personnel" autocomplete="off">
  <?php /* LA LISTE VIENT DU PERSONNEL, ET NON DES COMPTES DE L'ESPACE.
       [Anna, 22.08.2026] « a fonction tem que estar pré-preenchida com a
       profissão da pessoa que está na ficha pessoal dela ».

       `collaborators` porte les comptes en ligne — cinquante-six — et ne sait
       pas ce que les gens font. `rh_employe` porte les quatre-vingt-onze
       personnes du Personnel avec leur fonction, remplie sur quatre-vingt-
       trois. C'est la seule source qui contient ce qu'Anna demande, et c'est
       aussi celle qu'elle tient à jour.

       LA FONCTION VOYAGE DANS L'OPTION, en `data-f`: rappeler le serveur pour
       une information qu'on avait déjà au moment d'écrire la page serait un
       aller-retour pour rien. */ ?>
  <datalist id="lEquipe">
    <?php foreach (DB::all("SELECT prenom, nom, fonction FROM rh_employe
                            WHERE supprime_le IS NULL AND actif = 1
                            ORDER BY prenom, nom") as $c):
      $nc = trim(((string)$c['prenom']) . ' ' . ((string)$c['nom']));
      if ($nc === '') continue; ?>
      <option value="<?= e($nc) ?>" data-f="<?= e((string)($c['fonction'] ?? '')) ?>">
    <?php endforeach; ?>
  </datalist>
  <input type="text" name="l[prenom]"   id="pEquipe" placeholder="Prénom" size="12">
  <input type="text" name="l[nom]"      id="nEquipe" placeholder="Nom" size="14" required>
  <input type="text" name="l[fonction]" id="fEquipeF" placeholder="Fonction" size="20">
  <button type="submit">ajouter</button>
</form>
<script>
/* Le choix remplit les deux champs, qui restent modifiables. Sans JavaScript
   la recherche ne sert à rien mais les deux champs marchent comme avant: on ne
   perd pas la capacité de saisir, seulement le raccourci. */
(function () {
  var q = document.getElementById('qEquipe');
  if (!q) return;
  q.addEventListener('input', function () {
    var v = q.value.trim();
    if (v === '') return;
    var i = v.indexOf(' ');
    if (i < 1) return;
    document.getElementById('pEquipe').value = v.slice(0, i);
    document.getElementById('nEquipe').value = v.slice(i + 1);

    /* LA FONCTION SE PROPOSE, ELLE NE S'IMPOSE PAS. On ne l'écrit que si le
       champ est vide: quelqu'un qui a déjà tapé « régie son » sur cette
       production ne doit pas se le faire remplacer par « technicien », qui est
       son métier mais pas son rôle ici. */
    var f = document.getElementById('fEquipeF');
    var o = null;
    document.querySelectorAll('#lEquipe option').forEach(function (x) {
      if (x.value === v) o = x;
    });
    if (f && o && f.value.trim() === '') f.value = o.getAttribute('data-f') || '';
  });
})();
</script>
<?php endif; ?>
