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
 * ON N'EN REPREND QU'UNE COLONNE SUR SIX, ET C'EST LE SUJET DE CE FICHIER.
 *
 * La table est DÉCALÉE, sur plusieurs colonnes à la fois, et le décalage n'est
 * pas régulier — donc pas rattrapable d'un cran. Constaté le 17.08.2026 en la
 * confrontant aux dix-huit associations de la production, dont les directions
 * ont été dictées par Anna la veille:
 *
 *   `da`        « Annina Mosimann → Louis Matute », « Crile → Simone Repele &
 *               Sasha Riva », « Flux Poreux → Mirta / Alessandra ». Les noms
 *               sont VRAIS et appartiennent tous au roster: ils sont juste
 *               accrochés à la mauvaise association. Une sur neuf est juste.
 *   `saddress`  la ligne « Annina Mosimann » porte ℅ ARROI, Rue des
 *               Vieux-Grenadiers 10, 1205 Genève — qui est le siège de
 *               l'ENCONTRO — pendant que son `canton` dit BE, qui est celui de
 *               Gran Chicornia. Deux colonnes de la même ligne désignent deux
 *               associations différentes.
 *   `bankNom`   n'est pas un titulaire de compte: c'est la direction
 *               artistique. Crile → Lorena Dozio, Hibiscus → Marvin M'toumo,
 *               Flux Poreux → Sami Bernath. L'écrire dans `banque_nom`
 *               mettrait un nom de personne là où un virement va chercher un
 *               titulaire, et le virement reviendrait trois semaines plus tard.
 *
 * UNE DONNÉE FAUSSE A L'AIR D'UNE DONNÉE VÉRIFIÉE, et c'est ce qui la rend
 * chère: personne ne rouvre un champ rempli. C'est déjà le cas de
 * `lv-prods.artistId`, qui donne Louis Matute pour les treize projets de
 * l'Encontro. Le champ vide, lui, se voit.
 *
 * CE QUI EST REPRIS: `disc`, et rien d'autre. Les neuf disciplines ont été
 * vérifiées une à une contre ce qu'on sait des pièces — Bestiarium est bien
 * des marionnettes, Dolce Vita bien de la musique, Dear Son bien de la danse —
 * et les neuf sont justes. Anna l'a demandée nommément sur la liste des
 * associations, et la colonne s'affichait vide sur les dix-huit: une colonne
 * demandée qui n'affiche rien fait croire que la donnée n'existe nulle part.
 *
 * CE QUI NE L'EST PAS ET QUI ATTEND UNE CONFIRMATION D'ANNA: `debutCollab`.
 * Sept années — 2020 à 2026 — qui seraient utiles (« on travaille ensemble
 * depuis 2020 » se met dans un dossier) et qu'AUCUN recoupement ne permet de
 * vérifier, sur une table dont trois colonnes sur six se sont révélées
 * décalées. Elles sont IMPRIMÉES ici plutôt qu'écrites: trente secondes de
 * lecture d'Anna les transforment en donnée sûre, et d'ici là la colonne
 * `organisation.debut_collab` reste vide, ce qui est honnête.
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

/* Deux lignes sont nommées d'après l'ARTISTE et non d'après son association.
   Le rapprochement par le nom ne peut pas les trouver, et il ne faut pas qu'il
   invente: on les nomme ici.

   La discipline reste juste dans les deux sens, ce qui est la seule chose
   qu'on reprend: Gran Chicornia est l'association d'Annina Mosimann et fait
   des marionnettes; l'Encontro est celle de Louis Matute et fait de la
   musique. Les autres colonnes de ces deux lignes sont justement celles qui se
   sont révélées décalées — la ligne « Annina Mosimann » porte l'adresse de
   l'Encontro — et elles ne sont pas reprises. */
const CORRESPONDANCES = [
    'annina mosimann' => 'gran chicornia',
    'louis matute'    => 'encontro',
];

$faits = $sansCible = 0;
$aConfirmer = [];

foreach (array_values(is_array($export['lv-artists'] ?? null) ? $export['lv-artists'] : []) as $a) {
    $nom = trim((string)($a['name'] ?? $a['sname'] ?? ''));
    if ($nom === '') continue;

    $k = CORRESPONDANCES[norm($nom)] ?? norm($nom);
    $o = $assos[$k] ?? null;
    /* Le repli par inclusion ne sert qu'à « Riva & Repele Balletto », dont le
       dashboard garde le nom long. Il boucle sur une variable À LUI: écrasait
       `$k` la première fois, donc la correspondance nommée juste au-dessus se
       perdait dès qu'un repli était tenté. */
    if (!$o) {
        foreach ($assos as $kk => $v) {
            if ($kk !== '' && (str_contains($kk, $k) || str_contains($k, $kk))) { $o = $v; break; }
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

    /* L'année de début, imprimée et non écrite — voir l'en-tête. */
    $debut = trim((string)($a['debutCollab'] ?? ''));
    if (preg_match('/^(19|20)\d{2}$/', $debut) && !$o['debut_collab'])
        $aConfirmer[] = sprintf('%-24s %s', mb_substr((string)$o['nom'], 0, 24), $debut);

    /* La TVA: « pas soumis à la TVA » sur les neuf, et c'est déjà l'état en
       base. Rien à écrire. Une formulation inattendue est DITE et non
       interprétée — deviner « assujetti » mettrait 8,1 % sur des factures qui
       n'en portent pas. */
    $tva = mb_strtolower(trim((string)($a['tva'] ?? '')));
    if ($tva !== '' && !str_contains($tva, 'pas soumis') && !str_contains($tva, 'non soumis'))
        printf("      ? TVA « %s » sur %s — non reprise, à vérifier\n",
               mb_substr($tva, 0, 34), mb_substr($nom, 0, 20));

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

if ($aConfirmer) {
    echo "\n  ── À CONFIRMER PAR ANNA: l'année de début de collaboration ──\n";
    echo "     Elle vient de `lv-artists`, dont trois colonnes sur six se sont\n";
    echo "     révélées décalées. Rien n'est écrit tant que ce n'est pas confirmé.\n\n";
    foreach ($aConfirmer as $l) echo "       $l\n";
    echo "\n     Une fois confirmées, elles s'écrivent à la main sur la fiche de\n";
    echo "     chaque association — sept champs, et on est sûr des sept.\n";
}
if (!$ecrire) echo "\n  Relance avec --ecrire pour appliquer.\n";
