<?php
/**
 * Découper les adresses d'association en quatre champs. [16.08.2026]
 *
 *   php db/decouper_adresses.php [--ecrire]
 *
 * Anna: « pucar as infos de todos os enderecos e colcoar nas cadas certas, vc
 * colocu tudo em uma so ». La reprise avait mis l'adresse entière dans une seule
 * colonne, en plusieurs lignes, et la fiche l'affichait telle quelle.
 *
 * LE FORMAT EST RÉGULIER, relevé sur les douze qui en portent une:
 *
 *     ℅ Antonella Infantino      ← facultatif, dix sur douze
 *     Via Coremmo 13
 *     6900 Lugano
 *
 * ON NE DEVINE PAS, ON RECONNAÎT. La dernière ligne doit correspondre à
 * « code postal + ville » pour que le découpage se fasse; sinon on ne touche à
 * rien et on le dit. Un découpage approximatif d'adresse est pire que pas de
 * découpage: une lettre part à une ville qui n'existe pas, et personne ne sait
 * pourquoi le contrat n'est jamais arrivé.
 *
 * LES CODES POSTAUX ACCEPTÉS sont ceux qu'on rencontre: quatre chiffres pour la
 * Suisse, cinq pour la France et l'Allemagne. « 1211 Genève 1 » — avec le numéro
 * de case postale à la fin — est reconnu aussi: la ville est « Genève 1 » et
 * c'est ce qu'il faut écrire sur l'enveloppe.
 *
 * IDEMPOTENT. Une adresse déjà découpée — celle qui ne contient plus de saut de
 * ligne et dont `cp` est rempli — est laissée tranquille.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--ecrire', $argv, true);
echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --ecrire pour appliquer.\n\n";

$n = $sautes = 0;

foreach (DB::all("SELECT id, nom, chez, adresse, cp, ville FROM organisation
                   WHERE supprime_le IS NULL AND adresse IS NOT NULL AND adresse <> ''") as $o) {

    $brut   = str_replace(["\r\n", "\r"], "\n", (string)$o['adresse']);
    $lignes = array_values(array_filter(array_map('trim', explode("\n", $brut)), fn($x) => $x !== ''));

    if (count($lignes) < 2 && trim((string)$o['cp']) !== '') {
        continue;                       // déjà découpée
    }
    if (count($lignes) < 2) {
        printf("  %-22s une seule ligne, rien à découper\n", mb_substr((string)$o['nom'], 0, 22));
        $sautes++;
        continue;
    }

    /* La dernière ligne doit être « CP Ville ». C'est la condition, et elle est
       stricte: sans elle on ne sait pas où s'arrête la rue. */
    $derniere = end($lignes);
    if (!preg_match('/^(\d{4,5})\s+(.+)$/u', $derniere, $m)) {
        printf("  %-22s dernière ligne « %s » — pas un « CP Ville », NON TOUCHÉE\n",
               mb_substr((string)$o['nom'], 0, 22), mb_substr($derniere, 0, 34));
        $sautes++;
        continue;
    }
    $cp    = $m[1];
    $ville = trim($m[2]);
    array_pop($lignes);

    /* Le « chez »: première ligne si elle commence par ℅, c/o, chez. */
    $chez = null;
    if ($lignes && preg_match('/^(?:℅|c\/o|C\/O|chez)\s*(.+)$/u', $lignes[0], $mc)) {
        $chez = trim($mc[1]);
        array_shift($lignes);
    }
    $rue = trim(implode(', ', $lignes));

    printf("  %-22s\n", mb_substr((string)$o['nom'], 0, 22));
    if ($chez !== null) printf("      chez     %s\n", $chez);
    printf("      adresse  %s\n      cp       %s\n      ville    %s\n", $rue, $cp, $ville);
    $n++;

    if ($ecrire) {
        /* `cp` et `ville` ne sont écrasés que s'ils sont vides: une valeur
           saisie à la main est plus sûre que celle qu'on vient de deviner. */
        DB::run("UPDATE organisation
                    SET chez    = COALESCE(NULLIF(chez, ''), ?),
                        adresse = ?,
                        cp      = COALESCE(NULLIF(cp, ''), ?),
                        ville   = COALESCE(NULLIF(ville, ''), ?)
                  WHERE id = ?",
                [$chez, $rue, $cp, $ville, (int)$o['id']]);
    }
}

printf("\n  %d adresse(s) découpée(s)", $n);
if ($sautes) printf(" · %d laissée(s) telle(s) quelle(s)", $sautes);
echo "\n";
if (!$ecrire) echo "  Relance avec --ecrire pour appliquer.\n";
