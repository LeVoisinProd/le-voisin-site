<?php
/**
 * La grille des prix, toutes les dates d'un coup. [16.08.2026]
 *
 * POURQUOI ELLE EXISTE, ET C'EST UN CONSTAT AVANT D'ÊTRE UN ÉCRAN. Mesuré le
 * 16.08.2026: les 51 dates de la base portent TOUTES un prix de cession à zéro,
 * et aucune fiche de production ne porte de budget. Le compte de résultat par
 * spectacle qu'Anna demande ne peut donc rien calculer — il sortirait une page
 * de zéros, exactement le reproche qu'elle a fait le matin même: « nenhuma
 * funcionalidade esta integrada, so tem os campos ».
 *
 * D'OÙ VIENT LE TROU. Ce n'est pas la reprise qui a perdu les prix: le
 * `lv-tour` du dashboard N'A PAS DE CHAMP DE PRIX. Ses neuf colonnes sont
 * dateLabel, venue, artist, city, show, start, end, id, url — c'est une liste
 * publique de tournée, pas un carnet de vente. L'argent vit ailleurs: dans les
 * devis produits par la skill `/devis`, dans Bexio, dans les feuilles de compta.
 * Aucun de ces trois n'est lisible par le site aujourd'hui.
 *
 * LE PLUS COURT CHEMIN N'EST DONC PAS UNE API, C'EST CINQUANTE ET UN NOMBRES.
 * Les saisir un par un demandait d'ouvrir 51 fiches, un onglet, un formulaire
 * et un enregistrement — soit 51 allers-retours pour 51 nombres. Ici: une page,
 * une colonne, un bouton.
 *
 * ON N'ÉCRIT QUE CE QUI A CHANGÉ, et le champ vide veut dire « je n'y touche
 * pas », jamais « efface ». C'est la même règle que pour les mots de passe des
 * associations, et pour la même raison: une grille de cinquante champs se
 * survole, et un enregistrement distrait ne doit pas vider ce qu'on n'a pas
 * regardé. Pour remettre une ligne à zéro, on écrit 0.
 */
declare(strict_types=1);
/** @var array $ETIQ */

$ecrit = dash_droit('bookings', dash_role()) === 'ecrit';

$lignes = DB::all(
    "SELECT b.id, b.projet, b.artiste, b.venue, b.ville, b.pays, b.date_debut, b.date_texte,
            b.representations, b.prix_cession, b.prix_vente, b.devise, b.statut
       FROM booking b
      WHERE b.supprime_le IS NULL
      ORDER BY b.projet, COALESCE(b.date_debut, '9999-12-31')");

/* Ce que la saisie débloque, dit en haut de page: sans le chiffre, personne ne
   sait si cela vaut la peine de remplir cinquante lignes. */
$sans = 0; $avec = 0; $somme = 0.0;
foreach ($lignes as $l) {
    if ((float)$l['prix_cession'] > 0) { $avec++; $somme += (float)$l['prix_cession']; }
    else $sans++;
}
?>

<div class="pxi">
  <p><strong><?= $sans ?></strong> date<?= $sans > 1 ? 's' : '' ?> sans prix de cession
     <?php if ($avec): ?>· <?= $avec ?> renseignée<?= $avec > 1 ? 's' : '' ?>,
       <?= number_format($somme, 0, ',', ' ') ?> au total<?php endif; ?>.
     Tant qu'une date n'a pas de prix, elle ne compte ni dans le relevé, ni dans le
     compte de résultat d'un spectacle. <strong>Un champ laissé vide n'efface rien</strong> —
     pour remettre à zéro, écrivez 0.</p>
</div>

<?php if (!$lignes): ?>
  <p class="vide">Aucune date.</p>
<?php else: ?>
<form method="post" action="/dashboard.php?e=bookings&amp;v=prix">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="act" value="prix_lot">

  <div class="tw"><table class="tprix">
    <thead><tr>
      <th>Date</th><th>Lieu</th><th>Ville</th><th class="d">Repr.</th><th>État</th>
      <th class="d">Prix de cession</th><th class="d">Prix de vente</th><th>Devise</th>
    </tr></thead>
    <tbody>
    <?php $projetPrec = null;
    foreach ($lignes as $l): $bid = (int)$l['id'];
      if ($l['projet'] !== $projetPrec): $projetPrec = $l['projet']; ?>
        <tr class="grp"><td colspan="8"><?= e((string)($l['projet'] ?: '— sans spectacle')) ?>
          <?php if ($l['artiste']): ?><span class="sec"> · <?= e((string)$l['artiste']) ?></span><?php endif; ?></td></tr>
      <?php endif; ?>
      <tr>
        <td class="sec"><a href="/dashboard.php?e=bookings&amp;b=<?= $bid ?>"><?=
          $l['date_debut'] ? e(date('d.m.Y', strtotime((string)$l['date_debut'])))
                           : e((string)$l['date_texte']) ?></a></td>
        <td><?= e((string)($l['venue'] ?? '')) ?></td>
        <td class="sec"><?= e(trim((string)$l['ville'] . ($l['pays'] ? ', ' . $l['pays'] : ''))) ?></td>
        <td class="d sec"><?= $l['representations'] ? (int)$l['representations'] : '' ?></td>
        <td><span class="et <?= e((string)$l['statut']) ?>"><?= e($ETIQ[$l['statut']] ?? (string)$l['statut']) ?></span></td>
        <td class="d">
          <input type="text" inputmode="decimal" name="c[<?= $bid ?>]" class="px"
                 value="<?= $l['prix_cession'] !== null && (float)$l['prix_cession'] > 0
                          ? e((string)(0 + (float)$l['prix_cession'])) : '' ?>"
                 <?= $ecrit ? '' : 'readonly' ?> placeholder="—">
        </td>
        <td class="d">
          <input type="text" inputmode="decimal" name="v[<?= $bid ?>]" class="px"
                 value="<?= $l['prix_vente'] !== null && (float)$l['prix_vente'] > 0
                          ? e((string)(0 + (float)$l['prix_vente'])) : '' ?>"
                 <?= $ecrit ? '' : 'readonly' ?> placeholder="—">
        </td>
        <td>
          <select name="d[<?= $bid ?>]" <?= $ecrit ? '' : 'disabled' ?>>
            <?php foreach (['CHF', 'EUR'] as $dv): ?>
              <option <?= ($l['devise'] ?: 'CHF') === $dv ? 'selected' : '' ?>><?= $dv ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <?php if ($ecrit): ?>
    <div class="act-px"><button type="submit">Enregistrer les prix</button></div>
  <?php endif; ?>
</form>
<?php endif; ?>

<style>
.pxi{margin:0 0 16px;padding:11px 15px;background:var(--fond2);font-size:13px;max-width:92ch}
.pxi p{margin:0}
.tprix td{vertical-align:middle}
.tprix tr.grp td{padding-top:16px;font-weight:700;font-size:13px;border-bottom:1px solid var(--encre)}
/* `text-align:right` et `tabular-nums`: une colonne de montants se lit en
   comparant les rangs des chiffres, pas les mots. */
.tprix input.px{width:104px;padding:5px 8px;font:inherit;font-size:14px;text-align:right;
  font-variant-numeric:tabular-nums;border:1px solid var(--trait);border-radius:4px;
  background:var(--papier);color:var(--encre)}
.tprix input.px:focus{border-color:var(--encre);outline:none}
.tprix select{padding:5px 6px;font:inherit;font-size:13px}
.act-px{position:sticky;bottom:0;padding:12px 0;background:var(--papier);
  border-top:1px solid var(--trait);margin-top:6px}
/* Le bouton reste sous les yeux: une grille de cinquante lignes se remplit en
   défilant, et un bouton en bas de page oblige à revenir le chercher. */
.act-px button{padding:9px 20px;font:inherit;font-size:14px;font-weight:600;
  background:var(--encre);color:var(--papier);border:0;border-radius:5px;cursor:pointer}
</style>
