<?php
/**
 * Les dix onglets, en version imprimable. [16.08.2026]
 *
 * IL N'Y EN AVAIT QUE DEUX, et c'était une erreur de jugement écrite noir sur
 * blanc dans l'ancienne version de ce fichier: « ce sont les deux seuls onglets
 * qui sortent de la maison. Les autres se lisent à l'écran et n'ont pas besoin
 * de papier. » Anna, le 16.08.2026: « todas as etapas (…) tem que poder
 * imprimir ». Elle a raison et le raisonnement était faux — une fiche technique
 * part au lieu, un budget part au financeur, une répartition de droits part à
 * la SSA, une rémunération se relit en réunion. Ce qui ne sort pas de la maison
 * sort quand même de l'écran.
 *
 * Même parti que le relevé des Finances: aucune bibliothèque PDF sur ce site,
 * et le navigateur sait faire un PDF d'une page — un vrai, sélectionnable et
 * cherchable, pas une image.
 *
 * LA PAGE EST NUE: ni menu, ni onglets, ni liens, ni bouton. Ce qui reste à
 * l'écran s'imprime, et rien d'autre.
 *
 * UN SEUL RENDU POUR DIX ONGLETS. Chaque onglet décrit ce qu'il imprime sous
 * forme de blocs — un titre, un type, des données — et le rendu s'occupe du
 * reste. Dix mises en page écrites à la main divergeraient à la première
 * correction faite sur une seule.
 *
 * Attend $p, $onglet, $pcms.
 */
declare(strict_types=1);
/** @var array $p */ /** @var string $onglet */ /** @var int $pcms */

$d     = ProdFiche::donnees($pcms);
$prod  = ProdFiche::ligne($pcms);
$titre = trim((string)($p['title_fr'] ?: $p['title_en'])) ?: 'Spectacle';

$NOMS = [
    'synthese'     => 'Synthèse',
    'dossier'      => 'Dossier de demande de fonds',
    'planning'     => 'Planning',
    'logistique'   => 'Logistique',
    'technique'    => 'Fiche technique',
    'fdr'          => 'Feuille de route',
    'remuneration' => 'Rémunération',
    'budget'       => 'Budget',
    'devis'        => 'Devis et dates vendues',
    'droits'       => 'Déclaration de droits d\'auteur',
];
$quoi = $NOMS[$onglet] ?? 'Fiche de production';

/* Le porteur, imprimé sous le titre: un document qui part sans dire quelle
   association le porte oblige le destinataire à demander. */
$porteur = $prod['organisation_id']
    ? (string)(DB::val('SELECT nom FROM organisation WHERE id = ?', [(int)$prod['organisation_id']]) ?: '')
    : '';

/* ── Les blocs de chaque onglet ─────────────────────────────────────────────
   Types: 'texte' une chaîne · 'defs' des paires · 'table' un en-tête et des
   lignes déjà mises en forme. Un bloc vide n'est pas imprimé — une page pleine
   de « — » se lit moins bien qu'une page courte. */

$blocs = [];
$ajout = static function (string $titre, string $type, $donnees) use (&$blocs): void {
    if ($type === 'texte' && trim((string)$donnees) === '') return;
    if ($type !== 'texte' && !$donnees) return;
    $blocs[] = ['t' => $titre, 'type' => $type, 'd' => $donnees];
};
$mt = static fn($v, $dev = '') => $v === null || $v === ''
    ? '' : number_format((float)$v, 2, ',', ' ') . ($dev ? ' ' . $dev : '');

switch ($onglet) {

case 'synthese':
    $ajout('Résumé',        'texte', (string)$d['resume']);
    $ajout('Coproductions', 'texte', (string)$d['coproductions']);
    $ajout('Soutiens',      'texte', (string)$d['soutiens']);
    $s = $d['statistiques'] ?? [];
    $ajout('Statistiques', 'defs', array_filter([
        'Représentations' => (string)($s['representations'] ?? ''),
        'Spectateurs'     => (string)($s['spectateurs'] ?? ''),
        'Recettes'        => (string)($s['recettes'] ?? ''),
        'Villes'          => (string)($s['villes'] ?? ''),
    ], fn($v) => trim($v) !== ''));
    $ajout('Notes', 'texte', (string)($s['notes'] ?? ''));
    $ajout('Équipe', 'table', [
        'entete' => ['Nom', 'Fonction'],
        'lignes' => array_map(fn($l) => [
            trim(((string)($l['prenom'] ?? '')) . ' ' . ((string)($l['nom'] ?? ''))),
            (string)($l['fonction'] ?? ''),
        ], $d['equipe'] ?? []),
    ]);
    break;

case 'dossier':
    foreach (['lettre' => 'Lettre de motivation', 'description' => 'Description du projet',
              'intention' => 'Note d\'intention'] as $k => $t)
        $ajout($t, 'texte', (string)($d['dossier'][$k] ?? ''));
    $cal = trim((string)($d['dossier']['calendrier'] ?? ''));
    if ($cal === '') $cal = ProdFiche::calendrierDepuisPlanning($d);
    $ajout('Calendrier', 'texte', $cal);
    foreach (['publicCible' => 'Public cible', 'benefice' => 'Bénéfice pour la ville'] as $k => $t)
        $ajout($t, 'texte', (string)($d['dossier'][$k] ?? ''));
    $ajout('Résumé',        'texte', (string)$d['resume']);
    $ajout('Coproductions', 'texte', (string)$d['coproductions']);
    $ajout('Soutiens',      'texte', (string)$d['soutiens']);
    break;

case 'planning':
    $pl = $d['planning'] ?? [];
    $ajout('Dates de la production', 'defs', array_filter([
        'Arrivée' => (string)($pl['dateArrivee'] ?? ''),
        'Retour'  => (string)($pl['dateRetour'] ?? ''),
    ], fn($v) => trim($v) !== ''));
    $ajout('Étapes', 'table', [
        'entete' => ['Du', 'Au', 'Phase', 'Lieu', 'Ville', 'Pays'],
        'lignes' => array_map(fn($l) => [
            (string)($l['debut'] ?? ''), (string)($l['fin'] ?? ''),
            (string)(ProdFiche::PHASES[$l['phase'] ?? ''] ?? ($l['phase'] ?? '')),
            (string)($l['lieu'] ?? ''), (string)($l['ville'] ?? ''), (string)($l['pays'] ?? ''),
        ], $pl['dates'] ?? []),
    ]);
    $j = $pl['jours'] ?? [];
    if ($j) $ajout('Jours retenus', 'texte', implode(' · ', array_map(
        fn($x) => date('d.m.Y', strtotime((string)$x)), $j)));
    break;

case 'logistique':
    foreach (ProdFiche::LOGI as $cle => $lib)
        $ajout($lib, 'table', [
            'entete' => ['Quand', 'Qui', 'Quoi', 'De', 'À', 'Référence', 'Montant'],
            'lignes' => array_map(fn($l) => [
                (string)($l['quand'] ?? ''), (string)($l['qui'] ?? ''), (string)($l['libelle'] ?? ''),
                (string)($l['depart'] ?? ''), (string)($l['arrivee'] ?? ''),
                (string)($l['reference'] ?? ''),
                $mt($l['montant'] ?? '', (string)($l['devise'] ?? '')),
            ], $d['logistique'][$cle] ?? []),
        ]);
    break;

case 'technique':
    $t = $d['technique'] ?? [];
    foreach ([['Le plateau', 'plateau', ProdFiche::TECH_PLATEAU],
              ['Les temps',  'temps',   ProdFiche::TECH_TEMPS],
              ['Les besoins','besoins', ProdFiche::TECH_BESOINS]] as [$lib, $grp, $champs]) {
        $paires = [];
        foreach ($champs as $k => [$l, $_]) {
            $v = trim((string)($t[$grp][$k] ?? ''));
            if ($v !== '') $paires[$l] = $v;
        }
        $ajout($lib, 'defs', $paires);
    }
    $ajout('Adaptations possibles', 'texte', (string)($t['adaptations'] ?? ''));
    $c = $t['contact'] ?? [];
    $ajout('Contact technique', 'defs', array_filter([
        'Nom' => (string)($c['nom'] ?? ''), 'Rôle' => (string)($c['role'] ?? ''),
        'Courriel' => (string)($c['email'] ?? ''), 'Téléphone' => (string)($c['tel'] ?? ''),
    ], fn($v) => trim($v) !== ''));
    $vs = $t['versions'] ?? [];
    usort($vs, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
    $ajout('Versions du document', 'table', [
        'entete' => ['Version', 'Date', 'Configuration'],
        'lignes' => array_map(fn($v) => [
            (string)($v['version'] ?? ''), (string)($v['date'] ?? ''), (string)($v['config'] ?? ''),
        ], $vs),
    ]);
    /* Les notes internes ne s'impriment PAS: cet onglet part au lieu, et les
       notes disent où le spectacle ne rentre pas. */
    break;

case 'fdr':
    $txt = trim((string)($d['fdr']['texte'] ?? ''));
    if ($txt === '') $txt = ProdFiche::feuilleDeRoute($p, $d);
    $ajout('', 'texte', $txt);
    break;

case 'remuneration':
    $tot = 0.0; $dev = '';
    foreach ($d['remuneration'] ?? [] as $l) { $tot += (float)($l['montant'] ?? 0); $dev = $dev ?: (string)($l['devise'] ?? ''); }
    $ajout('Rémunérations convenues', 'table', [
        'entete' => ['Personne', 'Fonction', 'Contrat', 'Période', 'Jours', 'Montant'],
        'lignes' => array_map(fn($l) => [
            (string)($l['personne'] ?? ''), (string)($l['fonction'] ?? ''),
            (string)($l['contrat'] ?? ''), (string)($l['periode'] ?? ''),
            (string)($l['jours'] ?? ''), $mt($l['montant'] ?? '', (string)($l['devise'] ?? '')),
        ], $d['remuneration'] ?? []),
        'total'  => $tot > 0 ? ['Total', $mt($tot, $dev)] : null,
    ]);
    break;

case 'budget':
    $tx = ProdFiche::budgetTotaux($d);
    foreach ([['Dépenses', 'depense', ProdFiche::BUDGET_DEPENSE],
              ['Recettes', 'recette', ProdFiche::BUDGET_RECETTE]] as [$lib, $sens, $natures]) {
        $lignes = []; $s = 0.0; $dev = '';
        foreach ($d['budget'] ?? [] as $l) {
            if ((string)($l['sens'] ?? '') !== $sens) continue;
            $s += (float)($l['montant'] ?? 0); $dev = $dev ?: (string)($l['devise'] ?? '');
            $lignes[] = [(string)($natures[$l['nature'] ?? ''] ?? ($l['nature'] ?? '')),
                         (string)($l['libelle'] ?? ''), $mt($l['montant'] ?? '', (string)($l['devise'] ?? ''))];
        }
        $ajout($lib, 'table', ['entete' => ['Nature', 'Libellé', 'Montant'],
                               'lignes' => $lignes,
                               'total'  => $lignes ? ['Total ' . mb_strtolower($lib), $mt($s, $dev)] : null]);
    }
    if (isset($tx['solde'])) {
        $ajout('Solde', 'defs', ['Recettes moins dépenses' => $mt($tx['solde'] ?? 0)]);
    }
    break;

case 'devis':
    $dates = DB::all(
        "SELECT date_debut, date_texte, venue, ville, pays, prix_cession, devise, statut, representations
           FROM booking WHERE supprime_le IS NULL AND projet = ?
          ORDER BY COALESCE(date_debut,'9999-12-31')", [$titre]);
    $s = 0.0; $dev = '';
    foreach ($dates as $x) { $s += (float)$x['prix_cession']; $dev = $dev ?: (string)$x['devise']; }
    $ajout('Dates vendues', 'table', [
        'entete' => ['Date', 'Lieu', 'Ville', 'Pays', 'Repr.', 'État', 'Cession'],
        'lignes' => array_map(fn($x) => [
            $x['date_debut'] ? date('d.m.Y', strtotime((string)$x['date_debut'])) : (string)$x['date_texte'],
            (string)$x['venue'], (string)$x['ville'], (string)$x['pays'],
            (string)($x['representations'] ?: ''), (string)$x['statut'],
            $mt($x['prix_cession'], (string)$x['devise']),
        ], $dates),
        'total' => $dates ? ['Total des cessions', $mt($s, $dev)] : null,
    ]);
    break;

case 'droits':
    /* LA DÉCLARATION SSA. Elle porte l'ordre et les rubriques du formulaire de
       la Société Suisse des Auteurs, pour être recopiée ou jointe sans avoir à
       rechercher chaque valeur dans l'écran.

       CE QU'ELLE N'EST PAS, et il faut le dire ici plutôt que de le laisser
       découvrir: ce n'est pas le PDF officiel de la SSA rempli. Ce site n'a
       aucune bibliothèque PDF, et remplir un formulaire officiel demanderait
       le fichier de la SSA et ses champs. C'est une déclaration complète et
       signable qui contient exactement ce que la SSA demande. */
    $a = $d['droits']['auteurs'] ?? [];
    $tot = ProdFiche::droitsTotal($d);
    $ajout('Œuvre', 'defs', array_filter([
        'Titre'              => $titre,
        'Année de création'  => (string)($p['year_creation'] ?? ''),
        'Durée'              => $p['duration_min'] ? ((int)$p['duration_min']) . ' minutes' : '',
        'Association qui déclare' => $porteur,
    ], fn($v) => trim((string)$v) !== ''));

    $ajout('Répartition des droits', 'table', [
        'entete' => ['Auteur', 'Rôle', 'Société', 'Part'],
        'lignes' => array_map(fn($x) => [
            (string)($x['nom'] ?? ''), (string)($x['role'] ?? ''),
            (string)($x['societe'] ?? ''),
            rtrim(rtrim(number_format((float)($x['part'] ?? 0), 2, ',', ' '), '0'), ',') . ' %',
        ], $a),
        'total' => ['Total réparti',
                    rtrim(rtrim(number_format($tot, 2, ',', ' '), '0'), ',') . ' %'],
    ]);

    /* L'AVERTISSEMENT S'IMPRIME AUSSI, et c'est voulu: une déclaration à 90 %
       est refusée, et on s'en aperçoit des mois plus tard, quand les droits
       devaient tomber. Mieux vaut la voir sur la feuille qu'on signe. */
    if (abs($tot - 100.0) >= 0.01) {
        $ajout('Attention', 'texte',
            'Le partage fait ' . rtrim(rtrim(number_format($tot, 2, ',', ' '), '0'), ',')
            . ' % et non 100 %. Une déclaration qui ne fait pas exactement 100 % est refusée.');
    }

    $ajout('Contributions sans part déclarée', 'table', [
        'entete' => ['Nom', 'Rôle', 'Contribution'],
        'lignes' => array_map(fn($x) => [
            (string)($x['nom'] ?? ''), (string)($x['role'] ?? ''), (string)($x['contribution'] ?? ''),
        ], $d['droits']['cols'] ?? []),
    ]);
    $ajout('Éditeur',              'texte', (string)($d['droits']['editeur'] ?? ''));
    $ajout('Règle de répartition', 'texte', (string)($d['droits']['repartition'] ?? ''));
    $ajout('Notes',                'texte', (string)($d['droits']['notes'] ?? ''));
    break;
}
?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title><?= e($quoi) ?> — <?= e($titre) ?></title>
<style>
  body{margin:0;padding:26px;background:#fff;color:#111;
    font:13px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif}
  h1{font-size:21px;margin:0 0 2px;letter-spacing:-.01em}
  .st{color:#666;font-size:12.5px;margin:0 0 22px;padding-bottom:14px;border-bottom:1px solid #ddd}
  h2{font-size:11.5px;text-transform:uppercase;letter-spacing:.1em;color:#555;
    margin:24px 0 6px;font-weight:700}
  p.t{margin:0 0 12px;white-space:pre-wrap;max-width:88ch}
  pre.t{margin:0 0 12px;white-space:pre-wrap;font:inherit;max-width:88ch}
  dl{margin:0 0 12px;display:grid;grid-template-columns:auto 1fr;gap:3px 18px;max-width:88ch}
  dt{color:#666} dd{margin:0}
  table{border-collapse:collapse;width:100%;max-width:100%;margin:0 0 14px;font-size:12.5px}
  th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.07em;color:#666;
    border-bottom:1px solid #bbb;padding:4px 8px 4px 0;font-weight:700}
  td{padding:4px 8px 4px 0;border-bottom:1px solid #eee;vertical-align:top}
  tr.tot td{border-top:1px solid #bbb;border-bottom:0;font-weight:700}
  .imp{margin:0 0 20px;padding:8px 12px;background:#f3f3f1;font-size:12px}
  .vide{color:#888;font-style:italic}
  .sign{margin-top:34px;padding-top:12px;border-top:1px solid #ddd;display:grid;
    grid-template-columns:1fr 1fr;gap:30px;font-size:12px;color:#555}
  .sign .l{margin-top:34px;border-top:1px solid #999;padding-top:4px}
  @media print{ .imp{display:none} body{padding:0} @page{margin:16mm}
    h2{break-after:avoid} table{break-inside:auto} tr{break-inside:avoid} }
</style>
</head><body>
<p class="imp">Faites « Imprimer », puis « Enregistrer au format PDF ». Ce bandeau ne s'imprime pas.</p>

<h1><?= e($titre) ?></h1>
<p class="st"><?= e($quoi) ?><?= $porteur !== '' ? ' · ' . e($porteur) : '' ?>
   · Le Voisin · établi le <?= date('d.m.Y') ?></p>

<?php if (!$blocs): ?>
  <p class="vide">Cet onglet est encore vide. Ce qui y sera saisi s'imprimera ici.</p>
<?php endif; ?>

<?php foreach ($blocs as $b): ?>
  <?php if ($b['t'] !== ''): ?><h2><?= e($b['t']) ?></h2><?php endif; ?>

  <?php if ($b['type'] === 'texte'): ?>
    <pre class="t"><?= e((string)$b['d']) ?></pre>

  <?php elseif ($b['type'] === 'defs'): ?>
    <dl><?php foreach ($b['d'] as $k => $v): ?>
      <dt><?= e((string)$k) ?></dt><dd><?= e((string)$v) ?></dd>
    <?php endforeach; ?></dl>

  <?php elseif ($b['type'] === 'table'): ?>
    <table>
      <thead><tr><?php foreach ($b['d']['entete'] as $h): ?><th><?= e((string)$h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($b['d']['lignes'] as $l): ?>
        <tr><?php foreach ($l as $c): ?><td><?= e((string)$c) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if (!empty($b['d']['total'])): ?>
        <tr class="tot">
          <td colspan="<?= max(1, count($b['d']['entete']) - 1) ?>"><?= e((string)$b['d']['total'][0]) ?></td>
          <td><?= e((string)$b['d']['total'][1]) ?></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endforeach; ?>

<?php /* LA ZONE DE SIGNATURE N'EST PAS DÉCORATIVE. Une déclaration de droits
     part signée à la SSA, et une fiche technique validée par la régie évite la
     discussion sur place. Les autres onglets n'en ont pas besoin. */ ?>
<?php if (in_array($onglet, ['droits', 'technique'], true)): ?>
  <div class="sign">
    <div><?= $onglet === 'droits' ? 'Pour l\'association' : 'Direction technique' ?>
      <div class="l">Nom, date et signature</div></div>
    <div><?= $onglet === 'droits' ? 'L\'auteur ou son représentant' : 'Le lieu' ?>
      <div class="l">Nom, date et signature</div></div>
  </div>
<?php endif; ?>

</body></html>
