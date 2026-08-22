<?php
/**
 * La fiche d'une association, en version imprimable.  [Anna, 22.08.2026]
 *
 * « na página principal de cada association criar o botão de impressão em pdf
 * de todas as informações da associação ».
 *
 * TOUT, ET NON CE QUE L'ÉCRAN MONTRE. La fiche à l'écran affiche l'identité et
 * les coordonnées; les assurances, l'AVS et les deux fiscalités vivent dans des
 * onglets qu'il faut ouvrir un par un. Le document, lui, sert à emporter la
 * fiche entière — chez le fiduciaire, à la caisse, chez l'assureur — et c'est
 * précisément ce qu'on ne peut pas faire aujourd'hui.
 *
 * DEUX CHOSES N'Y SONT PAS, ET C'EST DÉLIBÉRÉ: le jeton bexio et les mots de
 * passe (`email_mdp`, `instagram_mdp`). Un jeton bexio ouvre la comptabilité
 * entière d'une association; ils n'ont rien à faire sur un papier qui traîne sur
 * un bureau ou part en pièce jointe. Ils ne sont pas non plus déchiffrés ici.
 *
 * MÊME PARTI QUE PARTOUT SUR CE SITE: aucune bibliothèque PDF — vérifié le
 * 16.08, ni FPDF, ni TCPDF, ni Dompdf — et le navigateur sait faire un PDF d'une
 * page. Un vrai PDF, sélectionnable et cherchable, pas une image.
 *
 * LA PAGE EST NUE: ni menu, ni onglets, ni bouton. Ce qui reste à l'écran
 * s'imprime, et rien d'autre.
 *
 * UNE LIGNE VIDE NE S'IMPRIME PAS, comme sur la fiche. Les treize fiches
 * suisses n'ont ni RNA ni URSSAF, et une page de tirets se lit moins bien
 * qu'une page courte.
 *
 * Attend $o (la ligne organisation), $GENRES, $STATUTS, $GESTIONS.
 * L'exercice se lit dans l'adresse: la fiche n'a pas de `$annee` en portée à
 * cet endroit — vérifié au test, pas en relisant.
 */
declare(strict_types=1);
/** @var array $o */ /** @var array $GENRES */ /** @var array $STATUTS */
/** @var array $GESTIONS */

$annee = (int)($_GET['an'] ?? date('Y'));
if ($annee < 2000 || $annee > 2100) $annee = (int)date('Y');

$OUI = static fn($v): string => (string)$v === 'oui' ? 'oui' : ((string)$v === 'non' ? 'non' : '');

/* Les sections, dans l'ordre des onglets de la fiche. Chaque entrée est
   « intitulé => valeur »; les valeurs vides tombent au rendu. */
$SECTIONS = [
    'Identité' => [
        'Nom'                    => $o['nom'] ?? '',
        'Nom légal'              => $o['nom_legal'] ?? '',
        'Genre'                  => $GENRES[$o['genre'] ?? ''] ?? '',
        'Statut'                 => $STATUTS[$o['statut'] ?? ''] ?? '',
        'Ce que nous faisons'    => $GESTIONS[$o['gestion'] ?? ''] ?? ($o['gestion'] ?? ''),
        'Forme juridique'        => $o['forme_juridique'] ?? '',
        'Date de création'       => $o['date_creation'] ?? '',
        'Discipline'             => $o['discipline'] ?? '',
        'Direction artistique'   => $o['direction'] ?? '',
        'Début de collaboration' => $o['debut_collab'] ?? '',
        'Comité'                 => $o['comite'] ?? '',
    ],
    'Identifiants' => [
        'IDE'            => $o['ide'] ?? '',
        'Registre'       => $o['registre'] ?? '',
        'AVS employeur'  => $o['avs_employeur'] ?? '',
        'REE'            => $o['ree'] ?? '',
        'Référence poste'=> $o['reference_poste'] ?? '',
        'SIRET'          => $o['siret'] ?? '',
        'RNA'            => $o['rna'] ?? '',
        'URSSAF'         => $o['urssaf'] ?? '',
        'Audiens'        => $o['audiens'] ?? '',
    ],
    'Adresse et contact' => [
        'Chez'      => $o['chez'] ?? '',
        'Adresse'   => $o['adresse'] ?? '',
        'CP · Ville'=> trim(((string)($o['cp'] ?? '')) . ' ' . ((string)($o['ville'] ?? ''))),
        'Pays'      => trim(((string)($o['pays'] ?? '')) . ((string)($o['canton'] ?? '') !== '' ? ' · ' . $o['canton'] : '')),
        'Contact'   => trim(((string)($o['contact_prenom'] ?? '')) . ' ' . ((string)($o['contact_nom'] ?? ''))),
        'Courriel'  => $o['email'] ?? '',
        'Téléphone' => $o['telephone'] ?? '',
        'Site'      => $o['site'] ?? '',
        'Instagram' => $o['instagram'] ?? '',
    ],
    'Banque' => [
        'Établissement'   => $o['banque_nom'] ?? '',
        'IBAN'            => $o['banque_iban'] ?? '',
        'BIC'             => $o['banque_bic'] ?? '',
        'Devise'          => $o['devise_defaut'] ?? '',
        'Frais de booking'=> $o['frais_booking'] ?? '',
        'Marge par défaut'=> $o['marge_defaut'] ?? '',
    ],
    'Assurances — LAA · LPP · AMPG' => [
        'RC professionnelle' => $o['rc_pro'] ?? '',
        'N° de police RC'    => $o['rc_police'] ?? '',
        'LAA'                => $o['laa'] ?? '',
        'Assureur LAA'       => $o['assureur_laa'] ?? '',
        'LPP (2e pilier)'    => $o['lpp'] ?? '',
        'Assureur LPP'       => $o['assureur_lpp'] ?? '',
        'AMPG'               => $o['ampg'] ?? '',
        'N° Trianon'         => $o['trianon'] ?? '',
        'Notes'              => $o['notes_laa'] ?? '',
    ],
    'AVS' => [
        'Inscription'            => $o['avs_inscription'] ?? '',
        'Caisse'                 => $o['caisse_avs'] ?? '',
        'Convention collective'  => $o['convention_coll'] ?? '',
        'Notes'                  => $o['notes_avs'] ?? '',
    ],
    'Fiscalité suisse' => [
        'Canton fiscal'          => $o['canton_fiscal'] ?? '',
        'Contribuable cantonal'  => $o['contribuable_cant'] ?? '',
        'TVA'                    => $OUI($o['tva_ch'] ?? '') === 'oui' ? 'assujettie' : '',
        'N° TVA'                 => $o['tva_ch_num'] ?? '',
        'Notes'                  => $o['notes_fisc_ch'] ?? '',
    ],
    'Fiscalité française' => [
        'TVA'    => $OUI($o['tva_fr'] ?? '') === 'oui' ? 'assujettie' : '',
        'N° TVA' => $o['tva_fr_num'] ?? '',
        'Notes'  => $o['notes_fisc_fr'] ?? '',
    ],
    'Notes' => [
        'Notes' => $o['notes'] ?? '',
    ],
];

/* ── LES DÉCLARATIONS DE L'EXERCICE ─────────────────────────────────────────
   Elles sont la moitié du travail administratif de l'année et n'apparaissent
   nulle part ailleurs sur papier. On n'imprime que ce qui a un état: une grille
   de seize cases vides ne dit rien. */
/* La colonne s'appelle `statut`, et le type est une énumération à deux valeurs,
   `laa` et `avs`. Écrit `etat` de mémoire au premier jet: la page tombait en
   « Unknown column 'etat' ». On lit la table, on ne la devine pas. */
$ETATS = ['a_faire' => 'à faire', 'envoye' => 'envoyé', 'paye' => 'payé', 'sans_objet' => 'sans objet'];
$TYPES = ['laa' => 'LAA · LPP · AMPG', 'avs' => 'AVS'];
$decl  = [];
foreach (DB::all('SELECT type, periode, statut, note FROM organisation_declaration
                   WHERE organisation_id = ? AND annee = ? ORDER BY type, periode',
                 [(int)$o['id'], $annee]) as $r) {
    $decl[] = [
        $TYPES[(string)$r['type']] ?? (string)$r['type'],
        (string)$r['periode'],
        $ETATS[(string)$r['statut']] ?? (string)$r['statut'],
        (string)($r['note'] ?? ''),
    ];
}

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e((string)$o['nom']) ?> — fiche association</title>
<style>
  @page { margin: 16mm 14mm; }
  * { box-sizing: border-box; }
  body { margin:0; padding:26px 30px 40px;
         font:14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
         color:#141414; background:#fff; }
  h1 { font-size:22px; margin:0 0 2px; }
  .sst { color:#666; font-size:13px; margin:0 0 22px; }
  h2 { font-size:13px; text-transform:uppercase; letter-spacing:.07em; color:#666;
       margin:22px 0 8px; padding-bottom:5px; border-bottom:1px solid #d8d8d4;
       /* Un titre ne se laisse pas seul en bas de page. */
       break-after:avoid; page-break-after:avoid; }
  dl { display:grid; grid-template-columns:190px 1fr; gap:5px 16px; margin:0; }
  dt { color:#666; font-size:13px; }
  dd { margin:0; }
  dd.long { white-space:pre-wrap; }
  section { break-inside:avoid; page-break-inside:avoid; }
  table { border-collapse:collapse; width:100%; font-size:13px; margin-top:4px; }
  th, td { text-align:left; padding:5px 10px 5px 0; border-bottom:1px solid #e6e6e2; }
  th { color:#666; font-weight:600; font-size:11.5px; text-transform:uppercase; letter-spacing:.05em; }
  footer { margin-top:26px; padding-top:10px; border-top:1px solid #d8d8d4;
           color:#8a8a8a; font-size:11.5px; }
</style>
</head>
<body>

<h1><?= e((string)$o['nom']) ?></h1>
<p class="sst"><?= e(trim(implode(' · ', array_filter([
      $GENRES[$o['genre'] ?? ''] ?? '',
      $STATUTS[$o['statut'] ?? ''] ?? '',
      trim(((string)($o['pays'] ?? '')) . ((string)($o['canton'] ?? '') !== '' ? ' · ' . $o['canton'] : '')),
   ])))) ?></p>

<?php foreach ($SECTIONS as $titre => $lignes):
    $lignes = array_filter($lignes, static fn($v) => trim((string)$v) !== '');
    if (!$lignes) continue; ?>
  <section>
    <h2><?= e($titre) ?></h2>
    <dl>
      <?php foreach ($lignes as $k => $v): ?>
        <dt><?= e((string)$k) ?></dt>
        <dd class="<?= strpos((string)$v, "\n") !== false ? 'long' : '' ?>"><?= e((string)$v) ?></dd>
      <?php endforeach; ?>
    </dl>
  </section>
<?php endforeach; ?>

<?php if ($decl): ?>
  <section>
    <h2>Déclarations <?= (int)$annee ?></h2>
    <table>
      <thead><tr><th>Déclaration</th><th>Période</th><th>État</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($decl as $r): ?>
        <tr><?php foreach ($r as $c): ?><td><?= e((string)$c) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>

<footer>
  Le Voisin — fiche imprimée le <?= date('d.m.Y') ?>.
  Les identifiants bancaires et fiscaux qui figurent ici sont confidentiels.
  Le jeton bexio et les mots de passe ne sont volontairement pas imprimés.
</footer>

</body>
</html>
<?php return;
