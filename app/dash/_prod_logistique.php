<?php
/**
 * Onglet Logistique. [16.08.2026]
 *
 * Quatre volets, ceux du dashboard: voyages, hébergement, repas, transports.
 *
 * POURQUOI QUATRE LISTES ET PAS UNE AVEC UNE COLONNE « type ». Parce qu'on ne
 * les remplit pas au même moment ni avec les mêmes têtes: les voyages se
 * réservent des mois avant, les repas se comptent la semaine d'avant. Une
 * liste unique obligerait à filtrer pour faire n'importe lequel des deux
 * gestes, et à voir les trois autres pendant qu'on en fait un.
 *
 * Chaque ligne partage les mêmes champs, et c'est ce qui permet à la Feuille de
 * route de les imprimer toutes de la même façon.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */
?>
<?php foreach (ProdFiche::LOGI as $cle => $lib):
  $lignes = $d['logistique'][$cle] ?? []; ?>

  <h3<?= $cle === 'voyages' ? '' : ' class="sep"' ?>><?= e($lib) ?></h3>

  <?php if ($lignes): ?>
    <div class="tbl"><table>
      <thead><tr><th>Quand</th><th>Qui</th><th>Quoi</th><th>De</th><th>À</th>
        <th>Référence</th><th class="d">Montant</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lignes as $l): ?>
        <tr>
          <td class="sec"><?= e((string)($l['quand'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['qui'] ?? '')) ?></td>
          <td><?= e((string)($l['libelle'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['depart'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['arrivee'] ?? '')) ?></td>
          <td class="sec"><?= e((string)($l['reference'] ?? '')) ?></td>
          <td class="d"><?= ($l['montant'] ?? '') !== ''
              ? e((string)$l['montant']) . ' ' . e((string)($l['devise'] ?? '')) : '' ?></td>
          <td class="d">
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
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="aide">Rien pour l'instant.</p>
  <?php endif; ?>

  <?php if ($ecrit): ?>
  <form method="post" action="<?= e($lien('logistique')) ?>" class="ajl">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="pf" value="liste_ajouter">
    <input type="hidden" name="ou" value="logistique.<?= e($cle) ?>">
    <?php /* « QUAND »: UNE ÉTAPE DU PLANNING, OU UNE DATE. [Anna, 22.08.2026]
         « le champ quand lier aux étapes de travail créé dans planning »,
         « les champs de dates laisser le choix du calendrier ».

         Les deux, parce que les deux arrivent: le gros de la logistique se
         rattache à une étape — la résidence, la série de jeu — mais un vol
         tombe la veille du départ, hors de toute étape. Un champ qui ne
         saurait faire que l'un des deux obligerait à écrire l'autre à la main,
         et c'est exactement ce que ces listes doivent supprimer.

         Le champ enregistré reste du TEXTE: la Feuille de route imprime
         « Quand » tel quel, et une étape choisie s'y écrit en clair. */ ?>
    <?php $etapes = $d['planning']['dates'] ?? []; ?>
    <?php if ($etapes): ?>
      <select name="l[quand]" class="lg-q">
        <option value="">— quand —</option>
        <?php foreach ($etapes as $et):
          $lb = trim(implode(' · ', array_filter([
            /* Les dates en clair, pas en ISO: cette liste se lit, elle ne se
               trie pas. « 12.04 – 15.04 » se reconnaît d'un coup d'œil là où
               « 2027-04-12–2027-04-15 » se déchiffre. */
            (function ($a, $b) {
                $f = fn($x) => ($t = strtotime((string)$x)) ? date('d.m.Y', $t) : (string)$x;
                if ($a === '' && $b === '') return '';
                if ($b === '' || $b === $a) return $f($a);
                return $f($a) . ' – ' . $f($b);
            })((string)($et['debut'] ?? ''), (string)($et['fin'] ?? '')),
            ProdFiche::PHASES[$et['phase'] ?? ''] ?? ((string)($et['phase'] ?? '')),
            trim(((string)($et['lieu'] ?? '')) . (($et['ville'] ?? '') ? ', ' . $et['ville'] : ''), ', '),
          ]))); ?>
          <option value="<?= e($lb) ?>"><?= e($lb) ?></option>
        <?php endforeach; ?>
      </select>
    <?php else: ?>
      <input type="text" name="l[quand]" placeholder="Quand" size="12">
    <?php endif; ?>
    <input type="date" name="l[quand_date]" class="lg-d" title="Ou une date précise">

    <?php /* « QUI »: L'ÉQUIPE DE CETTE PRODUCTION. Décision d'Anna. C'est la
         liste courte et toujours pertinente — les gens qui voyagent sur cette
         pièce. Le champ libre reste à côté pour ce qui sort du cadre: un
         chauffeur, quelqu'un du lieu. */ ?>
    <?php $eq = $d['equipe'] ?? []; ?>
    <?php if ($eq): ?>
      <select name="l[qui_choix]" class="lg-q">
        <option value="">— qui —</option>
        <?php foreach ($eq as $m):
          $nm = trim(((string)($m['prenom'] ?? '')) . ' ' . ((string)($m['nom'] ?? '')));
          if ($nm === '') continue; ?>
          <option value="<?= e($nm) ?>"><?= e($nm) ?><?php
            if (!empty($m['fonction'])): ?> · <?= e((string)$m['fonction']) ?><?php endif; ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input type="text" name="l[qui]" placeholder="ou un autre nom" size="12">
    <input type="text" name="l[libelle]"   placeholder="Quoi" size="18" required>
    <input type="text" name="l[depart]"    placeholder="De" size="9">
    <input type="text" name="l[arrivee]"   placeholder="À" size="9">
    <input type="text" name="l[reference]" placeholder="Référence" size="10">
    <input type="text" name="l[montant]"   placeholder="Montant" size="8">
    <select name="l[devise]"><option>CHF</option><option>EUR</option></select>
    <button type="submit">ajouter</button>
  </form>
  <?php endif; ?>
<?php endforeach; ?>

<style>
/* Les deux listes et le champ libre tiennent sur la même ligne que le reste:
   une ligne de logistique se saisit d'un trait, sans sauter de rang. */
.ajl .lg-q{max-width:210px;padding:7px 9px;font:inherit;font-size:13px;
  border:1px solid var(--trait);border-radius:5px;background:#fff}
.ajl .lg-d{padding:7px 9px;font:inherit;font-size:13px;
  border:1px solid var(--trait);border-radius:5px}
</style>
