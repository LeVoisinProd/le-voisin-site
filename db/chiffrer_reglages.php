<?php
/**
 * Chiffrer les secrets restés en clair dans `settings`. [16.08.2026]
 *
 *   php db/chiffrer_reglages.php            montre ce qu'il ferait
 *   php db/chiffrer_reglages.php --appliquer écrit
 *
 * CE QU'IL TERMINE. `secret()` lit les deux formes — chiffrée et en clair —
 * pour que rien ne casse le jour où on la pose. C'est la migration silencieuse
 * des fiches de collaborateur, et elle a le même défaut, celui-là même qui a
 * coûté une matinée aujourd'hui: ELLE NE SE TERMINE PAS TOUTE SEULE. Une valeur
 * jamais ressaisie reste en clair pour toujours. Ce script la termine d'un coup.
 *
 * DEUX TRAITEMENTS, ET LA DIFFÉRENCE EST DE FOND:
 *
 *   chiffré   `smtp_pass`, `skribble_api_key`. On doit pouvoir les RELIRE pour
 *             les présenter au serveur d'en face. Un haché serait inutilisable.
 *   haché     `catalogue_password`. On ne fait que le VÉRIFIER: personne n'a
 *             besoin de le relire, et un haché ne se déchiffre pas, même avec
 *             la clef du `config.php`. C'est strictement plus sûr que chiffrer.
 *             Le code de `CatalogAuth` prévoit déjà les deux formes et remplace
 *             le clair par un haché à la première connexion réussie — on ne
 *             fait qu'avancer cette échéance.
 *
 * TROIS GARDES, les mêmes que pour `chiffrer_fiches.php`:
 *   · on vérifie que la clef actuelle ouvre bien ce qui est déjà chiffré;
 *   · chaque valeur fait un aller-retour avant d'être écrite, et rien n'est
 *     écrit si elle ne revient pas identique;
 *   · on ne touche jamais à une valeur déjà chiffrée ni déjà hachée.
 *
 * CE QUI RESTE VOLONTAIREMENT EN CLAIR: `agenda_token`. Il est affiché à
 * l'écran par construction — c'est une adresse qu'on colle dans Google — et le
 * chiffrer n'ajouterait rien. Il se change d'un clic, c'est sa protection.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$ecrire = in_array('--appliquer', $argv, true);

const A_CHIFFRER = ['smtp_pass', 'skribble_api_key'];
const A_HACHER   = ['catalogue_password'];

echo $ecrire ? "ÉCRITURE\n\n" : "SIMULATION — rien n'est écrit. --appliquer pour appliquer.\n\n";

/* Garde 1: la clef actuelle ouvre-t-elle ce qui est déjà chiffré ? Si non,
   c'est que le `secret` du config.php a changé, et écrire par-dessus rendrait
   illisible ce qui l'était encore. */
$temoin = DB::val("SELECT sval FROM settings WHERE sval LIKE 'sb1:%' LIMIT 1");
if ($temoin !== null && Crypto::dechiffrer((string)$temoin) === null) {
    fwrite(STDERR, "ARRÊT: une valeur déjà chiffrée ne s'ouvre pas avec la clef actuelle.\n"
                 . "Le `secret` du config.php a changé. Ne rien écrire avant d'avoir tranché.\n");
    exit(1);
}

$n = 0;

foreach (A_CHIFFRER as $k) {
    $v = trim((string)Settings::get($k, ''));
    if ($v === '')                       { printf("  %-20s vide\n", $k); continue; }
    if (str_starts_with($v, 'sb1:'))     { printf("  %-20s déjà chiffré\n", $k); continue; }

    /* Garde 2: aller-retour avant d'écrire. */
    $chiffre = Crypto::chiffrer($v);
    if (Crypto::dechiffrer($chiffre) !== $v) {
        printf("  %-20s ÉCHEC de l'aller-retour — non écrit\n", $k);
        continue;
    }
    printf("  %-20s en clair (%d car.) → chiffré\n", $k, mb_strlen($v));
    if ($ecrire) Settings::set($k, $chiffre);
    $n++;
}

foreach (A_HACHER as $k) {
    $v = trim((string)Settings::get($k, ''));
    if ($v === '')                     { printf("  %-20s vide\n", $k); continue; }
    if (str_starts_with($v, '$2y$') || str_starts_with($v, '$argon')) {
        printf("  %-20s déjà haché\n", $k); continue;
    }
    $h = password_hash($v, PASSWORD_DEFAULT);
    /* Garde 2, version haché: le mot de passe actuel doit encore passer. */
    if (!password_verify($v, $h)) {
        printf("  %-20s ÉCHEC de la vérification — non écrit\n", $k);
        continue;
    }
    /* HACHER EST IRRÉVERSIBLE, et c'est tout l'intérêt — mais cela veut dire
       que la valeur lisible disparaît pour de bon. Le mot de passe du Catalogue
       s'écrit dans des e-mails à des programmateurs: si personne ne le connaît
       par cœur, le hacher le perd. On le montre donc EN SIMULATION, une fois,
       pour qu'il soit noté avant, et jamais au moment d'écrire. */
    if (!$ecrire) {
        printf("  %-20s en clair (%d car.) → sera haché\n", $k, mb_strlen($v));
        printf("  %-20s ⚠ NOTEZ-LE MAINTENANT, il disparaîtra: « %s »\n", '', $v);
    } else {
        printf("  %-20s haché — le mot de passe lui-même ne change pas\n", $k);
    }
    if ($ecrire) Settings::set($k, $h);
    $n++;
}

printf("\n  %d réglage(s) %s\n", $n, $ecrire ? 'traité(s)' : 'à traiter');
if (!$ecrire) echo "  Relance avec --appliquer.\n";
else if ($n) {
    echo "\n  À VÉRIFIER MAINTENANT, et pas demain:\n"
       . "    · un envoi d'e-mail depuis l'administration (le SMTP)\n"
       . "    · l'ouverture du Catalogue avec le mot de passe habituel\n"
       . "  Un secret chiffré dont un lecteur aurait été manqué ne dit rien: le service\n"
       . "  échoue en silence, et on l'apprend par la personne qui n'a rien reçu.\n";
}
