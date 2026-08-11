<?php
/** Petites aides d'affichage communes aux templates publics.   [V8-CADRAGE] */

/** URL d'une fiche projet/artiste. */
function detail_url(string $module, array $row, ?string $lang = null): string
{
    $lang ??= I18n::$lang;
    $mp = Pages::moduleP($module);
    if (!$mp) return url('/' . $lang);
    $slug = trim((string)($row['slug_' . $lang] ?? '')) ?: trim((string)($row['slug_' . I18n::$default] ?? ''));
    return url('/' . $lang . '/' . Pages::path($mp, $lang) . '/' . $slug);
}

/**
 * Infos pratiques : les lignes restées sans réponse ne sortent pas.  [V30-INFOS]
 *
 * Les fiches projet reçoivent un canevas — « Durée : », « Langue : », « Âge : » —
 * pour qu'il n'y ait plus qu'à compléter. Tant qu'une ligne n'est pas complétée,
 * elle n'a rien à dire au public : « Langue : » tout seul, sur le site, ressemble
 * à une panne. Une ligne qui se termine par deux points et rien après est donc
 * retirée à l'affichage.
 *
 * Elle reste intacte dans l'administration, où elle sert de rappel de ce qu'il
 * reste à remplir. Écrire la réponse la fait apparaître, l'effacer la fait
 * disparaître : rien d'autre à faire, et rien de perdu.
 *
 * Le découpage suit ce que produit l'éditeur de texte : un paragraphe par
 * touche Entrée, un « <br> » par Maj+Entrée.
 */
function infos_pratiques(string $html): string
{
    if (trim(strip_tags($html)) === '') return '';

    $enAttente = static function (string $segment): bool {
        $t = html_entity_decode(strip_tags($segment), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = trim(preg_replace('~[\s\x{00A0}]+~u', ' ', $t) ?? $t);
        return $t !== '' && preg_match('~[:：]$~u', $t) === 1;
    };

    $out = preg_replace_callback('~(<p\b[^>]*>)(.*?)(</p>)~isu', static function (array $m) use ($enAttente) {
        $lignes  = preg_split('~<br\s*/?>~i', $m[2]) ?: [$m[2]];
        $gardees = array_values(array_filter($lignes, static fn($l) => !$enAttente($l)));
        if (!$gardees) return '';
        return $m[1] . implode('<br>', $gardees) . $m[3];
    }, $html);

    $out = trim($out ?? $html);
    return trim(strip_tags($out)) === '' ? '' : $out;
}

/**
 * Distribution et infos pratiques : les deux points tombent.  [V31-DEUXPOINTS]
 *
 * « Durée : 60 minutes », « Mise en scène : Prénom Nom » — le deux-points est
 * une ponctuation de formulaire. Dans une colonne où chaque ligne est déjà un
 * intitulé suivi de sa réponse, il ne dit rien que la mise en page ne dise
 * mieux, et il alourdit une page qu'on cherche justement à alléger.
 *
 * Il n'est retiré qu'à l'affichage. Dans l'administration, le canevas garde
 * ses deux points : ils s'écrivent naturellement en saisissant, et surtout
 * c'est à eux qu'on reconnaît une ligne restée sans réponse — c'est ce qui
 * permet à infos_pratiques() de ne pas publier un « Langue » orphelin. Les
 * effacer dans la base coûterait ce repère ; les effacer ici ne coûte rien et
 * vaut pour toutes les fiches, y compris celles qui n'existent pas encore.
 *
 * Seul disparaît le deux-points qui sépare, c'est-à-dire celui qui a du blanc
 * d'un côté au moins. Une heure — « 20:00 » —, une adresse — « https://… » —
 * n'en ont pas et restent intactes. Le texte des balises n'est jamais touché :
 * on découpe sur les balises et on ne travaille qu'entre elles, sinon un
 * « style="color: red" » laissé par l'éditeur y passerait aussi.
 */
function sans_deux_points(string $html): string
{
    if (strpos($html, ':') === false && strpos($html, '：') === false) return $html;

    // Espace au sens large : l'éditeur écrit volontiers l'espace insécable
    // française sous forme d'entité, qui n'est pas un blanc pour une regex.
    $blanc = '(?:[\s\x{00A0}]|&nbsp;|&#0*160;|&#[xX]0*[aA]0;)';

    $morceaux = preg_split('~(<[^>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
    foreach ($morceaux as $i => $m) {
        if ($m === '' || $m[0] === '<') continue;
        $m = preg_replace('~' . $blanc . '+[:：]' . $blanc . '*~u', ' ', $m) ?? $m;
        $m = preg_replace('~[:：]' . $blanc . '+~u', ' ', $m) ?? $m;
        // Un deux-points collé à la balise suivante — « Avec:</p> » — n'a de
        // blanc d'aucun côté mais sépare tout de même : il s'en va aussi.
        $m = preg_replace('~[:：]' . $blanc . '*$~u', '', $m) ?? $m;
        $morceaux[$i] = preg_replace('~[ ]{2,}~', ' ', $m) ?? $m;
    }
    return implode('', $morceaux);
}

/** Noms des artistes liés à un projet ("Ruth Childs & Cecile Bouffard"). */
function project_artists_names(int $projectId): string
{
    $rows = DB::all(
        'SELECT a.name FROM artists a JOIN project_artists p ON p.artist_id = a.id
         WHERE p.project_id = ? AND a.visible = 1 ORDER BY a.sort, a.id',
        [$projectId]
    );
    return implode(' & ', array_column($rows, 'name'));
}

/** Carte projet : titre en grand blanc sur l'image, artistes en dessous. */
function card(string $module, array $row, string $title, ?string $sub = null): string
{
    $img = !empty($row['cover_image_id']) ? Img::row((int)$row['cover_image_id']) : null;
    $html  = '<a class="card pcard" href="' . e(detail_url($module, $row)) . '">';
    $html .= '<span class="card-img">' . ($img ? Img::tag($img, 'card', ['alt' => $title]) : '<span class="card-ph" aria-hidden="true">' . e(mb_substr($title, 0, 1)) . '</span>') . '</span>';
    $html .= '<span class="pcard-overlay">';
    $html .= '<span class="pcard-title">' . e($title) . '</span>';
    if ($sub) $html .= '<span class="pcard-artists">' . e($sub) . '</span>';
    return $html . '</span></a>';
}

/** Ligne d'agenda. */
function event_row(array $ev): string
{
    $img = event_img($ev);
    $artist = trim((string)($ev['artist_name'] ?? ''));
    $projTitle = f($ev, 'project_title');
    $html = '<article class="event">';
    $html .= '<div class="event-img">' . ($img ? Img::tag($img, 'card', ['alt' => $artist ?: $projTitle]) : '<span class="card-ph" aria-hidden="true">•</span>') . '</div>';
    $html .= '<div class="event-body">';
    $html .= '<p class="event-date">' . e(f($ev, 'date_text')) . '</p>';
    $head = [];
    if ($artist !== '') {
        $aRow = ['slug_en' => $ev['artist_slug_en'] ?? '', 'slug_fr' => $ev['artist_slug_fr'] ?? ''];
        $head[] = '<a href="' . e(detail_url('artists', $aRow)) . '">' . e($artist) . '</a>';
    }
    if ($projTitle !== '') {
        $pRow = ['slug_en' => $ev['project_slug_en'] ?? '', 'slug_fr' => $ev['project_slug_fr'] ?? ''];
        $head[] = '<a href="' . e(detail_url('projects', $pRow)) . '">' . e(html_entity_decode($projTitle)) . '</a>';
    }
    if ($head) {
        $html .= '<h3 class="event-title">' . $head[0] . '</h3>';
        if (isset($head[1])) $html .= '<p class="event-proj">' . $head[1] . '</p>';
    }
    $venue = trim((string)$ev['venue']);
    $place = $venue . ($ev['city'] !== '' ? ' — ' . $ev['city'] : '');
    if ($ev['venue_url'] !== '') {
        $html .= '<p class="event-venue"><a href="' . e($ev['venue_url']) . '" target="_blank" rel="noopener">' . e($place) . '</a></p>';
    } elseif ($place !== '') {
        $html .= '<p class="event-venue">' . e($place) . '</p>';
    }
    return $html . '</div></article>';
}

/**
 * Élément « programmation » illustré (accueil) : grande photo arrondie à gauche,
 * date + artiste + titre + lieu + court texte à droite. Empilé sur mobile.
 */
function event_feature(array $ev): string
{
    $img = event_img($ev);
    $artist = trim((string)($ev['artist_name'] ?? ''));
    $projTitle = html_entity_decode(f($ev, 'project_title'));
    $intro = trim((string)f($ev, 'project_intro'));

    $artistHref = $artist !== '' ? detail_url('artists', ['slug_en' => $ev['artist_slug_en'] ?? '', 'slug_fr' => $ev['artist_slug_fr'] ?? '']) : '';
    $projHref = (($ev['project_slug_en'] ?? '') !== '' || ($ev['project_slug_fr'] ?? '') !== '')
        ? detail_url('projects', ['slug_en' => $ev['project_slug_en'] ?? '', 'slug_fr' => $ev['project_slug_fr'] ?? '']) : '';
    $mainHref = $projHref ?: ($artistHref ?: '');

    $html  = '<article class="prog-item">';
    $open = $mainHref !== '' ? '<a class="prog-media" href="' . e($mainHref) . '">' : '<span class="prog-media">';
    $close = $mainHref !== '' ? '</a>' : '</span>';
    $html .= $open;
    if ($img) { Img::ensure($img, 'card'); $html .= Img::tag($img, 'card', ['alt' => trim($artist . ' ' . $projTitle)]); }
    else { $html .= '<span class="card-ph" aria-hidden="true">•</span>'; }
    $html .= $close;

    $html .= '<div class="prog-body">';
    if (f($ev, 'date_text') !== '') $html .= '<p class="prog-date">' . e(f($ev, 'date_text')) . '</p>';
    if ($artist !== '') {
        $html .= '<h3 class="prog-artist">' . ($artistHref ? '<a href="' . e($artistHref) . '">' . e($artist) . '</a>' : e($artist)) . '</h3>';
    }
    if ($projTitle !== '') {
        $html .= '<p class="prog-title">' . ($projHref ? '<a href="' . e($projHref) . '">' . e($projTitle) . '</a>' : e($projTitle)) . '</p>';
    }
    $venue = trim((string)$ev['venue']);
    $place = $venue . ($ev['city'] !== '' ? ' — ' . $ev['city'] : '');
    if ($place !== '') {
        $html .= '<p class="prog-place">' . ($ev['venue_url'] !== ''
            ? '<a href="' . e($ev['venue_url']) . '" target="_blank" rel="noopener">' . e($place) . ' ' . Ico::ext() . '</a>'
            : e($place)) . '</p>';
    }
    if ($intro !== '') $html .= '<p class="prog-text">' . e($intro) . '</p>';
    return $html . '</div></article>';
}

/**
 * Carte d'agenda (grille 4 colonnes, texte sur l'image — style Tortoise).
 */
function event_card(array $ev): string
{
    $img = event_img($ev);
    $artist = trim((string)($ev['artist_name'] ?? ''));
    $projTitle = html_entity_decode(f($ev, 'project_title'));

    $html  = '<article class="ecard">';
    $html .= '<div class="ecard-media">';
    if ($img) {
        Img::ensure($img, 'card');
        $html .= Img::tag($img, 'card', ['alt' => trim($artist . ' ' . $projTitle)]);
    }
    $html .= '</div>';
    $html .= '<div class="ecard-overlay">';
    $html .= '<p class="ecard-date">' . e(f($ev, 'date_text')) . '</p>';

    // [V23-TOURNEE] Ordre demandé : date (gras) / titre du projet (normal) /
    // nom de l'artiste (gras) / lieu en bas. La première ligne présente reçoit
    // la classe « ecard-lead » qui lui donne la taille de titre.
    $lignes = [];
    if ($projTitle !== '') {
        $pRow = ['slug_en' => $ev['project_slug_en'] ?? '', 'slug_fr' => $ev['project_slug_fr'] ?? ''];
        $lignes[] = ['ecard-proj', '<a href="' . e(detail_url('projects', $pRow)) . '">' . e($projTitle) . '</a>'];
    }
    if ($artist !== '') {
        $aRow = ['slug_en' => $ev['artist_slug_en'] ?? '', 'slug_fr' => $ev['artist_slug_fr'] ?? ''];
        $lignes[] = ['ecard-artist', '<a href="' . e(detail_url('artists', $aRow)) . '">' . e($artist) . '</a>'];
    }
    $premiere = true;
    foreach ($lignes as [$classe, $lien]) {
        $balise = $premiere ? 'h3' : 'p';
        $html .= '<' . $balise . ' class="' . $classe . ($premiere ? ' ecard-lead' : '') . '">'
               . $lien . '</' . $balise . '>';
        $premiere = false;
    }

    $venue = trim((string)$ev['venue']);
    $place = $venue . ($ev['city'] !== '' ? ' — ' . $ev['city'] : '');
    if ($place !== '') {
        if ($ev['venue_url'] !== '') {
            $html .= '<a class="ecard-btn" href="' . e($ev['venue_url']) . '" target="_blank" rel="noopener">' . e($place) . ' ' . Ico::ext() . '</a>';
        } else {
            $html .= '<span class="ecard-btn is-static">' . e($place) . '</span>';
        }
    }
    return $html . '</div></article>';
}

/** Vidéo intégrée derrière le consentement « médias externes ». */
function video_embed(array $v, bool $hero = false): string
{
    if (($v['provider'] ?? '') === 'file') {
        $title = $v['title'] !== '' ? $v['title'] : 'Vidéo';
        if ($hero) {
            // Bandeau d'accueil : pleine largeur, lecture automatique en boucle (muet — requis par les navigateurs pour l'autoplay).
            return '<figure class="video video-file video-hero">'
                . '<video src="' . e($v['url']) . '" autoplay muted loop playsinline preload="auto" aria-label="' . e($title) . '"></video>'
                . '</figure>';
        }
        $poster = $v['thumb'] !== '' ? ' poster="' . e($v['thumb']) . '"' : '';
        return '<figure class="video video-file">'
            . '<video src="' . e($v['url']) . '#t=0.1"' . $poster
            . ' controls playsinline preload="metadata" aria-label="' . e($title) . '"></video>'
            . '</figure>';
    }
    $embed = VideoLib::embedUrl($v['provider'], $v['vid']);
    $watch = $v['url'] ?: VideoLib::watchUrl($v['provider'], $v['vid']);
    $title = $v['title'] !== '' ? $v['title'] : ucfirst($v['provider']);
    $html  = '<figure class="video js-video" data-embed="' . e($embed) . '" data-title="' . e($title) . '">';
    $html .= '<div class="video-locked">';
    if ($v['thumb'] !== '') {
        $html .= '<img class="video-thumb" src="' . e($v['thumb']) . '" alt="" loading="lazy">';
    }
    $html .= '<div class="video-msg"><p>' . e(t('video_locked_text')) . '</p>';
    $html .= '<button type="button" class="btn js-video-allow">' . e(t('video_locked_btn')) . '</button> ';
    $html .= '<a class="video-ext" href="' . e($watch) . '" target="_blank" rel="noopener">' . e(t('video_locked_link')) . ' ' . Ico::ext() . '</a>';
    $html .= '</div></div>';
    $html .= '<figcaption>' . e($title) . '</figcaption></figure>';
    return $html;
}

/**
 * Lecteur Spotify.   [V26-SPOTIFY]
 *
 * Même prudence que pour les vidéos : rien n'est demandé à Spotify tant que
 * le visiteur n'a pas accepté les « médias externes ». Le bloc réutilise donc
 * exactement la mécanique existante — la classe .js-video et l'attribut
 * data-embed — et le script du site remplace le cache par l'iframe dès que le
 * consentement est donné. Aucun code supplémentaire n'est nécessaire côté
 * navigateur.
 *
 * Retourne une chaîne vide si l'adresse n'est pas un lien Spotify : la fiche
 * n'affiche alors simplement pas de bloc.
 */
function spotify_card(string $url, string $title = ''): string
{
    $sp = Spotify::parse($url);
    if (!$sp) return '';
    $embed   = Spotify::embedUrl($sp['kind'], $sp['id']);
    $page    = Spotify::pageUrl($sp['kind'], $sp['id']);
    $compact = Spotify::height($sp['kind']) < 300 ? ' sp-compact' : '';
    $label   = trim($title) !== '' ? 'Spotify — ' . trim($title) : 'Spotify';

    $html  = '<figure class="spotify js-video' . $compact . '"'
           . ' data-embed="' . e($embed) . '" data-title="' . e($label) . '">';
    $html .= '<div class="video-locked spotify-locked">';
    $html .= '<div class="video-msg"><p>' . e(t('spotify_locked_text')) . '</p>';
    $html .= '<button type="button" class="btn small js-video-allow">' . e(t('video_locked_btn')) . '</button> ';
    $html .= '<a class="video-ext" href="' . e($page) . '" target="_blank" rel="noopener">' . e(t('spotify_open')) . ' ' . Ico::ext() . '</a>';
    $html .= '</div></div></figure>';
    return $html;
}

/**
 * Carte Instagram.   [V28-INSTAGRAM]
 *
 * Le champ du CMS contient une adresse par ligne : le compte, et les
 * publications à mettre en avant. La carte affiche les publications puis,
 * tout en bas, le bouton qui mène au compte — celui-là ne périme jamais et
 * reste juste même quand les publications choisies datent un peu.
 *
 * Deux publications au plus. Ce n'est pas une limite technique : au-delà, la
 * colonne de droite s'allonge démesurément, et c'est précisément ce qu'on a
 * voulu éviter en renvoyant les vidéos à gauche.
 *
 * Même prudence que pour les vidéos et Spotify : rien n'est demandé à
 * Instagram tant que le visiteur n'a pas accepté les « médias externes ». Le
 * bouton vers le compte, lui, est un simple lien : il s'affiche toujours.
 *
 * Retourne une chaîne vide si le champ ne contient rien d'exploitable.
 */
function instagram_card(string $raw, string $title = '', int $max = 2): string
{
    $lu = Instagram::lire($raw);
    if (!$lu['ok']) return '';

    $compte = null;
    $posts  = [];
    foreach ($lu['ok'] as $ig) {
        if ($ig['kind'] === 'account') { if ($compte === null) $compte = $ig; }
        elseif (count($posts) < $max)  { $posts[] = $ig; }
    }
    if (!$compte && !$posts) return '';

    $html = '';
    foreach ($posts as $ig) {
        $embed = Instagram::embedUrl($ig);
        $page  = Instagram::pageUrl($ig);
        $label = trim($title) !== '' ? 'Instagram — ' . trim($title) : 'Instagram';
        $html .= '<figure class="insta js-video" data-embed="' . e($embed) . '" data-title="' . e($label) . '">';
        $html .= '<div class="video-locked insta-locked">';
        $html .= '<div class="video-msg"><p>' . e(t('insta_locked_text')) . '</p>';
        $html .= '<button type="button" class="btn small js-video-allow">' . e(t('video_locked_btn')) . '</button> ';
        $html .= '<a class="video-ext" href="' . e($page) . '" target="_blank" rel="noopener">' . e(t('insta_open')) . ' ' . Ico::ext() . '</a>';
        $html .= '</div></div></figure>';
    }
    if ($compte) {
        $html .= '<p class="insta-compte"><a href="' . e(Instagram::pageUrl($compte)) . '" target="_blank" rel="noopener">'
              .  '<strong>@' . e($compte['handle']) . '</strong> <span>' . e(t('insta_all')) . ' ' . Ico::ext() . '</span></a></p>';
    }
    return $html;
}

/**
 * Carte « Site internet ».   [V31-SITE-ARTISTE]
 *
 * Sous Instagram, dans la colonne de droite de la fiche artiste : le lien vers
 * le site personnel de l'artiste. Volontairement bâtie comme la ligne du compte
 * Instagram — le nom du site à gauche, l'invitation à droite — pour que les
 * deux cartes se ressemblent au lieu de se concurrencer.
 *
 * Rien n'est chargé depuis l'extérieur : c'est un lien, pas un lecteur. Il n'y
 * a donc pas de cache de consentement à lever avant de le voir, contrairement
 * à Spotify et aux publications Instagram.
 *
 * Ce qu'on montre est le nom de domaine sans « www. » ni « https:// » —
 * « lesitedelartiste.ch » se lit, l'adresse complète encombre. Si l'adresse ne
 * ressemble à rien, on retourne une chaîne vide et la fiche n'affiche
 * simplement pas de bloc.
 */
function site_card(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';

    /* Une adresse tapée « exemple.com » est une adresse : on complète le
       préfixe manquant plutôt que d'écarter le lien pour si peu. */
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';

    $hote = (string)parse_url($url, PHP_URL_HOST);
    if ($hote === '') return '';
    $nom = preg_replace('/^www\./i', '', $hote);

    return '<p class="site-lien"><a href="' . e($url) . '" target="_blank" rel="noopener">'
         . '<strong>' . e($nom) . '</strong> <span>' . e(t('site_open')) . ' ' . Ico::ext() . '</span></a></p>';
}

/** Bandeau vidéo d'accueil : une seule vidéo, ou un carrousel (durée réglable par vidéo). */
function hero_carousel(array $videos): string
{
    $videos = array_values($videos);
    if (!$videos) return '';
    if (count($videos) === 1) {
        return '<div class="hero-carousel single">' . video_embed($videos[0], true) . '</div>';
    }
    $n = count($videos);
    $slides = '';
    $dots = '';
    foreach ($videos as $i => $v) {
        $secs = max(1, min(60, (int)($v['duration'] ?? 6)));
        $slides .= '<div class="hero-slide' . ($i === 0 ? ' on' : '') . '" data-secs="' . $secs . '"'
            . ' role="group" aria-roledescription="slide" aria-label="' . ($i + 1) . ' / ' . $n . '">'
            . video_embed($v, true) . '</div>';
        $dots .= '<button type="button" class="hero-dot' . ($i === 0 ? ' on' : '') . '" data-i="' . $i . '" aria-label="' . ($i + 1) . '"></button>';
    }
    return '<div class="hero-carousel js-hero-carousel" data-count="' . $n . '" aria-roledescription="carrousel">'
        . '<div class="hero-slides">' . $slides . '</div>'
        . '<button type="button" class="hero-nav prev" aria-label="‹">‹</button>'
        . '<button type="button" class="hero-nav next" aria-label="›">›</button>'
        . '<div class="hero-dots">' . $dots . '</div>'
        . '</div>';
}

/**
 * Liste de documents, en vignettes ou en lignes.
 *
 * [V16-DOCS] En vignettes, la liste dit elle-même combien de fichiers elle
 * contient, via --docs-n. La grille n'a donc plus à deviner le nombre de
 * colonnes d'après la largeur disponible : quatre documents font quatre
 * colonnes, et restent sur une seule ligne au lieu de basculer en trois plus
 * un dès que la fenêtre se rétrécit un peu. Au-delà de cinq, on repasse à
 * cinq par ligne : au-delà les vignettes deviendraient illisibles.
 *
 * [V31-DOCS-LISTE] En lignes ($compact), plus de vignette : un titre par
 * ligne, avec le format et le poids à droite.
 *
 * Les deux présentations existent parce qu'elles ne servent pas le même
 * nombre de fichiers. Sur la page Formulaires, quatre documents sont quatre
 * boutons qu'on veut voir de loin : la vignette est le bouton. Dans la
 * colonne d'une fiche projet, la liste peut compter quinze pièces — dossier
 * de presse, fiche technique, plan de feu, contrat type… —, et quinze
 * vignettes sur deux colonnes font une colonne haute de deux écrans où le
 * titre, qui est la seule chose qu'on cherche, se perd entre les images.
 * Une couverture de PDF ne dit d'ailleurs rien : elles se ressemblent
 * toutes. Le format et le poids restent, eux : on ne clique pas de la même
 * façon sur 300 Ko et sur 40 Mo.
 *
 * [V31-DOC-LIEN] Une ligne peut aussi désigner un fichier qui n'est pas
 * hébergé ici. Elle se présente exactement comme les autres.
 */
function docs_list(array $documents, bool $compact = false): string
{
    if (!$documents) return '';
    $n = max(1, min(count($documents), 5));
    $html = $compact
        ? '<ul class="docs docs-liste">'
        : '<ul class="docs" style="--docs-n:' . $n . '">';
    foreach ($documents as $doc) {
        // [V31-DOC-LIEN] Un document hébergé ailleurs se présente comme les
        // autres. Deux différences seulement, et toutes deux pour le
        // visiteur : à droite, le nom du site remplace le poids — qu'on ne
        // connaît pas —, et la flèche prévient qu'on quitte le site.
        $lien = Docs::estLien($doc);
        $nomme = f($doc, 'title');
        $title = $nomme ?: ($lien ? Docs::hote($doc) : $doc['filename']);
        $html .= '<li><a class="doc' . ($lien ? ' doc-externe' : '') . '" href="' . e(Docs::fileUrl($doc)) . '" target="_blank" rel="noopener">';
        $fleche = $lien;
        if (!$compact) {
            $cover = !empty($doc['cover_image_id']) ? Img::row((int)$doc['cover_image_id']) : null;
            // Un lien dont l'adresse ne dit pas le format n'a pas d'étiquette à
            // mettre dans la vignette : la flèche y va, et pas deux fois.
            $etiquette = $doc['ext'] !== '' ? strtoupper($doc['ext']) : '';
            $flecheVignette = $etiquette === '' && $lien;
            if ($flecheVignette) $fleche = false;
            // La flèche est un dessin, pas un mot : elle ne passe pas par e().
            $vignette = $flecheVignette ? Ico::ext() : e($etiquette);
            $html .= '<span class="doc-cover">' . ($cover ? Img::tag($cover, 'doccover', ['alt' => '']) : '<span class="doc-ext">' . $vignette . '</span>') . '</span>';
        }
        $html .= '<span class="doc-title">' . e($title) . '</span>';
        // À droite : le format et le poids, ou le format et le site. Quand le
        // titre est resté vide, il affiche déjà le nom du site — inutile de
        // l'écrire deux fois sur la même ligne.
        $meta = $lien && $nomme === '' ? strtoupper((string)$doc['ext']) : Docs::meta($doc);
        // En lignes, « Télécharger » saute : le titre est déjà un lien, et
        // répété quinze fois le mot devient du bruit.
        $droite = implode(' · ', array_filter([$meta, $compact ? '' : t('download')]));
        // Le texte est échappé, le dessin se pose après — sinon il serait
        // recopié tel quel au lieu d'être tracé.
        $html .= '<span class="doc-meta">' . e($droite)
              . ($fleche ? ($droite !== '' ? ' ' : '') . Ico::ext() : '') . '</span>';
        $html .= '</a></li>';
    }
    return $html . '</ul>';
}

/**
 * Carrousel d'images (fiche projet) : les photos défilent avec flèches
 * et glisser au doigt. Sans galerie, l'image représentative sert de seule vue.
 */
function media_carousel(array $gallery, ?array $cover = null): string
{
    $slides = $gallery;
    if (!$slides && $cover) $slides = [$cover];
    if (!$slides) return '';

    $html = '<div class="carousel js-carousel' . (count($slides) < 2 ? ' single' : '') . '">';
    $html .= '<div class="carousel-track js-gallery">';
    foreach ($slides as $g) {
        // Sur la page, le cadrage « bandeau » choisi dans l'administration.
        // Au clic, la photo entière : la visionneuse n'a pas à couper.
        //
        // Avant, la page affichait elle aussi le format « gallery », qui réduit
        // la photo sans jamais la couper — le navigateur se chargeait alors de
        // la rogner tout seul pour remplir le bandeau, et le cadrage choisi
        // dans l'administration ne servait à rien.
        Img::ensure($g, 'gallery');
        $full = Img::fileUrl($g, 'gallery', 'jpg');
        $html .= '<a class="gallery-item" href="' . e($full) . '" data-alt="' . e(I18n::f($g, 'alt')) . '">'
              . Img::tag($g, 'banner') . '</a>';
    }
    $html .= '</div>';
    if (count($slides) > 1) {
        $html .= '<button type="button" class="carousel-btn prev" aria-label="‹">‹</button>';
        $html .= '<button type="button" class="carousel-btn next" aria-label="›">›</button>';
        $html .= '<span class="carousel-count">1 / ' . count($slides) . '</span>';
    }
    return $html . '</div>';
}

/** Extrait de biographie : les N premiers paragraphes. Retourne [html, tronqué?]. */
function bio_excerpt(string $html, int $paragraphs = 2): array
{
    $parts = preg_split('/<\/p>/i', $html, -1, PREG_SPLIT_NO_EMPTY);
    $parts = array_values(array_filter($parts, fn($p) => trim(strip_tags($p)) !== ''));
    $truncated = count($parts) > $paragraphs;
    $out = implode('</p>', array_slice($parts, 0, $paragraphs)) . '</p>';
    return [$out, $truncated];
}

/** Galerie photos avec visionneuse. */
function gallery_grid(array $gallery): string
{
    if (!$gallery) return '';
    $html = '<div class="gallery js-gallery">';
    foreach ($gallery as $g) {
        Img::ensure($g, 'gallery');
        $full = Img::fileUrl($g, 'gallery', 'jpg');
        $html .= '<a class="gallery-item" href="' . e($full) . '" data-alt="' . e(I18n::f($g, 'alt')) . '">'
              . Img::tag($g, 'square') . '</a>';
    }
    return $html . '</div>';
}

/** Texte de cookie éventuellement personnalisé dans le CMS. */
function ck(string $key): string
{
    $custom = setting_i18n('cookie_' . $key);
    return $custom !== '' ? $custom : t('cookies_' . $key);
}
