<?php
/**
 * Ancien écran de choix du mot de passe.   [V40-CLE] [13.08.2026]
 *
 * Il n'y a plus de mot de passe à choisir. Ce fichier reste vivant pour une
 * seule raison, et elle est importante : toutes les clés déjà parties portent
 * SON adresse. Elles sont dans des boîtes aux lettres, certaines n'expirent
 * pas, et elles doivent continuer de fonctionner. On garde donc le jeton et
 * l'on renvoie vers la nouvelle porte, qui saura quoi en faire.
 */
require __DIR__ . '/_inc.php';
$jeton = trim((string)($_GET['jeton'] ?? ''));
redirect('/espace/entrer.php' . ($jeton !== '' ? '?jeton=' . urlencode($jeton) : ''));
