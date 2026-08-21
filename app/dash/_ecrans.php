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

    /* PROJETS EST À LA RACINE ET AVANT LE CALENDRIER. [Anna, 21.08.2026]
       « nous allons mettre la page projets dans le menu au-dessus de la partie
       calendrier, et pas dans cette partie-là ».

       Il était rangé sous Calendrier avec Événements et Offres, par une
       symétrie qui ne tient pas: ces deux-là sont des DATES — une offre
       acceptée devient un événement — alors qu'un spectacle n'a pas de date,
       il en a plusieurs, sur plusieurs saisons, et il existe avant la première.
       Le mettre au-dessus, c'est dire l'ordre réel: il y a une pièce, puis des
       dates pour elle.

       La clef ne bouge pas: `?e=projets` reste la même adresse, les liens
       envoyés et la ligne de permissions aussi. Seul le menu change. */
    'projets'      => ['Projets', 'ok', []],

    // Le cœur, dans ses mots. Tout ce qui a une date y figure: shows confirmés,
    // en attente, voyages, logistique.
    'calendrier'   => ['Calendrier', 'ok', [
        /* « Événements » et non « Bookings » — Anna, 17.08.2026. L'écran porte
           les dates jouées, et le mot anglais désignait le métier plutôt que la
           chose: on fait du booking, on vend des événements.

           LA CLEF RESTE `bookings`, ET C'EST DÉLIBÉRÉ. Elle est l'adresse
           (`?e=bookings`), la ligne de la grille de permissions, le nom de la
           table `booking` et de ses colonnes, et elle apparaît 160 fois dans le
           dépôt. Renommer tout cela casserait les liens déjà envoyés et les
           marque-pages, pour un gain nul: personne ne lit l'adresse. Ce qu'on
           lit, c'est le menu, et c'est lui qui change.

           Orthographe: DEUX ACCENTS AIGUS, « Événements ». Les deux graphies
           sont correctes en français — la rectification de 1990 préfère
           « évènement » — mais les cinq occurrences déjà dans le dépôt
           (`admin.fr.php`, `entities.php`, `agenda.php`, `index.php`) portent
           l'aigu. Ce qui n'est pas correct, c'est d'en avoir deux dans la même
           application: le menu dirait l'une et le courriel envoyé l'autre. */
        'bookings'  => ['Événements', 'ok'],
        // Le pipeline des demandes entrantes, sous Événements comme dans la
        // spécification: une offre acceptée devient une date, donc elle vit
        // au même endroit qu'elles.
        'offres'    => ['Offres',    'ok'],
    ]],

    'contacts'     => ['Contacts', 'ok', []],

    'finances'     => ['Finances', 'partiel', []],

    // Associations et artistes ensemble: ce sont les mêmes fiches, avec ce qui
    // se répète d'un show à l'autre, modèles de contrat et de deal compris.
    'associations' => ['Associations', 'ok', []],

    /* Entre les associations et l'administration, parce que c'est la chaîne
       réelle: une association emploie des gens, et employer des gens fabrique
       les obligations de l'écran suivant — AVS, impôt à la source, A1.
       [17.08.2026] */
    'personnel'    => ['Personnel', 'ok', []],

    'administration' => ['Administration', 'ok', []],

    'documentation'  => ['Documentation', 'ok', []],

    'marketing'      => ['Marketing', 'ok', []],

    'parametres'     => ['Paramètres et équipe', 'ok', []],
];

/** L'écran servi quand aucun n'est demandé. */
const DASH_DEFAUT = 'accueil';

/**
 * QUI VOIT QUOI, ET QUI PEUT ÉCRIRE. [grille validée par Anna, 16.08.2026]
 *
 * POURQUOI CETTE TABLE EXISTE. Jusqu'au 16.08.2026, dashboard.php vérifiait
 * Auth::check() et rien d'autre: entrer, c'était tout voir et tout modifier.
 * Le rôle `role_dash` existait en base et n'était lu que par parametres.php,
 * pour se protéger lui-même. Mesuré: aucun autre écran ne le consultait.
 *
 * C'est le défaut même que la spécification reproche au dashboard actuel —
 * « la grille de permissions n'est lue par rien » — et il était en train de se
 * reproduire dans le nouveau. C'est aussi ce qui bloque l'équipe: pour voir une
 * date de spectacle il fallait tout voir, salaires, AVS et IBAN compris, donc
 * personne n'entrait.
 *
 * LA RÈGLE DE LECTURE:
 *
 *     'ecrit'  voit l'écran et peut le modifier
 *     'lit'    voit l'écran, tout geste d'écriture est refusé
 *     absent   l'écran n'existe pas pour cette personne: ni au menu, ni à
 *              l'adresse. Le routeur répond 403.
 *
 * UN ÉCRAN SANS LIGNE ICI N'EST OUVERT À PERSONNE SAUF À `direction`. C'est
 * voulu: on ajoute un écran, on oublie la permission, et il reste fermé au
 * lieu de s'ouvrir à tout le monde. Ce qui manque doit valoir moins.
 */
const DASH_ACCES = [
    'accueil'        => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],

    'calendrier'     => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],
    'bookings'       => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],
    'projets'        => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],

    // Les demandes entrantes portent des budgets annoncés et des noms de
    // programmateurs. Même niveau que les bookings: c'est le travail de
    // diffusion, et `lecture` doit pouvoir voir ce qui arrive sans y répondre.
    'offres'         => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],

    'contacts'       => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],
    'marketing'      => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],
    'documentation'  => ['direction'=>'ecrit', 'production'=>'ecrit', 'lecture'=>'lit'],

    // Les modèles de contrat et de deal s'y trouvent: la production les
    // consulte pour préparer une date, elle ne les fixe pas.
    'associations'   => ['direction'=>'ecrit', 'production'=>'lit',   'lecture'=>'lit'],

    // L'argent se lit par la production, pour chiffrer une date sans avoir à
    // demander. Il ne s'écrit que par la direction.
    'finances'       => ['direction'=>'ecrit', 'production'=>'lit'],

    // 42 numéros AVS et 40 IBAN sur un seul écran. C'est LA raison d'être de
    // toute cette grille, plus encore qu'Administration: là il s'agit de
    // déclarations, ici de l'identité et du compte en banque de 91 personnes.
    // Aucun rôle sauf `direction`, et pas de ligne 'lecture' même en lecture.
    'personnel'      => ['direction'=>'ecrit'],

    // AVS, impôt à la source, salaires des treize associations. C'est la
    // raison d'être de toute cette grille.
    'administration' => ['direction'=>'ecrit'],

    // Qui donne les rôles doit déjà les avoir.
    'parametres'     => ['direction'=>'ecrit'],
];

/** Les rôles déclarés, du plus fermé au plus ouvert. */
const DASH_ROLES = ['lecture', 'production', 'direction'];

/**
 * Ce que ce rôle peut faire sur cet écran: 'ecrit', 'lit', ou '' pour rien.
 *
 * Le défaut est le refus. Un écran non déclaré, un rôle inconnu, une clef qui
 * n'existe pas: tout cela rend '', et le seul à passer partout est `direction`.
 */
function dash_droit(string $clef, string $role): string
{
    if (!isset(DASH_ACCES[$clef])) return $role === 'direction' ? 'ecrit' : '';
    return DASH_ACCES[$clef][$role] ?? '';
}

/** Le rôle du compte connecté. Interrogé une fois par requête. */
function dash_role(): string
{
    static $role = null;
    if ($role !== null) return $role;

    $u = Auth::user();
    $id = (int)($u['id'] ?? 0);
    if ($id === 0) return $role = 'lecture';

    /* La colonne arrive avec la migration 008. Si elle manque, le dashboard
       n'est pas déployé au complet: mieux vaut le dire que deviner un rôle. */
    try {
        $r = DB::val('SELECT role_dash FROM users WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Le dashboard demande la migration 008. Lancer: php db/migrer.php');
    }

    /* Le repli est « lecture » et non « direction ». Un compte dont le rôle ne
       se lit pas voit le calendrier, pas les salaires. */
    return $role = in_array($r, DASH_ROLES, true) ? (string)$r : 'lecture';
}

/** Vrai si le compte connecté voit cet écran, en lecture ou en écriture. */
function dash_visible(string $clef): bool
{
    return dash_droit($clef, dash_role()) !== '';
}

/**
 * À appeler AVANT toute écriture d'un écran: création, modification,
 * suppression. Coupe net si le rôle n'a que la lecture.
 *
 * On coupe plutôt que d'afficher un message, parce qu'un POST refusé à
 * mi-chemin laisserait la moitié du travail fait.
 */
function dash_exige_ecriture(string $clef): void
{
    if (dash_droit($clef, dash_role()) !== 'ecrit') {
        http_response_code(403);
        exit('Interdit : votre rôle ne permet pas de modifier cet écran.');
    }
}

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
