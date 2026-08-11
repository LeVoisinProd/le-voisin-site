<?php
/**
 * Formats d'images prédéfinis (d'après les maquettes).   [V8-CADRAGE]
 * [largeur, hauteur, mode] — mode 'crop' : recadrage exact au format,
 * mode 'fit' : réduction dans le cadre en gardant les proportions.
 * Les fichiers sont générés en WebP (avec repli JPEG).
 */
return [
    'formats' => [
        'cover'    => [1920, 1080, 'crop'],  // grande image de tête 16:9
        // Bandeau des pages projet : le grand carrousel bord à bord. Sa forme
        // (20:9) est exactement celle du cadre affiché sur la page — ce qui est
        // cadré dans l'administration est donc ce qui apparaît, sans que le
        // navigateur ait à recouper quoi que ce soit.
        'banner'   => [2000, 900,  'crop'],
        'card'     => [900,  1125, 'crop'],  // vignette de grille 4:5
        'square'   => [900,  900,  'crop'],  // carré (galeries, portraits)
        'gallery'  => [1800, 1200, 'fit'],   // image de galerie (zoom)
        'content'  => [1600, 1600, 'fit'],   // image insérée dans un texte
        'team'     => [800,  1000, 'crop'],  // portrait équipe 4:5
        'thumb'    => [480,  480,  'crop'],  // miniature (admin et listes)
        'og'       => [1200, 630,  'crop'],  // partage réseaux sociaux
        'doccover' => [640,  905,  'crop'],  // couverture de document (A4)
        'logo'     => [600,  300,  'fit'],   // logo partenaire (dans le cadre, proportions gardées)
    ],

    // Formats générés selon l'emplacement de l'image
    'zones' => [
        // « banner » ajouté à l'image de tête : quand un projet n'a pas de
        // galerie, c'est elle qui remplit le bandeau.
        'cover'    => ['card', 'cover', 'banner', 'square', 'og', 'thumb'],
        // « banner » ajouté aux images de galerie : c'est le format affiché par
        // le grand carrousel des pages projet. Sans lui, cette page montrait le
        // format « gallery », qui ne se recadre pas — le cadrage choisi dans
        // l'administration n'avait donc aucun effet sur la page.
        'gallery'  => ['gallery', 'banner', 'square', 'thumb'],
        'content'  => ['content'],
        'team'     => ['team', 'thumb', 'og'],
        'event'    => ['card', 'thumb'],
        'doccover' => ['doccover', 'thumb'],
        'og'       => ['og', 'thumb'],
        'partners' => ['logo', 'thumb'],
    ],

    // Format à proposer au recadrage selon la zone (interface d'admin)
    'crop_ui' => [
        'cover'    => ['card', 'cover', 'banner', 'square', 'og'],
        // Le cadrage « banner » est proposé en premier : c'est celui qu'on voit
        // en grand sur la page projet, donc celui qu'on a envie de régler.
        // Auparavant la seule proposition était « square », un format que la
        // page projet n'affiche nulle part : on cadrait une image que personne
        // ne voyait jamais.
        'gallery'  => ['banner', 'square'],
        'team'     => ['team', 'og'],
        'event'    => ['card'],
        'doccover' => ['doccover'],
        'og'       => ['og'],
        'content'  => [],
    ],
];
