<?php
/**
 * Ancienne page de connexion.   [V40-CLE] [13.08.2026]
 *
 * Elle ne demande plus rien : il n'y a plus de mot de passe, et tout se passe
 * sur entrer.php. Le fichier reste, et se contente de renvoyer, parce que son
 * adresse est dans des signets, dans d'anciens courriels et dans le pied de
 * page du site. Une page qui disparaît laisse une erreur ; une page qui
 * renvoie ne se remarque même pas.
 */
require __DIR__ . '/_inc.php';
redirect('/espace/entrer.php');
