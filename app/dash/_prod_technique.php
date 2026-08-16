<?php
/**
 * Onglet Fiche technique. [16.08.2026]
 *
 * CE QU'IL RÉPOND, ET C'EST LA PREMIÈRE QUESTION QU'UN LIEU POSE. « Ça rentre
 * chez nous ? » Ouverture, profondeur, hauteur, temps de montage, nombre de
 * personnes. Aujourd'hui la réponse est dans un PDF sur le Drive, et le PDF est
 * daté: on répond de mémoire, ou on va le chercher.
 *
 * TROIS GROUPES PARCE QU'ILS SE REMPLISSENT À TROIS MOMENTS. Le plateau se fixe
 * à la création et ne bouge plus. Les temps se mesurent au premier montage et
 * s'affinent. Les besoins changent à chaque configuration — la version salle et
 * la version extérieur n'ont pas les mêmes. Un seul long formulaire ferait
 * relire cinquante champs pour en corriger un.
 *
 * AUCUN CHAMP N'EST OBLIGATOIRE, et c'est un choix. Une fiche à moitié remplie
 * et envoyée vaut mieux qu'une fiche complète jamais finie: le lieu à qui il
 * manque une information la demande, le lieu qui n'a rien reçu suppose.
 *
 * LES VERSIONS SONT UNE LISTE ET NON UN CHAMP. Une fiche technique n'a pas de
 * valeur sans sa date: celle de 2024 a fait déplacer un camion pour rien. On
 * garde donc la suite — version, date, lien — la plus récente en haut, et les
 * anciennes restent lisibles. Le fichier lui-même vit sur le Drive; ce qui est
 * ici est le lien et la date, pas une deuxième copie qui divergerait.
 *
 * CE QU'IL NE FAIT PAS: générer le PDF. Le site n'a aucune bibliothèque PDF
 * (vérifié le 16.08: ni FPDF, ni TCPDF, ni Dompdf), et l'impression du
 * navigateur suffit — c'est déjà ce que font la Feuille de route et le relevé.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */
/** @var callable $champ */ /** @var int $pid */

$t   = $d['technique'] ?? [];
$val = static fn(string $groupe, string $cle): string => (string)($t[$groupe][$cle] ?? '');
?>

<form method="post" action="<?= e($lien('technique')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="champs">

  <div class="deux">
    <div class="bl">
      <h3>Le plateau</h3>
      <p class="aide">Ce qui décide si le spectacle entre dans la salle. Se fixe à la
         création et ne bouge presque plus.</p>
      <?php foreach (ProdFiche::TECH_PLATEAU as $k => [$lib, $aide]): ?>
        <?= $champ("technique.plateau.$k", $lib, $val('plateau', $k), $aide) ?>
      <?php endforeach; ?>
    </div>

    <div class="bl">
      <h3>Les temps</h3>
      <p class="aide">Ce qui décide du planning du lieu, et donc du nombre de services
         qu'il doit payer. Se mesure au premier montage.</p>
      <?php foreach (ProdFiche::TECH_TEMPS as $k => [$lib, $aide]): ?>
        <?= $champ("technique.temps.$k", $lib, $val('temps', $k), $aide) ?>
      <?php endforeach; ?>
    </div>
  </div>

  <h3 class="sep">Les besoins</h3>
  <p class="aide">Ce qui change d'une configuration à l'autre. La version salle et la
     version extérieur n'ont pas les mêmes, et c'est ici qu'on le dit.</p>
  <div class="deux">
    <div class="bl">
      <?php $i = 0; foreach (ProdFiche::TECH_BESOINS as $k => [$lib, $aide]):
        if ($i++ === 5) echo '</div><div class="bl">'; ?>
        <?= $champ("technique.besoins.$k", $lib, $val('besoins', $k), $aide,
                   in_array($k, ['lumiere','son','video','loges','lieuEquipe'], true) ? 3 : 0) ?>
      <?php endforeach; ?>
    </div>
  </div>

  <h3 class="sep">Adaptations et contact</h3>
  <div class="deux">
    <div class="bl">
      <?= $champ('technique.adaptations', 'Adaptations possibles',
                 (string)($t['adaptations'] ?? ''),
                 'Version réduite, extérieur, hors les murs, scolaire. Ce qui se négocie '
               . 'plutôt que de refuser une date.', 4) ?>
      <?= $champ('technique.notes', 'Notes internes',
                 (string)($t['notes'] ?? ''),
                 'Ce qui ne part pas au lieu: ce qui a coincé la dernière fois, '
               . 'les salles où ça ne rentre pas.', 3) ?>
    </div>
    <div class="bl">
      <h4>Qui répond aux questions techniques</h4>
      <p class="aide">Le lieu écrit directement à cette personne. Sans elle, tout passe
         par le bureau et se perd d'un jour.</p>
      <?= $champ('technique.contact.nom',   'Nom',           (string)($t['contact']['nom']   ?? '')) ?>
      <?= $champ('technique.contact.role',  'Rôle',          (string)($t['contact']['role']  ?? ''),
                 'régie générale, création lumière, direction technique') ?>
      <?= $champ('technique.contact.email', 'Courriel',      (string)($t['contact']['email'] ?? '')) ?>
      <?= $champ('technique.contact.tel',   'Téléphone',     (string)($t['contact']['tel']   ?? '')) ?>
    </div>
  </div>

  <?php if ($ecrit): ?><div class="act"><button type="submit">Enregistrer</button></div><?php endif; ?>
</form>

<h3 class="sep">Les versions du document</h3>
<p class="aide">Une fiche technique sans sa date fait déplacer un camion pour rien. Le
   fichier reste sur le Drive — ici on garde le lien et la date, pas une deuxième copie
   qui finirait par diverger.</p>

<?php $versions = $t['versions'] ?? [];
usort($versions, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''))); ?>

<?php if ($versions): ?>
  <div class="tbl"><table>
    <thead><tr><th>Version</th><th>Date</th><th>Configuration</th><th>Lien</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $i => $v): ?>
      <tr<?= $i === 0 ? ' class="courante"' : '' ?>>
        <td><?= e((string)($v['version'] ?? '')) ?><?= $i === 0 ? ' <span class="pastille">à jour</span>' : '' ?></td>
        <td class="sec"><?= e((string)($v['date'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($v['config'] ?? '')) ?></td>
        <td><?php $u = trim((string)($v['lien'] ?? '')); ?>
          <?php if ($u !== ''): ?>
            <a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer">ouvrir</a>
          <?php else: ?><span class="sec">—</span><?php endif; ?></td>
        <td class="d">
          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('technique')) ?>" class="ligne-sup">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_retirer">
              <input type="hidden" name="ou" value="technique.versions">
              <input type="hidden" name="id" value="<?= e((string)($v['id'] ?? '')) ?>">
              <button type="submit" class="lien-sup">retirer</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <p class="vide">Aucune version enregistrée. La plus récente sera marquée « à jour ».</p>
<?php endif; ?>

<?php if ($ecrit): ?>
  <form method="post" action="<?= e($lien('technique')) ?>" class="ajl">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="liste_ajouter">
    <input type="hidden" name="ou" value="technique.versions">
    <input type="text" name="l[version]" placeholder="Version — v3, 2026" size="16">
    <input type="date" name="l[date]">
    <input type="text" name="l[config]" placeholder="Configuration — salle, extérieur, scolaire" size="26">
    <input type="url"  name="l[lien]" placeholder="Lien vers le fichier sur le Drive" size="34">
    <button type="submit">Ajouter</button>
  </form>
<?php endif; ?>

<style>
.courante td{font-weight:600}
.pastille{display:inline-block;margin-left:7px;padding:1px 7px;font-size:10.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.07em;border:1px solid var(--trait);border-radius:9px;
  color:var(--doux)}
</style>
