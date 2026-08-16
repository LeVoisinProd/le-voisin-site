<?php
/**
 * La barre des cinq onglets, et le CSS qui les fait marcher. [16.08.2026]
 *
 * EN CSS PUR, par des boutons radio cachés posés avant tout le reste. Pas de
 * JavaScript: un formulaire qui porte des numéros AVS et des références
 * fiscales doit se remplir même quand un script casse, et personne ne resaisit
 * cela deux fois de bonne humeur.
 *
 * Les radios sont ici plutôt que dans le formulaire pour que les sélecteurs
 * atteignent AUSSI les panneaux des grilles, qui sont des formulaires séparés
 * — le HTML interdit d'en imbriquer un dans un autre.
 */
declare(strict_types=1);

const ASSOC_ONGLETS = [
    'infos'  => 'Infos',
    'laa'    => 'LAA · LPP · AMPG',
    'avs'    => 'AVS',
    'is'     => 'Impôt Source',
    'idirect'=> 'Impôt Direct',
];

/** Les 26 cantons, pour les listes qui en demandent un. */
const CANTONS = ['AG','AI','AR','BE','BL','BS','FR','GE','GL','GR','JU','LU','NE','NW',
                 'OW','SG','SH','SO','SZ','TG','TI','UR','VD','VS','ZG','ZH'];

/**
 * Les délais de dépôt de la déclaration annuelle, par canton.
 *
 * Recopiés de l'écran du dashboard. Ce sont des dates INDICATIVES: des
 * prolongations sont presque toujours possibles, et l'écran le dit. Les mettre
 * en dur plutôt qu'en base est volontaire — ce sont des faits publics qui
 * bougent rarement, et une table qu'il faudrait remplir resterait vide.
 */
const DELAIS_CANTON = [
    'GE' => '31 mars', 'VD' => '15 mars', 'VS' => '31 mars', 'FR' => '31 mars',
    'NE' => '28 février', 'JU' => '28 février', 'BE' => '15 mars',
];
?>
<?php foreach (array_keys(ASSOC_ONGLETS) as $i => $k): ?>
  <input type="radio" name="ong" id="ong-<?= $k ?>" class="ongr" <?= $i === 0 ? 'checked' : '' ?>>
<?php endforeach; ?>

<div class="ongbar">
  <?php foreach (ASSOC_ONGLETS as $k => $lib): ?>
    <label for="ong-<?= $k ?>" class="ongl ongl-<?= $k ?>"><?= e($lib) ?></label>
  <?php endforeach; ?>
</div>

<style>
.ongr{position:absolute;opacity:0;pointer-events:none}
.ongbar{display:flex;gap:2px;margin:0 0 20px;border-bottom:1px solid var(--trait);
  overflow-x:auto}
.ongl{padding:9px 15px;font-size:13.5px;white-space:nowrap;cursor:pointer;
  color:var(--doux);border-bottom:2px solid transparent}
.ongl:hover{color:var(--encre)}
.pane{display:none}

<?php foreach (array_keys(ASSOC_ONGLETS) as $k): ?>
#ong-<?= $k ?>:checked ~ .ongbar .ongl-<?= $k ?>{color:var(--encre);font-weight:600;
  border-bottom-color:var(--jaune,#FFD24D)}
#ong-<?= $k ?>:checked ~ .pane-<?= $k ?>,
#ong-<?= $k ?>:checked ~ * .pane-<?= $k ?>{display:block}
<?php endforeach; ?>

.avis-b{background:var(--fond2);border-left:3px solid var(--jaune,#FFD24D);
  padding:11px 15px;margin:0 0 18px;font-size:13.5px;max-width:88ch}
.aide-b{font-size:12.5px;color:var(--doux);margin:0 0 14px;max-width:84ch}
.mdpbloc{margin:6px 0 20px}
.deux-cartes{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:24px}
.carte{border:1px solid var(--trait);border-radius:7px;padding:16px 18px}
.carte h4{margin:0 0 12px;font-size:12px;text-transform:uppercase;letter-spacing:.09em}
.carte.ch h4{color:#d0473a}
.carte.fr h4{color:#3a6fd0}
h4.sect-h{margin:26px 0 10px;font-size:14px}

.grl{margin-top:22px;padding-top:18px;border-top:1px solid var(--trait)}
.grl-nav{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:14px}
.grl-nav .an{display:inline-flex;align-items:center;justify-content:center;width:26px;
  height:26px;border:1px solid var(--trait);border-radius:50%;text-decoration:none;
  color:var(--doux)}
.grl-nav .an:hover{border-color:var(--encre);color:var(--encre)}
.grl-nav .sec{font-size:12.5px;color:var(--doux)}
.grl-t{font-size:13.5px;font-weight:600;margin-bottom:10px}
.grl-c{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px}
.cel-l{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--doux);
  text-align:center;margin-bottom:4px}
.cel-b{display:block;width:100%;padding:9px 6px;font:inherit;font-size:13px;text-align:center;
  border:1px solid var(--trait);border-radius:6px;background:var(--papier);
  color:var(--doux);cursor:pointer}
.cel-b:hover{border-color:var(--encre)}
.e-envoye{border-color:#d9a800;color:#8a6a00;font-weight:600}
.e-paye{border-color:#7bb33a;color:#4d7a1e;font-weight:600}
.e-sans_objet{opacity:.5}
@media (max-width:820px){.deux-cartes{grid-template-columns:1fr}}
</style>
