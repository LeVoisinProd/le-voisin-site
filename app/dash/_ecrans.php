<?php
/**
 * La carte du dashboard. [16.08.2026]
 *
 * UN SEUL ENDROIT DÉCLARE LES ÉCRANS: leur nom, leur groupe, leur ordre, et
 * s'ils existent déjà. Le menu, le routeur et le titre de la page lisent tous
 * ce fichier. Ajouter un écran, c'est ajouter une ligne ici et poser le fichier
 * correspondant dans app/dash/.
 *
 * POURQUOI LES ÉCRANS PAS ENCORE ÉCRITS FIGURENT QUAND MÊME. Le dashboard
 * actuel a dix-huit entrées de menu, et la reprise en aura autant. Les cacher
 * jusqu'à ce qu'elles marchent donnerait, pendant des mois, une application qui
 * a l'air de savoir faire trois choses. Les montrer grisées dit la vérité: voilà
 * la carte, voilà où on en est.
 *
 * C'est aussi une protection contre un défaut mesuré dans le dashboard actuel,
 * le 15.08.2026: douze sections y sont des marqueurs « Contenu à venir » et
 * dix-neuf pages existent sans aucune entrée de menu, dont six sont écrites et
 * inatteignables. Un écran déclaré ici et sans fichier est visiblement à faire;
 * un fichier sans déclaration n'est servi par personne.
 */
declare(strict_types=1);

/**
 * clef => [groupe, libellé, état]
 *
 * état: 'ok'      l'écran existe et fonctionne
 *       'partiel' il existe mais ne fait qu'une partie du travail
 *       'a_faire' déclaré, pas encore écrit
 */
const DASH_ECRANS = [

    // ── Diffusion ───────────────────────────────────────────────────────────
    'contacts'    => ['Diffusion', 'Contacts',            'partiel'],
    'relances'    => ['Diffusion', 'Relances',            'a_faire'],
    'trombi'      => ['Diffusion', 'Trombinoscope',       'a_faire'],
    'emailing'    => ['Diffusion', 'Envois groupés',      'a_faire'],

    // ── Production ──────────────────────────────────────────────────────────
    'projets'     => ['Production', 'Projets',            'a_faire'],
    'dates'       => ['Production', 'Dates et tournées',  'a_faire'],
    'calendrier'  => ['Production', 'Calendrier',         'a_faire'],
    'documents'   => ['Production', 'Documents',          'a_faire'],

    // ── Personnel ───────────────────────────────────────────────────────────
    'personnes'   => ['Personnel', 'Personnes',           'a_faire'],
    'engagements' => ['Personnel', 'Engagements',         'a_faire'],
    'salaires'    => ['Personnel', 'Salaires',            'a_faire'],
    'temps'       => ['Personnel', 'Feuilles de temps',   'a_faire'],

    // ── Administration ──────────────────────────────────────────────────────
    'associations'=> ['Administration', 'Associations',   'a_faire'],
    'mensuel'     => ['Administration', 'Suivi mensuel',  'a_faire'],
    'a1'          => ['Administration', 'Attestations A1', 'a_faire'],

    // ── Argent ──────────────────────────────────────────────────────────────
    'financements'=> ['Argent', 'Financements',           'a_faire'],
    'factures'    => ['Argent', 'Factures',               'a_faire'],
    'comptes'     => ['Argent', 'Grand livre',            'a_faire'],
];

/** L'écran servi quand aucun n'est demandé. */
const DASH_DEFAUT = 'contacts';

/** Les groupes, dans l'ordre du menu. Dérivé, pour n'avoir qu'une source. */
function dash_groupes(): array
{
    $g = [];
    foreach (DASH_ECRANS as $clef => [$groupe, $libelle, $etat]) {
        $g[$groupe][] = ['clef' => $clef, 'libelle' => $libelle, 'etat' => $etat];
    }
    return $g;
}

function dash_libelle(string $clef): string
{
    return DASH_ECRANS[$clef][1] ?? 'Dashboard';
}

function dash_existe(string $clef): bool
{
    return isset(DASH_ECRANS[$clef]) && is_file(__DIR__ . '/' . $clef . '.php');
}
