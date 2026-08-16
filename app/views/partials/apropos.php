<?php
/**
 * La page « À propos » : lire le texte du CMS et en déduire une mise en page.
 * [V43-APROPOS]
 *
 * Le problème que ce fichier résout. La page « À propos » est un mur de texte :
 * cinq familles de services, dix-huit prestations, les règles de
 * fonctionnement, les convictions, la durabilité — tout dans un seul champ, en
 * paragraphes. Personne ne lit cela, et l'information la plus utile (ce qui est
 * assuré, ce qui se demande, sous quel délai) est la plus enfouie.
 *
 * La solution évidente aurait été de découper le texte en trente champs de CMS,
 * un par carte et par puce. Elle est mauvaise : elle rend la page pénible à
 * modifier, elle oblige à une migration, et elle fige une structure qui bougera
 * — le jour où une sixième famille de services apparaît, il faut du code.
 *
 * Ce fichier prend l'autre chemin, celui que le site suit déjà ailleurs :
 * infos_pratiques() lit le texte libre d'une fiche projet et en retire les
 * lignes restées sans réponse ; sans_deux_points() nettoie ce qui reste. Le
 * texte du CMS est la source, le code en déduit la forme.
 *
 * LA CONVENTION, et elle tient en trois lignes :
 *
 *   <h2>          ouvre une section
 *   <h3>          ouvre une carte à l'intérieur de la section
 *   <ul>          les puces d'une carte
 *   <blockquote>  une citation
 *
 * Et la forme se déduit de ce qu'on a écrit, sans rien avoir à déclarer :
 *
 *   des <h3> avec des listes           → une grille de cartes
 *   des <h3> avec des paragraphes      → une rangée de blocs
 *   des <blockquote>                   → des citations
 *   ni l'un ni l'autre                 → du texte, comme avant
 *
 * Une section écrite sans <h3> continue donc de s'afficher exactement comme
 * aujourd'hui. C'est délibéré : la page ne casse pas si personne n'apprend la
 * convention, elle s'embellit quand on l'applique.
 */

if (!function_exists('apropos_sections')) {

/**
 * Découpe le HTML du CMS en sections, et devine la forme de chacune.
 *
 * @return array<int, array{titre:string, mode:string, intro:string, items:array}>
 */
function apropos_sections(string $html): array
{
    $html = trim($html);
    if ($html === '') return [];

    /* DOMDocument plutôt qu'une expression régulière. Le HTML qui sort de
       l'éditeur du CMS n'est pas prévisible : attributs de style, balises
       imbriquées, paragraphes vides. Une regex qui découpe sur « <h2 » se
       trompe dès qu'un titre porte une classe, et échoue en silence — le
       défaut récurrent de ce projet. Un analyseur, lui, se trompe bruyamment
       ou pas du tout. */
    $doc = new DOMDocument();
    $avant = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
        . $html . '</body></html>'
    );
    libxml_clear_errors();
    libxml_use_internal_errors($avant);

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return [];

    $sections = [];
    $section  = null;   // la section en cours
    $item     = null;   // la carte en cours, à l'intérieur d'une section

    $fermerItem = function () use (&$section, &$item) {
        if ($item !== null && $section !== null) {
            $section['items'][] = $item;
            $item = null;
        }
    };
    $fermerSection = function () use (&$sections, &$section, &$item, &$fermerItem) {
        $fermerItem();
        if ($section !== null) {
            $sections[] = $section;
            $section = null;
        }
    };

    foreach (iterator_to_array($body->childNodes) as $noeud) {
        if ($noeud->nodeType === XML_TEXT_NODE) {
            // Les blancs entre deux balises ne sont pas du contenu.
            if (trim($noeud->textContent) === '') continue;
        }

        $nom = strtolower($noeud->nodeName);
        $sortie = $doc->saveHTML($noeud);

        if ($nom === 'h2') {
            $fermerSection();
            $section = ['titre' => trim($noeud->textContent), 'mode' => '',
                        'intro' => '', 'outro' => '', 'items' => [], 'citations' => []];
            continue;
        }

        /* Du contenu avant le premier <h2> : c'est le chapeau de la page, et il
           forme une section sans titre. Sans cela, le texte d'introduction
           disparaîtrait — perdre du contenu en silence serait pire que ne rien
           réorganiser du tout. */
        if ($section === null) {
            $section = ['titre' => '', 'mode' => '', 'intro' => '', 'outro' => '', 'items' => [], 'citations' => []];
        }

        if ($nom === 'h3') {
            $fermerItem();
            $item = ['titre' => trim($noeud->textContent), 'intro' => '', 'liste' => []];
            continue;
        }

        if ($nom === 'blockquote') {
            $fermerItem();
            $section['citations'][] = trim($noeud->textContent);
            continue;
        }

        if ($item !== null) {
            if ($nom === 'ul' || $nom === 'ol') {
                foreach ($noeud->getElementsByTagName('li') as $li) {
                    $t = trim($li->textContent);
                    if ($t !== '') $item['liste'][] = $t;
                }
            } else {
                $item['intro'] .= $sortie;
            }
            continue;
        }

        /* Un paragraphe écrit APRÈS les cartes ou les citations va en pied de
           section, pas en chapeau. Sans cette distinction, il remonterait
           au-dessus de ce qu'il commente : le texte du CMS serait réordonné
           dans le dos de qui l'a écrit, ce qui est exactement le genre de
           surprise silencieuse que ce projet cherche à éliminer. */
        if ($section['items'] || $section['citations']) $section['outro'] .= $sortie;
        else                                            $section['intro'] .= $sortie;
    }
    $fermerSection();

    // La forme se déduit du contenu, section par section.
    foreach ($sections as $i => $s) {
        $avecListe = false;
        foreach ($s['items'] as $it) if ($it['liste']) { $avecListe = true; break; }

        if ($s['items'] && $avecListe)      $sections[$i]['mode'] = 'cartes';
        elseif ($s['items'])                $sections[$i]['mode'] = 'blocs';
        elseif ($s['citations'])            $sections[$i]['mode'] = 'citations';
        else                                $sections[$i]['mode'] = 'texte';
    }

    return $sections;
}

/** Rend une section selon la forme déduite. */
function apropos_section(array $s): string
{
    $h = '<section class="ap-section">';

    if ($s['titre'] !== '' || $s['intro'] !== '') {
        $h .= '<div class="ap-head">';
        if ($s['titre'] !== '') $h .= '<h2>' . e($s['titre']) . '</h2>';
        // L'intro garde sa mise en forme : c'est du texte rédigé, pas une étiquette.
        if ($s['intro'] !== '') $h .= '<div class="rich ap-intro">' . $s['intro'] . '</div>';
        $h .= '</div>';
    }

    switch ($s['mode']) {

        case 'cartes':
            $h .= '<div class="ap-cartes">';
            foreach ($s['items'] as $n => $it) {
                // La première carte porte le jaune du logo. Une seule fois par
                // section : au-delà, l'accent cesse d'accentuer.
                $h .= '<article class="ap-carte' . ($n === 0 ? ' ap-carte-phare' : '') . '">';
                $h .= '<span class="ap-num">' . str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT) . '</span>';
                $h .= '<h3>' . e($it['titre']) . '</h3>';
                if ($it['intro'] !== '') $h .= '<div class="rich ap-carte-intro">' . $it['intro'] . '</div>';
                if ($it['liste']) {
                    $h .= '<ul class="ap-liste">';
                    foreach ($it['liste'] as $l) $h .= '<li>' . e($l) . '</li>';
                    $h .= '</ul>';
                }
                $h .= '</article>';
            }
            $h .= '</div>';
            break;

        case 'blocs':
            $h .= '<div class="ap-blocs">';
            foreach ($s['items'] as $n => $it) {
                $h .= '<div class="ap-bloc' . ($n === 0 ? ' ap-bloc-fort' : '') . '">';
                $h .= '<h3>' . e($it['titre']) . '</h3>';
                if ($it['intro'] !== '') $h .= '<div class="rich">' . $it['intro'] . '</div>';
                $h .= '</div>';
            }
            $h .= '</div>';
            break;

        case 'citations':
            $h .= '<div class="ap-citations">';
            foreach ($s['citations'] as $c) {
                $h .= '<blockquote class="ap-citation"><p>' . e($c) . '</p></blockquote>';
            }
            $h .= '</div>';
            break;
    }

    if (trim($s['outro'] ?? '') !== '') {
        $h .= '<div class="rich ap-outro">' . $s['outro'] . '</div>';
    }

    return $h . '</section>';
}

}
