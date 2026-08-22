<?php
/**
 * Onglet Logistique — refait d'après le modèle d'Anna.  [22.08.2026]
 *
 * « na parte logistica corrigir de acordo com a foto para cada item: viagem,
 * hébergement, repas. »
 *
 * TROIS CHANGEMENTS, ET LE PREMIER COMMANDE LES DEUX AUTRES.
 *
 *   1. LES QUATRE VOLETS DEVIENNENT DES ONGLETS et non quatre sections
 *      empilées. On ne remplit pas les voyages et les repas au même moment —
 *      les vols se réservent des mois avant, les repas se comptent la semaine
 *      d'avant — et les trois autres listes n'ont rien à faire sous les yeux
 *      pendant qu'on en remplit une. L'ancienne note de ce fichier disait déjà
 *      cela pour justifier quatre listes; elle n'en tirait pas la conséquence.
 *
 *   2. À L'INTÉRIEUR D'UN ONGLET, UNE CARTE PAR PERSONNE. C'est le modèle
 *      d'Anna, et c'est la façon dont la question se pose réellement: on ne
 *      demande pas « qui prend ce train », on demande « comment vient Vincent ».
 *      Le nom cesse donc d'être un champ à choisir à chaque ligne — il est
 *      l'en-tête de la carte, et le formulaire de la carte le pose tout seul.
 *
 *   3. LE « QUOI » D'UN VOYAGE EST UNE LISTE FERMÉE. Train, avion, voiture,
 *      car, bateau. C'était un champ libre, et un champ libre sur un mot aussi
 *      court produit « train », « Train », « TGV » et « SBB » pour la même
 *      chose. Les trois autres volets gardent le texte libre: un hébergement
 *      s'écrit, il ne se choisit pas.
 *
 * LES CLEFS DE DONNÉES NE CHANGENT PAS — `quand`, `qui`, `libelle`, `depart`,
 * `arrivee`, `reference`, `montant`, `devise`. La Feuille de route et le
 * document imprimé les lisent telles quelles, et rien ne justifie de les casser
 * pour une mise en page. `reference` porte ce que le modèle appelle « Note »:
 * l'étiquette dit les deux, parce qu'un numéro de réservation et une remarque
 * finissent dans la même case.
 *
 * MESURÉ AVANT DE REFAIRE: les quatre listes sont vides sur les 21 projets.
 * Rien à reprendre, rien à casser.
 *
 * Attend $d, $ecrit, $lien.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */

/* Les modes d'un voyage. Liste courte: ce qui n'y est pas s'écrit dans la note. */
$MODES = ['Train', 'Avion', 'Voiture', 'Car', 'Bateau', 'Autre'];

/* Les gens de la production, dans l'ordre où ils y sont entrés. Une carte
   chacun. La carte « Sans personne » recueille ce qui n'est attaché à personne —
   un décor qui voyage, un repas d'équipe — et ce qui porte un nom qui n'est plus
   dans l'équipe: on ne fait pas disparaître une ligne parce que quelqu'un a
   quitté le projet. */
$gens = [];
foreach (($d['equipe'] ?? []) as $m) {
    $n = trim(((string)($m['prenom'] ?? '')) . ' ' . ((string)($m['nom'] ?? '')));
    if ($n !== '') $gens[$n] = (string)($m['fonction'] ?? '');
}

$etapesLg = $d['planning']['dates'] ?? [];

/** L'étiquette lisible d'une étape: « 12.04.2027 – 15.04.2027 · Jeu · Vidy, Lausanne ». */
$libEtape = static function (array $et): string {
    $f = static fn($x) => ($t = strtotime((string)$x)) ? date('d.m.Y', $t) : (string)$x;
    $a = (string)($et['debut'] ?? '');
    $b = (string)($et['fin'] ?? '');
    $dates = ($a === '' && $b === '') ? '' : (($b === '' || $b === $a) ? $f($a) : $f($a) . ' – ' . $f($b));
    return trim(implode(' · ', array_filter([
        $dates,
        ProdFiche::PHASES[$et['phase'] ?? ''] ?? (string)($et['phase'] ?? ''),
        trim(((string)($et['lieu'] ?? '')) . (($et['ville'] ?? '') ? ', ' . $et['ville'] : ''), ', '),
    ])));
};
?>

<div class="lg">
  <?php /* Des boutons radio et du CSS: les onglets tiennent sans une ligne de
       JavaScript et sans recharger la page. Même mécanisme que la fiche
       association. Ils sont écrits AVANT la barre et les panneaux, parce que le
       sélecteur `~` ne regarde que ce qui suit. */ ?>
  <?php foreach (array_keys(ProdFiche::LOGI) as $i => $cle): ?>
    <input type="radio" name="lgOng" id="lg-<?= e($cle) ?>" class="lg-r" <?= $i === 0 ? 'checked' : '' ?>>
  <?php endforeach; ?>

  <div class="lg-bar">
    <?php foreach (ProdFiche::LOGI as $cle => $lib): $nb = count($d['logistique'][$cle] ?? []); ?>
      <label for="lg-<?= e($cle) ?>" class="lgl lgl-<?= e($cle) ?>"><?= e($lib) ?>
        <?php if ($nb): ?><span class="lg-n"><?= $nb ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>
  </div>

  <?php foreach (ProdFiche::LOGI as $cle => $lib):
      $lignes = $d['logistique'][$cle] ?? [];

      /* On range les lignes par personne. Ce qui ne correspond à personne de
         l'équipe tombe dans la carte commune, sous la clef vide. */
      $par = [];
      foreach ($gens as $n => $_f) $par[$n] = [];
      $par[''] = [];
      foreach ($lignes as $l) {
          $q = trim((string)($l['qui'] ?? ''));
          $par[($q !== '' && isset($par[$q])) ? $q : ''][] = $l;
      }
      $sing = rtrim(mb_strtolower($lib), 's');
  ?>
    <div class="lg-pane lg-pane-<?= e($cle) ?>">
      <?php foreach ($par as $nom => $siennes):
          /* La carte commune ne s'affiche que si elle sert à quelque chose, ou
             s'il n'y a personne dans l'équipe — sinon elle serait la seule et
             l'écran n'aurait aucun endroit où saisir. */
          if ($nom === '' && !$siennes && $gens) continue; ?>
        <section class="lg-c">
          <div class="lg-t">
            <span class="lg-nom"><?= $nom === '' ? 'Sans personne' : e($nom) ?></span>
            <?php if ($nom !== '' && ($gens[$nom] ?? '') !== ''): ?>
              <span class="lg-f"><?= e($gens[$nom]) ?></span>
            <?php endif; ?>
            <span class="lg-cpt"><?= count($siennes) ?> <?= e($sing) ?><?= count($siennes) > 1 ? 's' : '' ?></span>
          </div>

          <?php if ($siennes): ?>
            <ul class="lg-l">
              <?php foreach ($siennes as $l): ?>
                <li>
                  <span class="lg-q"><?= e((string)($l['quand'] ?? '')) ?: '—' ?></span>
                  <span class="lg-quoi"><?= e((string)($l['libelle'] ?? '')) ?></span>
                  <?php $trajet = trim(((string)($l['depart'] ?? ''))
                        . ((($l['depart'] ?? '') !== '' && ($l['arrivee'] ?? '') !== '') ? ' → ' : '')
                        . ((string)($l['arrivee'] ?? ''))); ?>
                  <?php if ($trajet !== ''): ?><span class="lg-tr"><?= e($trajet) ?></span><?php endif; ?>
                  <?php if (($l['reference'] ?? '') !== ''): ?>
                    <span class="lg-no"><?= e((string)$l['reference']) ?></span>
                  <?php endif; ?>
                  <span class="lg-m"><?= ($l['montant'] ?? '') !== ''
                      ? e((string)$l['montant']) . ' ' . e((string)($l['devise'] ?? '')) : '' ?></span>
                  <?php if ($ecrit): ?>
                    <form method="post" action="<?= e($lien('logistique')) ?>" class="inline"
                          onsubmit="return confirm('Supprimer cette ligne ?')">
                      <?= Auth::csrfField() ?>
                      <input type="hidden" name="pf" value="liste_retirer">
                      <input type="hidden" name="ou" value="logistique.<?= e($cle) ?>">
                      <input type="hidden" name="ligne" value="<?= e((string)($l['id'] ?? '')) ?>">
                      <button type="submit" class="x">×</button>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="lg-vide">Aucun <?= e($sing) ?>.</p>
          <?php endif; ?>

          <?php if ($ecrit): ?>
            <form method="post" action="<?= e($lien('logistique')) ?>" class="lg-aj">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="pf" value="liste_ajouter">
              <input type="hidden" name="ou" value="logistique.<?= e($cle) ?>">
              <?php /* LE NOM EST POSÉ PAR LA CARTE, plus choisi à chaque ligne.
                   Sur la carte commune il reste saisissable: c'est là qu'on note
                   un chauffeur ou quelqu'un du lieu. */ ?>
              <?php if ($nom !== ''): ?>
                <input type="hidden" name="l[qui]" value="<?= e($nom) ?>">
              <?php endif; ?>

              <div class="lg-r1">
                <?php if ($etapesLg): ?>
                  <select name="l[quand]">
                    <option value="">— étape du planning —</option>
                    <?php foreach ($etapesLg as $et): $lb = $libEtape($et); ?>
                      <option value="<?= e($lb) ?>"><?= e($lb) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>
                <?php /* Ou une date précise: un vol tombe la veille du départ,
                     hors de toute étape. Les deux arrivent, donc les deux sont
                     là — `projets.php` garde celle qui est remplie. */ ?>
                <input type="date" name="l[quand_date]" title="Ou une date précise">
                <?php if ($cle === 'voyages'): ?>
                  <select name="l[libelle]">
                    <?php foreach ($MODES as $md): ?><option><?= e($md) ?></option><?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <input type="text" name="l[libelle]" placeholder="Quoi" required>
                <?php endif; ?>
              </div>

              <div class="lg-r2">
                <input type="text" name="l[depart]"  placeholder="De">
                <input type="text" name="l[arrivee]" placeholder="À">
                <?php if ($nom === ''): ?>
                  <input type="text" name="l[qui]" placeholder="Qui (facultatif)">
                <?php endif; ?>
              </div>

              <div class="lg-r3">
                <input type="text" name="l[montant]" placeholder="Montant" class="lg-mt">
                <select name="l[devise]" class="lg-dv"><option>CHF</option><option>EUR</option></select>
                <input type="text" name="l[reference]" placeholder="Note ou référence" class="lg-nt">
                <button type="submit" class="lg-b">+ Ajouter</button>
              </div>
            </form>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

<style>
/* ── LES ONGLETS ───────────────────────────────────────────────────────────
   Les boutons radio sont sortis du cadre plutôt que masqués par `display:none`:
   masqués, ils quitteraient l'ordre de tabulation et l'écran deviendrait
   impraticable sans souris. */
.lg-r{position:absolute;left:-9999px}
.lg-bar{display:flex;flex-wrap:wrap;gap:8px;margin:2px 0 18px}
.lgl{padding:7px 15px;border:1px solid var(--trait);border-radius:20px;font-size:13.5px;
  cursor:pointer;color:var(--doux);background:var(--papier);white-space:nowrap;
  display:inline-flex;align-items:center;gap:7px}
.lgl:hover{color:var(--encre);border-color:var(--encre)}
.lg-n{font-size:11px;padding:0 6px;border-radius:9px;background:var(--fond2);color:var(--doux)}
.lg-pane{display:none}
<?php foreach (array_keys(ProdFiche::LOGI) as $cle): ?>
#lg-<?= e($cle) ?>:checked ~ .lg-bar .lgl-<?= e($cle) ?>{background:var(--jaune,#FFD24D);
  border-color:var(--jaune,#FFD24D);color:var(--encre);font-weight:600}
#lg-<?= e($cle) ?>:checked ~ .lg-bar .lgl-<?= e($cle) ?> .lg-n{background:rgba(0,0,0,.14);color:var(--encre)}
#lg-<?= e($cle) ?>:checked ~ .lg-pane-<?= e($cle) ?>{display:block}
<?php endforeach; ?>

/* ── LA CARTE D'UNE PERSONNE ───────────────────────────────────────────────
   Bornée en largeur: une ligne de voyage est courte, et l'étirer sur tout
   l'écran oblige l'œil à traverser du vide entre le nom et le montant. */
.lg-c{border:1px solid var(--trait);border-radius:10px;margin-bottom:14px;
  overflow:hidden;max-width:820px}
.lg-t{display:flex;align-items:baseline;gap:10px;padding:10px 14px;
  background:var(--fond2);border-bottom:1px solid var(--trait)}
.lg-nom{font-weight:600;font-size:14px}
.lg-f{font-size:12px;color:var(--doux)}
.lg-cpt{margin-left:auto;font-size:12.5px;color:var(--doux)}
.lg-vide{margin:12px 14px;font-size:13px;color:var(--doux)}
.lg-l{list-style:none;margin:0;padding:0}
.lg-l li{display:flex;align-items:center;gap:12px;padding:8px 14px;
  border-bottom:1px solid var(--trait);font-size:13.5px}
.lg-q{color:var(--doux);font-size:12.5px;white-space:nowrap}
.lg-quoi{font-weight:600}
.lg-tr{color:var(--doux)}
.lg-no{color:var(--doux);font-size:12.5px}
.lg-m{margin-left:auto;white-space:nowrap;font-variant-numeric:tabular-nums}

/* ── LE FORMULAIRE, EN TROIS RANGS COMME DANS LE MODÈLE ────────────────────
   Quand et quoi d'abord, le trajet ensuite, l'argent en dernier. C'est l'ordre
   dans lequel on tient l'information: on sait qu'on part avant de savoir
   combien cela coûte. */
.lg-aj{display:flex;flex-direction:column;gap:8px;padding:12px 14px;background:var(--papier)}
.lg-r1,.lg-r2,.lg-r3{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.lg-aj input,.lg-aj select{flex:1 1 150px;min-width:0;padding:7px 10px;font:inherit;
  font-size:13px;border:1px solid var(--trait);border-radius:6px;
  background:var(--papier);color:var(--encre);box-sizing:border-box}
.lg-aj input.lg-mt{flex:0 0 110px}
.lg-aj select.lg-dv{flex:0 0 82px}
.lg-aj input.lg-nt{flex:2 1 200px}
/* `.lg-aj button.lg-b` ET NON `.lg-b`: la règle générale de la fiche projet
   vise `button[type=submit]`, qui pèse autant que `button.lg-b` — et elle est
   écrite APRÈS, puisque la feuille de la fiche est émise en fin de page. À
   poids égal, la dernière gagne, et le bouton sortait noir au lieu de jaune.
   Deux fois vu à la capture, jamais à la lecture. */
.lg-aj button.lg-b{flex:0 0 auto;margin:0;padding:8px 16px;font-size:13.5px;font-weight:600;
  border:0;border-radius:6px;background:var(--jaune,#FFD24D);color:var(--encre);
  cursor:pointer;font-family:inherit}
.lg-aj button.lg-b:hover{opacity:.88}
@media (max-width:700px){.lg-aj input,.lg-aj select{flex:1 1 100%}}
</style>
