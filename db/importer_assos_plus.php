<?php
/**
 * Compléter les associations depuis `lv-artists`. [17.08.2026]
 *
 *   php db/importer_assos_plus.php [--ecrire]
 *
 * Anna: « os dados completos (…) das associacoes ». La première reprise avait
 * lu `lv-assoc-fiches`; `lv-artists` porte neuf lignes de plus, avec quatre
 * champs qu'aucune autre table n'a.
 *
 * LA TABLE S'APPELLE « artists » ET NE CONTIENT PAS D'ARTISTES. Ses neuf
 * lignes sont « Association Watering Hole », « Association Crile », « Flux
 * Poreux » — avec `stype: association`, un IDE, une adresse de siège et une
 * direction artistique. C'est le même piège de vocabulaire que les tables du
 * site en anglais et celles du dashboard en français: on cherche le mot, on ne
 * trouve rien, on conclut que la donnée n'existe pas. Elle existait.
 *
 * CE QU'ELLE APPORTE, et pourquoi chacun compte:
 *
 *   `disc`         la discipline. Anna l'a demandée nommément sur la liste des
 *                  associations — « mostrar nome, direction, ville, canton,
 *                  discipline, statut » — et la colonne s'affichait vide sur
 *                  les dix-huit. Une colonne demandée qui n'affiche rien fait
 *                  croire que la donnée n'existe nulle part.
 *   `debutCollab`  l'année où la collaboration a commencé. C'est ce qui permet
 *                  de dire « on travaille ensemble depuis 2020 » dans un
 *                  dossier, et personne ne s'en souvient à cinq ans près.
 *   `tva`          l'assujettissement. « pas soumis à la TVA » sur les neuf.
 *                  Se trompe de sens une fois et c'est une facture à refaire.
 *   `bankNom`      le titulaire du compte, qui n'est PAS le nom de
 *                  l'association: Watering Hole encaisse sur « Captains of the
 *                  Imagination », Hibiscus sur « Marvin M'toumo ». Un virement
 *                  au mauvais titulaire revient trois semaines plus tard.
 *
 * L'ANNÉE SEULE DEVIENT LE 1ᵉʳ JANVIER, et il faut le savoir en la relisant:
 * le dashboard ne stocke que « 2022 ». Écrire 2022-01-01 est la seule lecture
 * possible d'une année seule, mais ce n'est pas une date mesurée — c'est une
 * année à laquelle on a mis un jour pour qu'elle rentre dans une colonne DATE.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--ecrire', $argv, true);
$src    = null;
foreach (array_slice($argv, 1) as $a) if ($a !== '--ecrire') { $src = $a; break; }
$src ??= getenv('HOME') . '/export.json';

if (!is_file($src)) { fwrite(STDERR, "Export introuvable: $src\n"); exit(1); }
$export = json_decode((string)file_get_contents($src), true);
if (!is_array($export)) { fwrite(STDERR, "Export illisible.\n"); exit(1); }

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

/** Les disciplines du dashboard, en toutes lettres. */
const DISC = [
    'music'        => 'Musique',
    'dance'        => 'Danse',
    'theatre'      => 'Théâtre',
    'marionette'   => 'Marionnettes',
    'visual-arts'  => 'Arts visuels',
    'circus'       => 'Cirque',
    'performance'  => 'Performance',
    'pluri'        => 'Pluridisciplinaire',
];

function norm(string $s): string {
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à'=>'a','â'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i',
                    'ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','&'=>'et']);
    $s = preg_replace('/\b(association|verein|asso)\b/', '', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/', ' ', $s) ?? '');
}

$assos = [];
foreach (DB::all("SELECT * FROM organisation WHERE supprime_le IS NULL") as $o) {
    foreach ([$o['nom'], $o['nom_legal']] as $n)
        if ($n !== null && trim((string)$n) !== '') $assos[norm((string)$n)] ??= $o;
}

$faits = $sansCible = 0;

foreach (array_values(is_array($export['lv-artists'] ?? null) ? $export['lv-artists'] : []) as $a) {
    $nom = trim((string)($a['name'] ?? $a['sname'] ?? ''));
    if ($nom === '') continue;

    $o = $assos[norm($nom)] ?? null;
    if (!$o) {
        foreach ($assos as $k => $v) {
            if ($k !== '' && (str_contains($k, norm($nom)) || str_contains(norm($nom), $k))) { $o = $v; break; }
        }
    }
    if (!$o) {
        printf("  ✗ %-34s aucune association de ce nom — LAISSÉE\n", mb_substr($nom, 0, 34));
        $sansCible++;
        continue;
    }

    $maj = [];

    $disc = trim((string)($a['disc'] ?? ''));
    if ($disc !== '' && trim((string)$o['discipline']) === '')
        $maj['discipline'] = DISC[$disc] ?? $disc;

    $debut = trim((string)($a['debutCollab'] ?? ''));
    if (preg_match('/^(19|20)\d{2}$/', $debut) && !$o['debut_collab'])
        $maj['debut_collab'] = "$debut-01-01";

    $bank = trim((string)($a['bankNom'] ?? ''));
    if ($bank !== '' && trim((string)$o['banque_nom']) === '')
        $maj['banque_nom'] = $bank;

    /* La TVA est une phrase à la source et un oui/non ici. On ne reconnaît que
       les deux formulations rencontrées, et on DIT ce qu'on ne reconnaît pas:
       deviner « assujetti » sur une phrase inattendue mettrait 8,1 % sur des
       factures qui n'en portent pas. */
    $tva = mb_strtolower(trim((string)($a['tva'] ?? '')));
    if ($tva !== '' && (string)$o['tva_ch'] === 'non') {
        if (str_contains($tva, 'pas soumis') || str_contains($tva, 'non soumis')) {
            /* déjà « non » en base: rien à écrire, et c'est le bon état */
        } elseif (str_contains($tva, 'soumis') || str_contains($tva, 'assujetti')) {
            $maj['tva_ch'] = 'oui';
        } else {
            printf("      ? TVA « %s » non reconnue sur %s — laissée à « non »\n",
                   mb_substr($tva, 0, 30), mb_substr($nom, 0, 20));
        }
    }

    $statut = trim((string)($a['statut'] ?? ''));
    if ($statut === 'active' && (string)$o['statut'] !== 'actif') $maj['statut'] = 'actif';

    /* LA DIRECTION ARTISTIQUE N'EST PAS REPRISE, ET C'EST LE POINT LE PLUS
       IMPORTANT DE CE FICHIER. La colonne `da` de `lv-artists` est décalée:

           Annina Mosimann      → « Louis Matute »
           Association Crile    → « Simone Repele & Sasha Riva »   (Riva & Repele)
           Riva & Repele        → « Marc Crofts »
           Flux Poreux          → « Mirta / Alessandra »           (le bureau)
           Louis Matute         → « Sami Bernath »                 (Flux Poreux)

       Aucune des neuf n'est juste, et le décalage n'est même pas régulier —
       on ne peut donc pas le corriger d'un cran. C'est la même maladie que
       `lv-prods.artistId`, qui donne Louis Matute pour les treize projets de
       l'Encontro.

       UN LIEN FAUX A L'AIR D'UNE DONNÉE VÉRIFIÉE, et c'est ce qui le rend
       coûteux: personne ne rouvre un champ rempli. Écrire « Sami Bernath » en
       direction artistique de Louis Matute, sur une fiche qui part en dossier
       de subvention, se corrige devant le jury ou pas du tout.

       Les directions justes sont DÉJÀ EN BASE — treize sur dix-huit, dictées
       par Anna le 16.08.2026. Ce fichier ne les touche pas, même là où le
       champ est vide: vide et faux ne sont pas deux états voisins, et le vide
       se voit. */

    if (!$maj) continue;
    printf("  · %-34s %s\n", mb_substr((string)$o['nom'], 0, 34),
           implode(', ', array_map(fn($k, $v) => "$k=" . mb_substr((string)$v, 0, 18),
                                   array_keys($maj), $maj)));
    $faits++;
    if ($ecrire) DB::update('organisation', $maj, 'id = ?', [(int)$o['id']]);
}

printf("\n  %d association(s) complétée(s)", $faits);
if ($sansCible) printf(" · %d sans correspondance", $sansCible);
echo "\n";
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";
