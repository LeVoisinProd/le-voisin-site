<?php
/**
 * Les trois listes à cocher d'une fiche de contact. [16.08.2026]
 *
 * Participations, mois envisagés, directions artistiques liées. Elles étaient
 * jusqu'ici des champs de texte libre, ou n'existaient pas du tout.
 *
 * POURQUOI DES BOUTONS ET PAS DU TEXTE, et ce n'est pas un choix d'esthétique.
 * Mesuré le 16.08.2026 sur les 8432 fiches: la colonne `region`, laissée libre,
 * porte « Île-de-France », « Ile-de-France » et « ILE DE FRANCE » comme trois
 * valeurs distinctes. Une liste qu'on tape se salit toujours, et une liste sale
 * ne se filtre plus — c'est-à-dire qu'elle ne sert plus à ce pour quoi on la
 * remplit: retrouver à qui écrire.
 *
 * Les participations, elles, sont restées propres: douze valeurs distinctes sur
 * 2798 fiches remplies. On garde donc ces douze, et on laisse ajouter les
 * suivantes — une liste fermée obligerait à une migration à chaque nouveau
 * festival, et le prochain arrive toujours.
 *
 * LE FORMAT ENREGISTRÉ RESTE CELUI DU DASHBOARD: une chaîne séparée par des
 * virgules. La reprise depuis lv-contacts doit rester sans perte, et l'écran
 * qui coche écrit exactement ce qu'un import relirait.
 */
declare(strict_types=1);
/** @var callable $v */

/** Les douze participations relevées dans les 8432 fiches, les plus portées d'abord. */
const CONTACT_PARTICIPATIONS = [
    'Chalon 2024', 'Jeune public', 'Carnet diffusion', 'Santarcangelo Festival 2024',
    'Avignon 2026', 'Swiss Dance Days 2022', 'Prospection 2026', "Cours d'école",
    'Bestiarium 2016-2022', "Salon d'artistes 2024", 'Rectum Crocodile', 'Grand-Est FR',
];

const CONTACT_MOIS = ['Janvier','Février','Mars','Avril','Mai','Juin',
                      'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

/** Ce qui est coché, depuis la chaîne enregistrée. */
$coches = static function (string $champ) use ($v): array {
    $s = trim((string)$v($champ));
    if ($s === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $s)), fn($x) => $x !== ''));
};

$pCoches = $coches('participations');
$mCoches = $coches('date_mois');
$dCoches = $coches('directions');

/* Les directions artistiques du roster. Lues des dates déjà saisies plutôt que
   d'une liste écrite en dur: le roster bouge, et une liste en dur vieillit
   sans que personne ne s'en aperçoive. */
$directions = DB::all("SELECT DISTINCT artiste FROM booking
                       WHERE supprime_le IS NULL AND artiste IS NOT NULL AND artiste <> ''
                       ORDER BY artiste");
$dNoms = array_values(array_unique(array_merge(
    array_map(fn($r) => (string)$r['artiste'], $directions), $dCoches)));

/** Un groupe de boutons à cocher, qui écrit une chaîne à virgules. */
$groupe = function (string $champ, string $titre, string $aide, array $choix, array $actifs)
                    use (&$groupe): void { ?>
  <div class="lb">
    <div class="lb-t"><?= e($titre) ?></div>
    <p class="lb-a"><?= e($aide) ?></p>
    <div class="lb-g" data-champ="<?= e($champ) ?>">
      <?php foreach ($choix as $c): $on = in_array($c, $actifs, true); ?>
        <label class="pil <?= $on ? 'on' : '' ?>">
          <input type="checkbox" name="<?= e($champ) ?>_c[]" value="<?= e($c) ?>" <?= $on ? 'checked' : '' ?>>
          <?= e($c) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
<?php };
?>

<div class="listes">
  <?php
  $groupe('participations', 'Participations et rencontres professionnelles',
          'Où ce contact a été rencontré, ou ce qu\'il suit. C\'est ce qui permet de dire, '
        . 'avant d\'écrire, si l\'on s\'est déjà parlé.',
          CONTACT_PARTICIPATIONS, $pCoches);
  ?>
  <div class="lb-plus">
    <label for="participations_libre">Autre participation</label>
    <input type="text" id="participations_libre" name="participations_libre"
           placeholder="Un festival, une rencontre — séparez par des virgules">
    <p class="lb-a">Ce qui s'ajoute ici rejoint la liste ci-dessus pour ce contact.
       Les douze proposées sont celles relevées dans les 8432 fiches existantes.</p>
  </div>

  <?php
  $groupe('date_mois', 'Mois envisagés ou confirmés',
          'Quand ce contact programme, ou quand il a dit être disponible.',
          CONTACT_MOIS, $mCoches);
  ?>
  <div class="lb-plus">
    <label for="date_notes">Précisions sur les dates</label>
    <textarea id="date_notes" name="date_notes" rows="3"
      placeholder="Années, périodes exactes, disponibilités, contraintes de saison…"><?= e((string)$v('date_notes')) ?></textarea>
  </div>

  <?php
  $groupe('directions', 'Directions artistiques liées',
          'Ce contact peut être intéressé par ces spectacles. C\'est le champ qui transforme '
        . 'un carnet d\'adresses en outil de diffusion: on cherche à qui proposer une pièce, '
        . 'pas qui est programmateur.',
          $dNoms, $dCoches);
  ?>
</div>

<style>
.listes{margin:6px 0 4px;display:flex;flex-direction:column;gap:22px}
.lb-t{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;
  color:var(--doux);margin-bottom:3px}
.lb-a{font-size:12.5px;color:var(--doux);margin:0 0 9px;max-width:76ch}
.lb-g{display:flex;flex-wrap:wrap;gap:6px}
.pil{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;font-size:13px;
  border:1px solid var(--trait);border-radius:14px;cursor:pointer;background:var(--papier);
  color:var(--doux);user-select:none}
.pil input{margin:0;width:13px;height:13px;cursor:pointer}
.pil.on,.pil:has(input:checked){border-color:var(--encre);color:var(--encre);font-weight:600}
.pil:hover{border-color:var(--encre)}
.lb-plus{margin-top:-8px}
.lb-plus label{display:block;font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.09em;color:var(--doux);margin-bottom:4px}
.lb-plus input,.lb-plus textarea{width:100%;max-width:640px;padding:8px 10px;font:inherit;
  font-size:14px;border:1px solid var(--trait);border-radius:5px;background:var(--papier);
  color:var(--encre);box-sizing:border-box}
.lb-plus textarea{resize:vertical}
</style>
