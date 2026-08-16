<?php
/**
 * Les fenêtres de dépôt des financeurs, en rappels. [17.08.2026]
 *
 *   php db/importer_echeances.php [--ecrire]
 *
 * `lv-fiscal` porte 54 lignes et se lit en deux moitiés, qui n'ont pas le même
 * sort:
 *
 *   28 obligations mensuelles — AVS/AI/APG, impôt à la source, TVA
 *      trimestrielle. `admin_tache` les GÉNÈRE déjà, 188 lignes en production,
 *      à partir des modèles et des associations. On ne les reprend pas: deux
 *      vérités pour la même échéance, c'est une échéance qu'on finit par
 *      ignorer des deux côtés.
 *
 *   26 fenêtres de dépôt — Pro Helvetia, DGC Genève, Loterie Romande, FCMA,
 *      Leenaards, SACD, CNM, DRAC, Institut Français. Celles-là n'existent
 *      NULLE PART ailleurs, ni en base ni dans le dépôt.
 *
 * SEULES LES SEPT À VENIR DEVIENNENT DES RAPPELS. Les dix-neuf autres sont
 * passées et marquées faites: un rappel daté d'il y a six mois ne rappelle
 * rien, il allonge une liste dont on se met à sauter le début. Ce qu'elles
 * apprennent — le rythme des deux sessions annuelles — vaut mieux qu'un rappel
 * mort, et c'est écrit dans `_contexto/calendrier_subventions.md` du dépôt de
 * travail, où vivent les règles.
 *
 * IDEMPOTENT PAR LE TEXTE ET LA DATE. Relancer n'ajoute rien: on cherche un
 * rappel ouvert portant le même intitulé le même jour avant d'écrire. Sans
 * cela, deux passages donneraient deux fois « Pro Helvetia — Création » au
 * 31 octobre, et un doublon dans un agenda se coche une fois sur deux.
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

$aujourdhui = date('Y-m-d');
$lignes = array_values(is_array($export['lv-fiscal'] ?? null) ? $export['lv-fiscal'] : []);

$fenetres = array_filter($lignes, fn($x) => ($x['type'] ?? '') === 'funding');
$futures  = array_filter($fenetres, fn($x) => (string)($x['date'] ?? '') >= $aujourdhui);
usort($futures, fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));

printf("  %d ligne(s) dans lv-fiscal · %d fenêtres de dépôt · %d à venir\n\n",
       count($lignes), count($fenetres), count($futures));

$ecrites = $deja = 0;

foreach ($futures as $f) {
    $date   = (string)$f['date'];
    $titre  = trim((string)($f['title'] ?? ''));
    $canton = trim((string)($f['canton'] ?? ''));
    $notes  = trim((string)($f['notes'] ?? ''));
    if ($titre === '' || $date === '') continue;

    /* Le texte porte le canton, parce que « Service Affaires Culturelles » ne
       dit pas lequel et qu'il y en a un par canton. Il porte aussi le mot
       « dépôt »: la ligne tombe dans un agenda à côté de « rappeler Nürnberg »,
       et elle doit se lire sans qu'on ait à l'ouvrir. */
    $texte = 'Dépôt — ' . $titre . ($canton !== '' && !str_contains($titre, $canton) ? " ($canton)" : '');

    $existe = DB::val(
        "SELECT id FROM rappel
          WHERE archive_le IS NULL AND texte = ? AND DATE(quand) = ?", [$texte, $date]);
    if ($existe) { printf("  = %s  %s — déjà là\n", $date, mb_substr($texte, 0, 52)); $deja++; continue; }

    printf("  + %s  %s%s\n", $date, mb_substr($texte, 0, 52), $notes !== '' ? "  [$notes]" : '');
    $ecrites++;

    if ($ecrire) {
        Rappels::creer([
            'quand' => $date,
            'texte' => $texte,
            /* La note dit d'où vient la ligne. Six mois plus tard, « 2e
               session » tout seul ne se rattache à rien, et on ne sait plus si
               c'est une date relevée chez le financeur ou une supposition. */
            'note'  => trim($notes . "\nRepris du calendrier du dashboard Apps Script, 17.08.2026. "
                          . "Vérifier la date chez le financeur avant de s'y fier: "
                          . "les sessions se déplacent d'une année à l'autre."),
        ], 'reprise');
    }
}

printf("\n  %d rappel(s) à créer · %d déjà présent(s)\n", $ecrites, $deja);
printf("  %d fenêtre(s) passée(s) NON reprises — elles ne rappellent plus rien.\n",
       count($fenetres) - count($futures));
echo "  Leur rythme (deux sessions par an) est écrit dans _contexto/calendrier_subventions.md.\n";
if (!$ecrire) echo "\n  Relance avec --ecrire pour appliquer.\n";
