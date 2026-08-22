<?php
/**
 * Retire le deuxième facteur d'un compte.  [revue de sécurité, 22.08.2026]
 *
 *   php db/retirer_2fa.php anna@le-voisin.com              dit ce qu'il ferait
 *   php db/retirer_2fa.php anna@le-voisin.com --appliquer  écrit
 *
 * C'EST LA SORTIE DE SECOURS, ET IL EN FAUT UNE. Un deuxième facteur enferme
 * dehors aussi sûrement qu'il enferme les autres: téléphone perdu, volé,
 * réinitialisé, application effacée. Sans ce script, un compte devient
 * inaccessible pour toujours et il faudrait toucher la base à la main dans
 * l'urgence — c'est-à-dire au pire moment.
 *
 * IL NE TOURNE QU'EN LIGNE DE COMMANDES, donc il demande un accès SSH à
 * l'hébergement. Ce n'est pas un détail: cet accès est le facteur qui remplace
 * celui qu'on vient de perdre. Quelqu'un qui n'a que le mot de passe du
 * dashboard ne peut pas s'en servir.
 *
 * IL NE TOUCHE PAS AU MOT DE PASSE. Retirer le deuxième facteur ne doit pas
 * ouvrir le compte: il faut encore le mot de passe pour entrer.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../app/bootstrap.php';

$email     = trim((string)($argv[1] ?? ''));
$appliquer = in_array('--appliquer', $argv, true);

if ($email === '') {
    fwrite(STDERR, "\n  Usage: php db/retirer_2fa.php <adresse> [--appliquer]\n\n");
    exit(1);
}

$u = DB::one('SELECT id, email, name, totp_actif, totp_secret FROM users WHERE email = ?',
             [mb_strtolower($email)]);

if (!$u) {
    fwrite(STDERR, "\n  Aucun compte avec cette adresse. Rien n'a été touché.\n\n");
    exit(1);
}

$actif  = (int)$u['totp_actif'] === 1;
$secret = trim((string)($u['totp_secret'] ?? '')) !== '';

printf("\n  Compte: %s (%s)\n", $u['email'], $u['name'] ?: '—');
printf("  Deuxième facteur: %s · secret posé: %s\n\n",
       $actif ? 'ACTIF' : 'inactif', $secret ? 'oui' : 'non');

if (!$actif && !$secret) { echo "  Rien à retirer.\n\n"; exit(0); }

if (!$appliquer) {
    echo "  LECTURE SEULE. Relancer avec --appliquer pour retirer.\n";
    echo "  Le mot de passe n'est pas touché: il faudra toujours l'avoir pour entrer.\n\n";
    exit(0);
}

DB::update('users', ['totp_secret' => null, 'totp_actif' => 0, 'totp_dernier_pas' => null],
           'id = ?', [(int)$u['id']]);

$v = DB::one('SELECT totp_actif, totp_secret FROM users WHERE id = ?', [(int)$u['id']]);
printf("  Retiré. Vérifié: actif=%d · secret=%s\n",
       (int)$v['totp_actif'], $v['totp_secret'] === null ? 'aucun' : 'ENCORE LÀ');
echo "  La personne entre maintenant avec son mot de passe seul.\n";
echo "  Qu'elle repose un facteur depuis Paramètres dès qu'elle a son téléphone.\n\n";
