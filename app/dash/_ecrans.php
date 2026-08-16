<?php
/**
 * La carte du dashboard. [structure d'Anna, 16.08.2026]
 *
 * UN SEUL ENDROIT DÉCLARE LES ÉCRANS: leur nom, leur place, leur état. Le menu,
 * le routeur et le titre de la page lisent tous ce fichier. Ajouter un écran,
 * c'est ajouter une ligne ici et poser le fichier dans app/dash/.
 *
 * L'ORDRE EST CELUI D'ANNA, pas un rangement par familles techniques. Il suit
 * la journée de travail: on ouvre sur ce qui demande une décision, puis on
 * regarde les dates, puis les gens, puis l'argent. Le calendrier est en tête
 * parce que, dans ses mots, « le calendrier est le cœur de la plateforme ».
 *
 * LES ÉCRANS PAS ENCORE ÉCRITS FIGURENT QUAND MÊME, en gris. La reprise du
 * dashboard prendra des mois: les cacher donnerait pendant tout ce temps
 * l'impression d'un outil qui sait faire trois choses. La carte doit être vraie.
 *
 * C'est aussi une parade contre un défaut mesuré dans le dashboard actuel le
 * 15.08.2026: douze sections y sont des marqueurs vides et dix-neuf pages
 * existent sans aucune entrée de menu, dont six sont écrites et inatteignables.
 * Ici un écran déclaré sans fichier se voit, et un fichier sans déclaration
 * n'est servi à personne.
 *
 * Le détail de ce que chaque écran doit faire est dans
 * `dados/dashboard_migration/ecrans_specification.md` du dépôt de travail.
 */
declare(strict_types=1);

/**
 * clef => [libellé, état, sous-écrans]
 *
 * état: 'ok'      l'écran existe et fait son travail
 *       'partiel' il existe et ne fait qu'une partie
 *       'a_faire' déclaré, pas encore écrit
 */
const DASH_ECRANS = [

    'accueil'      => ['Tableau de bord', 'ok', []],

    // Le cœur, dans ses mots. Tout ce qui a une date y figure: shows confirmés,
    // en attente, voyages, logistique.
    'calendrier'   => ['Calendrier', 'ok', [
        'bookings'  => ['Bookings',  'ok'],
        'projets'   => ['Projets',   'ok'],
    ]],

    'contacts'     => ['Contacts', 'ok', []],

    'finances'     => ['Finances', 'partiel', []],

    // Associations et artistes ensemble: ce sont les mêmes fiches, avec ce qui
    // se répète d'un show à l'autre, modèles de contrat et de deal compris.
    'associations' => ['Associations et artistes', 'ok', []],

    'administration' => ['Administration', 'ok', []],

    'documentation'  => ['Documentation', 'ok', []],

    'marketing'      => ['Marketing', 'ok', []],

    'parametres'     => ['Paramètres et équipe', 'ok', []],
];

/** L'écran servi quand aucun n'est demandé. */
const DASH_DEFAUT = 'accueil';

/** Toutes les clefs, sous-écrans compris, à plat. Sert au routeur. */
function dash_clefs(): array
{
    $out = [];
    foreach (DASH_ECRANS as $clef => [$lib, $etat, $enfants]) {
        $out[$clef] = $lib;
        foreach ($enfants as $c => [$l, $e]) $out[$c] = $l;
    }
    return $out;
}

function dash_libelle(string $clef): string
{
    return dash_clefs()[$clef] ?? 'Dashboard';
}

function dash_existe(string $clef): bool
{
    return isset(dash_clefs()[$clef]) && is_file(__DIR__ . '/' . $clef . '.php');
}

/** La clef du parent d'un sous-écran, pour que le menu ouvre la bonne branche. */
function dash_parent(string $clef): string
{
    foreach (DASH_ECRANS as $p => [$lib, $etat, $enfants]) {
        if (isset($enfants[$clef])) return $p;
    }
    return $clef;
}
