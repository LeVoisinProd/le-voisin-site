<?php
/**
 * Le Voisin — configuration du site.
 * Copiez ce fichier en config.php (l'assistant d'installation le fait pour vous).
 */
return [
    // Base de données MySQL
    'db' => [
        'host'    => 'localhost',
        'name'    => 'levoisin',
        'user'    => 'levoisin',
        'pass'    => 'levoisin',
        'charset' => 'utf8mb4',
    ],

    // URL complète du site, sans slash final.
    // Exemple : https://www.le-voisin.com
    'base_url' => 'http://localhost:8080',

    // Langues du site. La première est la langue par défaut :
    // c'est elle qui est affichée quand un contenu n'est pas traduit.
    'languages' => ['en', 'fr'],

    // Affichage des erreurs (à laisser sur false en production)
    'debug' => false,

    // Clé secrète (sessions / jetons). Générée à l'installation.
    'secret' => 'change-me',
];
