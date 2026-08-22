<?php
/**
 * Le budget, en document.  [Anna, 22.08.2026]
 *
 * « na hora de imprimir o budget colocar num formato como esse, em 2 colunas
 * entradas e saídas, título, local de apresentação, e etapa. »
 *
 * IL A SA PROPRE PAGE ET NE PASSE PLUS PAR LE RENDU COMMUN. Les neuf autres
 * onglets s'impriment en blocs empilés — un titre, du texte ou un tableau — et
 * c'est très bien pour eux. Un budget se lit en deux colonnes face à face: ce
 * qui sort à gauche, ce qui entre à droite, et le solde entre les deux. Empilé,
 * il oblige à retenir un total pendant qu'on lit l'autre.
 *
 * L'EN-TÊTE DIT DE QUOI ON PARLE AVANT DE DONNER DES CHIFFRES: le titre, qui
 * porte le projet, et les dates de jeu. Un budget qui circule sans ces trois
 * lignes revient avec la question.
 *
 * LES DATES DE JEU SE DÉDUISENT DU PLANNING, jamais saisies ici. Ce sont les
 * étapes marquées « Jeu »; s'il n'y en a aucune, on prend la première et la
 * dernière date de tout le planning plutôt que de laisser la ligne vide.
 *
 * CHAQUE LIGNE DIT À QUOI ELLE SE RAPPORTE quand ce n'est pas le projet entier.
 * Une ligne sans étape ne porte aucune mention: écrire « projet entier » sur
 * quinze lignes sur seize ne renseigne personne.
 *
 * LES MONTANTS SONT EN FRANCS, y compris ceux saisis en euros, convertis au taux
 * figé sur la ligne. La mention du taux et de sa date suit la ligne concernée:
 * un budget qui affiche 1 122 CHF sans dire d'où ils viennent se fait redemander.
 *
 * Attend $p, $d, $prod, $pcms.
 */
declare(strict_types=1);
/** @var array $p */ /** @var array $d */ /** @var array $prod */ /** @var int $pcms */

$B  = ProdFiche::budgetParPoste($d);
$mt = static fn($v): string => number_format((float)$v, 0, ',', ' ');

$titre = trim((string)($p['title_fr'] ?: $p['title_en'])) ?: 'Spectacle';

$org = $prod['organisation_id']
    ? DB::one('SELECT nom, direction FROM organisation WHERE id = ?', [(int)$prod['organisation_id']])
    : null;
/* La direction artistique de l'association quand elle est renseignée, son nom
   sinon: une fiche qui n'a pas encore sa direction ne doit pas laisser un blanc
   là où le lecteur cherche qui porte le projet. */
$direction = trim((string)($org['direction'] ?? '')) ?: trim((string)($org['nom'] ?? ''));

$jeu = [];
foreach (($d['planning']['dates'] ?? []) as $et) {
    if (($et['phase'] ?? '') !== 'jeu') continue;
    foreach (['debut', 'fin'] as $c) {
        $v = trim((string)($et[$c] ?? ''));
        if ($v !== '') $jeu[] = $v;
    }
}
if (!$jeu) {
    foreach (($d['planning']['dates'] ?? []) as $et) {
        foreach (['debut', 'fin'] as $c) {
            $v = trim((string)($et[$c] ?? ''));
            if ($v !== '') $jeu[] = $v;
        }
    }
}
sort($jeu);
$jourFr = static fn($x) => ($t = strtotime((string)$x)) ? date('d/m/Y', $t) : (string)$x;
/* La date du taux en français aussi: « 2026-08-21 » dans un document qui part au
   fiduciaire jure avec les dates de jeu écrites juste au-dessus. */
$jourPt = static fn($x) => ($t = strtotime((string)$x)) ? date('d.m.Y', $t) : (string)$x;
$dates  = $jeu
    ? ($jourFr($jeu[0]) . (count($jeu) > 1 && $jeu[0] !== $jeu[count($jeu) - 1]
        ? ' → ' . $jourFr($jeu[count($jeu) - 1]) : ''))
    : '';

/* Le périmètre: si toutes les lignes se rapportent à la même étape, on le dit;
   sinon c'est le projet entier. */
$etapes = [];
foreach (($d['budget'] ?? []) as $l) $etapes[trim((string)($l['etape'] ?? ''))] = true;
$perimetre = (count($etapes) === 1 && !isset($etapes['']))
    ? (string)array_key_first($etapes) : 'Totalité du projet';

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($titre) ?> — budget</title>
<style>
  @page { margin: 14mm; size: A4 landscape; }
  * { box-sizing: border-box; }
  body { margin:0; padding:30px 34px 24px;
         font:13px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
         color:#111; background:#fff; }
  h1 { font-size:25px; margin:0 0 16px; letter-spacing:-.01em; }
  .meta { margin:0 0 22px; font-size:13px; line-height:1.75; }
  .meta b { font-weight:600; }
  .quoi { font-size:16px; margin:0 0 2px; }
  .perim { font-size:12.5px; color:#666; margin:0 0 14px; }

  /* DEUX COLONNES, ET UNE SEULE RÈGLE ÉPAISSE AU-DESSUS DES DEUX: c'est elle qui
     dit qu'on lit deux listes en parallèle et non l'une après l'autre. */
  .deux { display:grid; grid-template-columns:1fr 1fr; gap:0 54px;
          border-top:2px solid #111; padding-top:12px; align-items:start; }
  .ct { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.09em;
        margin:0 0 10px; }
  .poste { font-size:9.5px; font-weight:600; text-transform:uppercase; letter-spacing:.09em;
           color:#8a8a8a; margin:12px 0 3px; }
  .l { display:flex; align-items:baseline; gap:10px; padding:3px 0;
       border-bottom:1px solid #e8e8e4; }
  .l .n { flex:1 1 auto; min-width:0; }
  .l .m { flex:0 0 auto; font-variant-numeric:tabular-nums; }
  .l .e { display:block; font-size:10.5px; color:#8a8a8a; }
  .tot { display:flex; justify-content:space-between; gap:10px; margin-top:10px;
         padding-top:7px; border-top:1px solid #111; font-weight:700; font-size:12px;
         text-transform:uppercase; letter-spacing:.05em; }
  .tot .m { font-variant-numeric:tabular-nums; }
  .solde { margin:26px 0 0; text-align:right; font-size:17px; font-weight:700; }
  .vide { color:#8a8a8a; }

  footer { margin-top:26px; padding-top:9px; border-top:2px solid #111;
           display:flex; justify-content:space-between; gap:20px;
           color:#6b6b6b; font-size:10.5px; }
  @media print { body { padding:0 } }
</style>
</head>
<body>

<h1><?= e($titre) ?></h1>

<p class="meta">
  imprimé le <?= date('d/m/Y') ?><br>
  <?php if ($direction !== ''): ?>Direction artistique : <b><?= e($direction) ?></b><br><?php endif; ?>
  <?php if ($dates !== ''): ?>Dates de jeu : <b><?= e($dates) ?></b><?php endif; ?>
</p>

<p class="quoi">Budget de production</p>
<p class="perim"><?= e($perimetre) ?></p>

<div class="deux">
  <div>
    <p class="ct">Charges</p>
    <?php foreach (ProdFiche::BUDGET_POSTES as $cle => $lib):
        $ps = $B['postes'][$cle];
        if (!$ps['lignes'] && $ps['auto'] <= 0) continue; ?>
      <p class="poste"><?= e($lib) ?></p>
      <?php if ($ps['auto'] > 0): ?>
        <div class="l"><span class="n">Salaires équipe (TTC)</span>
          <span class="m"><?= $mt($ps['auto']) ?> CHF</span></div>
      <?php endif; ?>
      <?php foreach ($ps['lignes'] as $l): ?>
        <div class="l">
          <span class="n"><?= e((string)($l['libelle'] ?? '')) ?: '—' ?>
            <?php $bouts = array_filter([
                    trim((string)($l['etape'] ?? '')),
                    (string)($l['devise'] ?? 'CHF') === 'EUR'
                        ? $l['montant'] . ' EUR au taux BCE du ' . $jourPt((string)($l['taux_jour'] ?? ''))
                        : '',
                  ]); ?>
            <?php if ($bouts): ?><span class="e"><?= e(implode(' · ', $bouts)) ?></span><?php endif; ?>
          </span>
          <span class="m"><?= $mt((float)$l['_m']) ?> CHF</span>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($B['charges'] <= 0 && !array_filter($B['postes'], fn($x) => (bool)$x['lignes'])): ?>
      <p class="vide">Aucune charge saisie.</p>
    <?php endif; ?>
    <div class="tot"><span>Total charges</span><span class="m"><?= $mt($B['charges']) ?> CHF</span></div>
  </div>

  <div>
    <p class="ct">Produits</p>
    <?php foreach ($B['produits'] as $l): ?>
      <div class="l">
        <span class="n"><?= e((string)($l['libelle'] ?? '')) ?: '—' ?>
          <?php $nat = ProdFiche::BUDGET_PRODUITS[(string)($l['nature'] ?? '')]
                    ?? ProdFiche::BUDGET_RECETTE[(string)($l['nature'] ?? '')] ?? ''; ?>
          <?php if ($nat !== ''): ?>(<?= e($nat) ?>)<?php endif; ?>
          <?php if ((string)($l['devise'] ?? 'CHF') === 'EUR'): ?>
            <span class="e"><?= e((string)$l['montant']) ?> EUR au taux BCE du
              <?= e($jourPt((string)($l['taux_jour'] ?? ''))) ?></span>
          <?php endif; ?>
        </span>
        <span class="m"><?= $mt((float)$l['_m']) ?> CHF</span>
      </div>
    <?php endforeach; ?>
    <?php if (!$B['produits']): ?><p class="vide">Aucun produit saisi.</p><?php endif; ?>
    <div class="tot"><span>Total produits</span><span class="m"><?= $mt($B['recettes']) ?> CHF</span></div>
  </div>
</div>

<p class="solde">Solde : <?= $mt($B['solde']) ?> CHF</p>

<footer>
  <span>Le Voisin Productions · anna@le-voisin.com · +41 78 257 13 17</span>
  <span>Document généré le <?= date('j') ?> <?= ['','janvier','février','mars','avril','mai','juin',
        'juillet','août','septembre','octobre','novembre','décembre'][(int)date('n')] ?> <?= date('Y') ?></span>
</footer>

</body>
</html>
<?php return;
