<?php
/**
 * Onglet Devis — le calcul du prix de cession. [17.08.2026]
 *
 * Anna: « na parte devis temos que criar a logica que usamos para fazer com
 * bestiarium: saber quem viaja, a quantidade de dias, o preço de le voisin, os
 * custos de produção (que no bestiarium não tinha) e depois a margem ».
 *
 * IL CALCULE, MAINTENANT. La version du 16.08 disait le contraire — « le calcul
 * vit dans le dépôt de travail, le refaire ici donnerait deux calculs qui
 * divergeraient au premier barème changé » — et l'argument était juste tant que
 * le calcul n'existait qu'en Excel. Sauf que le résultat était déjà là: huit
 * devis partis le 07.08 avec DEUX grilles contradictoires pour la même pièce,
 * l'une dégressive par jour, l'autre par représentation. Deux calculs avaient
 * déjà divergé, et personne ne s'en apercevait parce qu'aucun écran ne les
 * mettait côte à côte.
 *
 * LA CHAÎNE N'EST PAS INVENTÉE ICI. Elle est dans `ProdFiche::devisCalcul()`,
 * qui exécute la note du dépôt de travail corrigée par Anna les 14 et 15.08.
 * Cet écran ne fait que la montrer et la rendre saisissable.
 *
 * ET IL LA MONTRE EN ENTIER, ligne par ligne, plutôt que de sortir un nombre.
 * Un prix de cession se défend au téléphone: « pourquoi 3 700 ? » se répond en
 * lisant les postes, pas en citant un total. C'est aussi la seule façon de voir
 * qu'un tarif manque — un zéro se remarque dans une colonne, jamais dans une
 * somme.
 */
declare(strict_types=1);
/** @var array $d */ /** @var bool $ecrit */ /** @var callable $lien */
/** @var callable $champ */ /** @var int $pid */ /** @var array $p */

$v      = $d['devis'] ?? [];
$equipe = (array)($v['equipe'] ?? []);
$url    = $lien('devis');
$seuil  = (int)(float)($v['seuil'] ?? 10);
$taux   = (float)str_replace([' ', "'"], '', (string)($v['diffusion']['taux'] ?? 80));

/** Un montant, à l'aise à lire. */
$m = static fn(float $x, int $dec = 0): string => number_format($x, $dec, ',', ' ');

/* L'hypothèse détaillée: par défaut une journée, celle qui sert de référence
   dans tous les devis du Bestiarium. Elle se change par l'URL pour lire le
   détail d'une série plus longue sans quitter l'écran. */
$jours  = max(1.0, min(40.0, (float)($_GET['j'] ?? 1)));
$calc   = ProdFiche::devisCalcul($d, $jours);
$grille = ProdFiche::devisGrille($d);

$titre = trim((string)($p['title_fr'] ?: $p['title_en']));
$dates = $titre === '' ? [] : DB::all(
    "SELECT id, date_debut, date_texte, venue, ville, pays, prix_cession, devise, statut,
            representations
       FROM booking
      WHERE supprime_le IS NULL AND projet = ?
      ORDER BY COALESCE(date_debut,'9999-12-31')", [$titre]);
?>

<?php if (!$equipe): ?>
  <div class="rap">
    <p><strong>Ce spectacle n'a pas encore de calcul de cession.</strong> Il en faut trois
       choses: qui travaille et à quel tarif, le temps du Voisin, et les coûts de production.</p>
    <?php if ($ecrit): ?>
    <form method="post" action="<?= e($url) ?>" class="lf">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="pf" value="devis_defauts">
      <button type="submit">Reprendre les valeurs du Bestiarium</button>
    </form>
    <p class="aide">5 000 CHF de salaire mensuel de référence sur 20 jours ouvrés, 8,33 % de
       vacances, 19 % de charges patronales, 10 % de marge, la diffusion à 80 CHF de l'heure.
       Elles viennent de l'onglet « Barèmes » du fichier de calcul, où chacune porte sa source
       et sa date. Tout se corrige ensuite, ligne par ligne.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e($url) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="champs">

  <h3 class="bloc">Ce qui décide du prix</h3>
  <div class="quatre">
    <?= $champ('devis.marge', 'Marge (%)', (string)($v['marge'] ?? '10'),
        'Se change à chaque devis. Le manuel Reso dit 20 %, Anna a retenu 10 % le 14.08.2026 — et depuis que le temps du bureau est compté en coût direct, ces 10 % sont du résultat: les céder ne réduit plus une provision.') ?>
    <?= $champ('devis.repr_jour', 'Représentations par jour', (string)($v['repr_jour'] ?? '2'),
        'Deux au maximum pour le Bestiarium. Un jour à deux représentations vaut 1,5 jour de salaire, la seconde étant un service.') ?>
    <?= $champ('devis.seuil', 'La grille s\'arrête à (repr.)', (string)($v['seuil'] ?? '10'),
        'Au delà, la série relève d\'un tarif à la semaine et non de la dégressivité.') ?>
    <?= $champ('devis.tarif_semaine', 'Tarif à la semaine', (string)($v['tarif_semaine'] ?? ''),
        'Pour les séries qui dépassent le seuil. Vide tant qu\'il n\'est pas fixé.') ?>
  </div>

  <h3 class="bloc">Le temps du Voisin</h3>
  <p class="aide sous">C'est <strong>notre</strong> rémunération, et rien d'autre. Elle se
     facture à l'heure et ne se paie pas en cachet, donc elle n'est pas dans les salaires.
     Depuis le 14.08.2026 elle est un coût direct: la marge ne la couvre plus.</p>
  <div class="quatre">
    <?= $champ('devis.diffusion.heures', 'Heures de production et diffusion',
        (string)($v['diffusion']['heures'] ?? ''), '4 h par date dans le modèle du Bestiarium.') ?>
    <?= $champ('devis.diffusion.taux', 'Tarif horaire',
        (string)($v['diffusion']['taux'] ?? '80'), 'CHF') ?>
  </div>

  <h3 class="bloc">Coûts de production</h3>
  <p class="aide sous">Les cinq postes dictés par Anna le 17.08.2026. Ils sont vides sur le
     Bestiarium — c'est son propre constat — et ce qui n'entre pas dans le devis n'est jamais
     payé.</p>
  <div class="quatre">
    <?php foreach (ProdFiche::DEVIS_PRODUCTION as $k => $libelle): ?>
      <?= $champ('devis.production.' . $k, $libelle, (string)($v['production'][$k] ?? '')) ?>
    <?php endforeach; ?>
  </div>

  <?php if ($ecrit): ?>
    <div class="actions"><button type="submit">Enregistrer</button></div>
  <?php endif; ?>
</form>

<h3 class="bloc">Qui travaille, et combien de jours</h3>
<p class="aide sous">
  <strong>« Suit le jeu »</strong> dit si les jours d'une personne montent avec la série.
  Annina et la régie oui — un jour de plus, c'est un jour de plus pour elles. L'administration
  non: sa demi-journée est la même qu'on joue deux fois ou dix.<br>
  <strong>L'administration n'est pas Le Voisin.</strong> C'est une demi-journée de quelqu'un de
  l'association qui porte la pièce — la Gran Chichornia pour le Bestiarium. Sur Improvável
  Produções, où nous ne faisons pas l'administration, cette ligne se retire.
</p>

<?php if ($equipe): ?>
<div class="tbl">
<table>
  <?php if (!$ecrit): ?>
  <thead><tr><th>Rôle</th><th>Personne</th><th class="d">Paie mensuelle</th>
    <th class="d">Jours fixes</th><th>Suit le jeu</th></tr></thead>
  <?php endif; ?>
  <tbody>
  <?php foreach ($equipe as $l): $lid = (string)($l['id'] ?? ''); ?>
    <tr>
      <?php if ($ecrit): ?>
      <td class="ligne-form">
        <form method="post" action="<?= e($url) ?>" class="lf">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="pf" value="liste_modifier">
          <input type="hidden" name="ou" value="devis.equipe">
          <input type="hidden" name="ligne" value="<?= e($lid) ?>">
          <input type="text" name="l[role]" value="<?= e((string)($l['role'] ?? '')) ?>" placeholder="Rôle">
          <input type="text" name="l[nom]"  value="<?= e((string)($l['nom'] ?? '')) ?>" placeholder="Nom, si connu">
          <input type="text" name="l[paie]" value="<?= e((string)($l['paie'] ?? '')) ?>" class="nb" placeholder="paie/mois">
          <input type="text" name="l[jours_fixes]" value="<?= e((string)($l['jours_fixes'] ?? '')) ?>" class="nb" placeholder="j. fixes">
          <select name="l[suit_jeu]">
            <option value="1"<?= (string)($l['suit_jeu'] ?? '1') !== '0' ? ' selected' : '' ?>>suit le jeu</option>
            <option value="0"<?= (string)($l['suit_jeu'] ?? '1') === '0' ? ' selected' : '' ?>>jours fixes seuls</option>
          </select>
          <button type="submit">ok</button>
        </form>
        <form method="post" action="<?= e($url) ?>" class="lf sup"
              onsubmit="return confirm('Retirer cette ligne du calcul ?')">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="pf" value="liste_retirer">
          <input type="hidden" name="ou" value="devis.equipe">
          <input type="hidden" name="ligne" value="<?= e($lid) ?>">
          <button type="submit" class="x">retirer</button>
        </form>
      </td>
      <?php else: ?>
        <td><?= e((string)($l['role'] ?? '')) ?></td>
        <td class="sec"><?= e((string)($l['nom'] ?? '')) ?></td>
        <td class="d nb"><?= e((string)($l['paie'] ?? '')) ?></td>
        <td class="d nb"><?= e((string)($l['jours_fixes'] ?? '')) ?></td>
        <td><?= (string)($l['suit_jeu'] ?? '1') === '0' ? 'non' : 'oui' ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($ecrit): ?>
<form method="post" action="<?= e($url) ?>" class="lf ajout">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="pf" value="liste_ajouter">
  <input type="hidden" name="ou" value="devis.equipe">
  <input type="text" name="l[role]" placeholder="Rôle — jeu, régie, administration">
  <input type="text" name="l[nom]"  placeholder="Nom, si connu">
  <input type="text" name="l[paie]" class="nb" placeholder="paie/mois">
  <input type="text" name="l[jours_fixes]" class="nb" placeholder="j. fixes">
  <select name="l[suit_jeu]"><option value="1">suit le jeu</option><option value="0">jours fixes seuls</option></select>
  <button type="submit">ajouter</button>
</form>
<?php endif; ?>

<?php if ($equipe): ?>

<h3 class="bloc">Le détail, pour <?= $m($jours) ?> jour<?= $jours > 1 ? 's' : '' ?> de jeu
  · <?= (int)$calc['repr'] ?> représentation<?= $calc['repr'] > 1 ? 's' : '' ?></h3>
<p class="aide sous">
  <?php foreach ([1, 2, 3, 4, 5] as $j): ?>
    <a href="<?= e($url . '&j=' . $j) ?>"<?= (float)$j === $jours ? ' class="ici"' : '' ?>><?= $j ?> j</a>
  <?php endforeach; ?>
</p>

<?php if ($calc['sans_tarif']): ?>
  <div class="rap alerte"><strong><?= (int)$calc['sans_tarif'] ?> ligne(s) d'équipe sans paie
    mensuelle.</strong> Elles comptent pour zéro dans le total ci-dessous, donc le prix affiché
    est plus bas que le vrai.</div>
<?php endif; ?>

<div class="tbl">
<table class="calc">
  <thead><tr>
    <th>Poste</th><th class="d">Jours</th><th class="d">Base</th>
    <th class="d">Vacances <?= $m(ProdFiche::DEVIS_VACANCES, 2) ?> %</th>
    <th class="d">Brut</th>
    <th class="d">Patronales <?= $m(ProdFiche::DEVIS_PATRONALES, 0) ?> %</th>
    <th class="d">TTC</th>
  </tr></thead>
  <tbody>
  <?php foreach ($calc['personnes'] as $x): ?>
    <tr>
      <td><?= e($x['role']) ?><?= $x['nom'] !== '' ? ' <span class="sec">' . e($x['nom']) . '</span>' : '' ?><?=
          $x['suit'] ? '' : ' <span class="sec">jours fixes</span>' ?></td>
      <td class="d nb"><?= $m($x['jours'], 2) ?></td>
      <td class="d nb"><?= $m($x['base'], 2) ?></td>
      <td class="d nb"><?= $m($x['vacances'], 2) ?></td>
      <td class="d nb"><?= $m($x['brut'], 2) ?></td>
      <td class="d nb"><?= $m($x['patronales'], 2) ?></td>
      <td class="d nb"><strong><?= $m($x['ttc'], 2) ?></strong></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="somme"><th colspan="6">A · Salaires</th><th class="d nb"><?= $m($calc['salaires'], 2) ?></th></tr>
    <tr><td colspan="6">B · Le Voisin — <?= $m($calc['heures'], 2) ?> h × <?= $m($taux, 0) ?></td>
        <td class="d nb"><?= $m($calc['diffusion'], 2) ?></td></tr>
    <?php foreach (ProdFiche::DEVIS_PRODUCTION as $k => $libelle):
        if ((float)($calc['postes'][$k] ?? 0) <= 0) continue; ?>
      <tr><td colspan="6">C · <?= e($libelle) ?></td>
          <td class="d nb"><?= $m((float)$calc['postes'][$k], 2) ?></td></tr>
    <?php endforeach; ?>
    <tr class="somme"><th colspan="6">Charges</th><th class="d nb"><?= $m($calc['charges'], 2) ?></th></tr>
    <tr><td colspan="6">Marge <?= $m($calc['taux_marge'], 0) ?> %</td>
        <td class="d nb"><?= $m($calc['marge'], 2) ?></td></tr>
    <tr class="prix"><th colspan="6">Prix de cession</th>
        <th class="d nb"><?= $m($calc['prix'], 0) ?></th></tr>
    <tr><td colspan="6">Par représentation</td>
        <td class="d nb"><?= $m($calc['unitaire'], 0) ?></td></tr>
  </tfoot>
</table>
</div>

<h3 class="bloc">La grille</h3>
<p class="aide sous">Le coût suit les <strong>jours</strong>. Deux représentations le même jour
   ajoutent une demi-journée de salaire et non une journée entière: jouer deux fois dans la
   journée reste nettement moins cher que sur deux jours, mais ce n'est pas gratuit.
   <em>La feuille de calcul du 07.08 le comptait gratuit — corrigé par Anna le 15.08, et
   c'est pour cela que cette grille n'est pas celle des huit devis déjà partis.</em></p>

<div class="tbl">
<table>
  <thead><tr><th class="d">Repr.</th><th class="d">Jours de jeu</th>
    <th class="d">Charges</th><th class="d">Prix de cession</th><th class="d">Unitaire</th></tr></thead>
  <tbody>
  <?php foreach ($grille as $g): ?>
    <tr<?= (float)$g['jours'] === $jours ? ' class="ici"' : '' ?>>
      <td class="d nb"><?= (int)$g['repr'] ?></td>
      <td class="d nb"><?= $m($g['jours']) ?></td>
      <td class="d nb"><?= $m($g['charges'], 0) ?></td>
      <td class="d nb"><strong><?= $m($g['prix'], 0) ?></strong></td>
      <td class="d nb"><?= $m($g['unitaire'], 0) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<p class="aide sous">Au delà de <?= $seuil ?> représentations, la série relève d'un
   <strong>tarif à la semaine</strong> — décision d'Anna, 17.08.2026 — et non de la
   dégressivité ci-dessus.
   <?php if (trim((string)($v['tarif_semaine'] ?? '')) !== ''): ?>
     Il est fixé à <strong><?= e((string)$v['tarif_semaine']) ?></strong>.
   <?php else: ?>
     <strong>Il n'est pas encore fixé</strong>, et c'est exactement le cas du Théâtre de
     Carouge, qui demande deux à trois semaines.
   <?php endif; ?></p>

<div class="rap">
  <p><strong>Hors prix de cession</strong>, modèle « plus, plus, plus » du manuel Reso:
     voyages, hébergement, per diem, transport du décor et droits d'auteur. Ils ne sont pas
     dans le calcul ci-dessus et ne doivent jamais y entrer.</p>
  <p>Le jour de voyage ne compte pas — décision d'Anna du 10.08.2026: c'est un fait
     logistique, pas un jour de contrat.</p>
</div>

<?php endif; ?>

<h3 class="bloc">Ce qui a été vendu</h3>
<?php if ($dates): $tot = 0.0; foreach ($dates as $x) $tot += (float)$x['prix_cession']; ?>
  <div class="tbl"><table>
    <thead><tr><th>Date</th><th>Lieu</th><th class="d">Repr.</th>
      <th class="d">Prix de cession</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($dates as $x): ?>
      <tr>
        <td><a href="/dashboard.php?e=bookings&amp;b=<?= (int)$x['id'] ?>&amp;o=deal"><?=
          e($x['date_texte'] ?: (string)$x['date_debut']) ?></a></td>
        <td class="sec"><?= e((string)$x['venue']) ?><?php if ($x['ville']): ?>, <?= e((string)$x['ville']) ?><?php endif; ?></td>
        <td class="d nb"><?= (int)$x['representations'] ?></td>
        <td class="d nb"><?= $x['prix_cession'] !== null
            ? $m((float)$x['prix_cession'], 2) . ' ' . e($x['devise']) : '—' ?></td>
        <td class="sec"><?= e((string)$x['statut']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="3">Total</th><th class="d nb"><?= $m($tot, 2) ?></th><th></th></tr></tfoot>
  </table></div>
<?php else: ?>
  <p class="aide sous">Aucune date ne porte ce titre. L'appariement se fait sur le nom du
     spectacle: si les dates existent sous un autre libellé, elles n'apparaissent pas ici.</p>
<?php endif; ?>

<style>
h3.bloc{margin:26px 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;
  color:var(--doux);border-bottom:1px solid var(--trait);padding-bottom:5px}
.quatre{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0 22px}
.aide.sous{max-width:780px;font-size:13px;color:var(--doux);margin:0 0 12px}
.aide.sous a{margin-right:9px}
.aide.sous a.ici{font-weight:700;color:var(--encre)}
td.ligne-form{padding:5px 10px}
form.lf{display:inline-flex;gap:7px;align-items:center;flex-wrap:wrap}
form.lf input,form.lf select{padding:6px 9px;font-size:13.5px;font-family:inherit;
  border:1px solid var(--trait);border-radius:4px;background:var(--papier);color:var(--encre)}
form.lf input.nb{width:104px;text-align:right;font-variant-numeric:tabular-nums}
form.lf button{padding:6px 12px;font-size:13px}
form.lf.sup{margin-left:8px}
form.lf .x{background:none;border:0;color:var(--orange);text-decoration:underline;cursor:pointer;
  font-family:inherit;font-size:13px}
form.lf.ajout{padding:10px 0 4px}
table.calc tfoot tr.somme th{background:var(--fond2)}
table.calc tfoot tr.prix th{background:var(--jaune);color:#0d0d0d;font-size:14.5px}
table.calc tfoot td,table.calc tfoot th{border-top:1px solid var(--trait)}
tr.ici td{background:#fffbe9}
td.d,th.d{text-align:right}
.nb{font-variant-numeric:tabular-nums}
.rap.alerte{border-left:3px solid var(--orange);padding-left:14px}
</style>
