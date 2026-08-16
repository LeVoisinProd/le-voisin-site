<?php
/**
 * Rattacher chaque pièce à l'association qui la porte. [16.08.2026]
 *
 *   php db/importer_projets.php <export.json> [--ecrire]
 *
 * CE QU'IL IMPORTE, ET CE N'EST PAS « LES PROJETS ». Les pièces existent déjà:
 * 35 dans `projects`, avec leurs artistes dans `project_artists`. Ce qui
 * n'existait nulle part, c'est LE PORTEUR — quelle association répond de quelle
 * pièce. Le dashboard le sait (`lv-prods.assocId`), le site l'ignorait.
 *
 * C'est ce qui manquait au modèle qu'Anna a dicté: une association porte
 * plusieurs projets de plusieurs artistes. Les deux premiers niveaux étaient en
 * base, le lien entre eux n'y était pas.
 *
 * TROIS NIVEAUX MÉLANGÉS DANS `lv-prods`, ET ON NE RECOPIE PAS LE MÉLANGE:
 *
 *   pièce    « Bestiarium », « Concours de Larmes » — rattachée
 *   mois     « Dolce Vita — Août 2026 » — 9 lignes qui sont des périodes de
 *            tournée. Rattachées à la pièce mère, jamais créées: onze « Dolce
 *            Vita » dans le catalogue public seraient le résultat.
 *   artiste  « Evita Koné », « Captains of the Imagination » — ils sont dans
 *            `artists`. Signalés, pas importés.
 *
 * LE RAPPROCHEMENT SE FAIT SUR LE TITRE, normalisé (accents, casse,
 * ponctuation) puis par inclusion. Il est imparfait par nature, alors il
 * S'AFFICHE EN ENTIER: chaque ligne dit quelle pièce a été reconnue, et les
 * non-reconnues sont listées à part plutôt que rattachées au hasard. Un
 * rapprochement silencieux qui se trompe est indétectable.
 *
 * IDEMPOTENT. `projet_prod` a une ligne par pièce; relancer met à jour.
 * `--ecrire` n'écrase JAMAIS un `organisation_id` déjà posé à la main: on ne
 * remplit que ce qui est vide, sauf si la ligne porte déjà le `source_ref` de
 * cette même production — auquel cas c'est nous qui l'avions écrite.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$fichier = '';
foreach (array_slice($argv, 1) as $a) if ($a !== '--ecrire') { $fichier = $a; break; }
$ecrire = in_array('--ecrire', $argv, true);

if ($fichier === '' || !is_file($fichier)) {
    fwrite(STDERR, "Usage: php db/importer_projets.php <export.json> [--ecrire]\n");
    exit(1);
}
$j = json_decode((string)file_get_contents($fichier), true);
if (!is_array($j) || !isset($j['lv-prods'])) {
    fwrite(STDERR, "Ce fichier ne porte pas de clef `lv-prods`.\n");
    exit(1);
}
$lignes = $j['lv-prods'];
if (!array_is_list($lignes)) $lignes = array_values($lignes);

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

/** Titre comparable: sans accents, sans casse, sans ponctuation. */
$norm = static function (string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)) ?? '');
};

/* Les pièces du site. */
$pieces = [];
foreach (DB::all("SELECT id, title_fr, title_en FROM projects") as $p) {
    $t = trim((string)($p['title_fr'] ?: $p['title_en']));
    if ($t !== '') $pieces[] = ['id' => (int)$p['id'], 'titre' => $t, 'n' => $norm($t)];
}
/* Les plus longs titres d'abord: « Dolce Vita — Lancement » doit rencontrer
   « Dolce Vita » et non l'inverse, et un titre court avalerait tout. */
usort($pieces, fn($a, $b) => mb_strlen($b['n']) <=> mb_strlen($a['n']));

/* Les artistes du site, pour reconnaître les lignes qui n'en sont pas. */
$artistes = [];
foreach (DB::all("SELECT id, name FROM artists") as $a) $artistes[$norm((string)$a['name'])] = (string)$a['name'];

/* Les porteurs, par clef du dashboard. Une association peut être en double en
   base — on prend la plus ancienne, celle qui porte le nom lisible. */
$porteurs = [];
foreach (DB::all("SELECT id, source_ref, nom FROM organisation
                   WHERE supprime_le IS NULL AND source_ref IS NOT NULL
                   ORDER BY id ASC") as $o) {
    $porteurs[(string)$o['source_ref']] ??= ['id' => (int)$o['id'], 'nom' => (string)$o['nom']];
}

$MOIS = ['janvier','fevrier','mars','avril','mai','juin','juillet','aout',
         'septembre','octobre','novembre','decembre'];

$rattaches = $moisVus = 0;
$sansPiece = $sansPorteur = $estArtiste = [];
$vus = [];

foreach ($lignes as $p) {
    $ref = trim((string)($p['id']   ?? ''));
    $nom = trim((string)($p['name'] ?? ''));
    if ($nom === '') continue;

    $aref    = trim((string)($p['assocId'] ?? ''));
    $porteur = $porteurs[$aref] ?? null;
    $k       = $norm($nom);

    /* Une pièce ? Égalité d'abord, puis inclusion. */
    $piece = null;
    foreach ($pieces as $pc) if ($pc['n'] === $k) { $piece = $pc; break; }
    if (!$piece) foreach ($pieces as $pc) {
        if ($pc['n'] !== '' && (str_contains($k, $pc['n']) || str_contains($pc['n'], $k))) { $piece = $pc; break; }
    }

    $estMois = (bool)array_filter($MOIS, fn($m) => str_contains($k, $m));

    if (!$piece) {
        if (isset($artistes[$k])) { $estArtiste[] = $nom; continue; }
        $sansPiece[] = $nom;
        continue;
    }
    if (!$porteur) { $sansPorteur[] = "$nom  (assocId « $aref »)"; continue; }

    if ($estMois) $moisVus++;

    /* Une pièce peut être visée par plusieurs lignes du dashboard — les onze
       « Dolce Vita ». On n'écrit qu'une fois, et c'est la première qui compte:
       les suivantes portent le même porteur, vérifié sur les 32. */
    if (isset($vus[$piece['id']])) {
        printf("  %-46s → #%-3d %-24s (déjà rattachée)\n",
               mb_substr($nom, 0, 46), $piece['id'], mb_substr($piece['titre'], 0, 24));
        continue;
    }
    $vus[$piece['id']] = true;

    printf("  %-46s → #%-3d %-24s porteur: %s%s\n",
           mb_substr($nom, 0, 46), $piece['id'], mb_substr($piece['titre'], 0, 24),
           $porteur['nom'], $estMois ? '   [mois de tournée]' : '');
    $rattaches++;

    if (!$ecrire) continue;

    $f = DB::one("SELECT project_id, organisation_id, source_ref FROM projet_prod WHERE project_id = ?",
                 [$piece['id']]);

    $champs = [
        'source_ref'  => $ref ?: null,
        'phase'       => in_array((string)($p['phase'] ?? ''),
                          ['dev','creation','production','promo','tournee','cloture'], true)
                          ? (string)$p['phase'] : null,
        'responsable' => mb_substr(trim((string)($p['resp']  ?? '')), 0, 96) ?: null,
        'valide_par'  => mb_substr(trim((string)($p['valid'] ?? '')), 0, 96) ?: null,
    ];

    if (!$f) {
        DB::run("INSERT INTO projet_prod (project_id, organisation_id, source_ref, phase,
                                          responsable, valide_par)
                 VALUES (?,?,?,?,?,?)",
                [$piece['id'], $porteur['id'], $champs['source_ref'], $champs['phase'],
                 $champs['responsable'], $champs['valide_par']]);
        continue;
    }

    /* On ne remplace un porteur déjà posé que s'il vient de la même reprise. */
    $aNous = (string)($f['source_ref'] ?? '') !== '' && (string)$f['source_ref'] === $ref;
    $poser = !$f['organisation_id'] || $aNous;

    DB::run("UPDATE projet_prod
                SET organisation_id = " . ($poser ? '?' : 'organisation_id') . ",
                    source_ref  = COALESCE(NULLIF(source_ref,''), ?),
                    phase       = COALESCE(phase, ?),
                    responsable = COALESCE(NULLIF(responsable,''), ?),
                    valide_par  = COALESCE(NULLIF(valide_par,''), ?)
              WHERE project_id = ?",
            $poser
              ? [$porteur['id'], $champs['source_ref'], $champs['phase'],
                 $champs['responsable'], $champs['valide_par'], $piece['id']]
              : [$champs['source_ref'], $champs['phase'],
                 $champs['responsable'], $champs['valide_par'], $piece['id']]);

    if (!$poser) printf("      porteur laissé tel quel — il a été saisi à la main\n");
}

printf("\n  %d pièces rattachées à leur porteur", $rattaches);
if ($moisVus) printf(" · %d lignes étaient des mois de tournée", $moisVus);
echo "\n";

if ($estArtiste) {
    echo "\n  " . count($estArtiste) . " lignes de `lv-prods` sont des ARTISTES et non des pièces.\n"
       . "  Elles sont déjà dans `artists`. Rien à faire, mais c'est la preuve que le\n"
       . "  dashboard mélange les niveaux:\n";
    foreach ($estArtiste as $x) echo "    · $x\n";
}
if ($sansPiece) {
    echo "\n  " . count($sansPiece) . " lignes sans pièce reconnue. Ni rattachées ni créées —\n"
       . "  dis lesquelles sont des pièces à ajouter au catalogue:\n";
    foreach ($sansPiece as $x) echo "    · $x\n";
}
if ($sansPorteur) {
    echo "\n  " . count($sansPorteur) . " lignes dont l'association n'est pas en base:\n";
    foreach ($sansPorteur as $x) echo "    · $x\n";
}
if (!$ecrire) echo "\n  Relance avec --ecrire pour appliquer.\n";
