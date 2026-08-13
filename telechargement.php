<?php
/**
 * Le portier des médias du Catalogue.      [V42-PORTIER] [13.08.2026]
 *
 * CE FICHIER MANQUAIT DEPUIS LE DÉBUT. Toute la colonne de téléchargement de la
 * fiche pointait vers lui (catalog_item.php:156) et il n'a jamais existé : le
 * routeur renvoyait la demande vers index.php, qui redirigeait vers
 * /fr/telechargement.php EN PERDANT la query string, et rendait un 404. Aucun
 * fichier déposé par FTP n'aurait été atteignable par l'interface.
 *
 * ET LE MOT DE PASSE NE PROTÉGEAIT QUE LA PAGE. La captation était servie par
 * un `<video src="/medias/...">`, une adresse statique qu'Apache sert sans
 * jamais passer par PHP. Les noms de fichiers sont fixes et documentés, les
 * slugs sont dans le sitemap : la captation intégrale s'ouvrait sans mot de
 * passe. C'est le contraire de ce que le Catalogue existe pour faire.
 *
 * Ce fichier sert donc les deux : le téléchargement d'un document et la lecture
 * de la vidéo, derrière la même porte.
 *
 * LES REQUÊTES PARTIELLES SONT INDISPENSABLES ICI, et c'est la seule vraie
 * difficulté. Sans elles un navigateur ne sait pas se déplacer dans une vidéo :
 * la barre de temps devient décorative, et sur iOS la lecture ne démarre même
 * pas. Apache les gérait pour rien ; en passant par PHP il faut les écrire.
 *
 * Fichier neuf, volontairement : sur ce serveur le cache d'opcode empêche la
 * mise à jour d'index.php, et un fichier neuf compile toujours.
 */
require __DIR__ . '/app/bootstrap.php';
I18n::init();
session_boot();

/* La porte, avant tout le reste. Le bureau passe : il vient de déposer ces
   fichiers, lui redemander le mot de passe du Catalogue n'ajoute rien. */
if (!Auth::check() && !CatalogAuth::check()) redirect('/catalogue.php');

$pid = (int)($_GET['p'] ?? 0);
$rel = (string)($_GET['f'] ?? '');

$projet = $pid > 0 ? DB::one('SELECT id, media_slug FROM projects WHERE id = ?', [$pid]) : null;
if (!$projet) { http_response_code(404); exit('Not found'); }

/* LE CHEMIN SE RECONSTRUIT, IL NE SE REÇOIT PAS. Le dossier vient de la base,
   passé par le nettoyeur de Catalog ; de l'adresse on ne garde que deux
   morceaux, chacun réduit aux caractères d'un nom de fichier. Rien de ce que
   le visiteur écrit ne devient un chemin : « ../ » n'a nulle part où aller. */
$base = Catalog::dossier((string)$projet['media_slug']);
$bouts = explode('/', $rel);
if ($base === '' || count($bouts) !== 2) { http_response_code(404); exit('Not found'); }

$sous = preg_replace('/[^a-z]/', '', mb_strtolower($bouts[0]));
$nom  = preg_replace('/[^A-Za-z0-9._-]/', '', $bouts[1]);
if (!in_array($sous, ['video', 'photos', 'docs'], true) || $nom === '' || str_contains($nom, '..')) {
    http_response_code(404); exit('Not found');
}

$chemin = $base . '/' . $sous . '/' . $nom;
if (!is_file($chemin)) { http_response_code(404); exit('Not found'); }

$taille = (int)filesize($chemin);
$ext    = mb_strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
$mimes  = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
           'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
           'png' => 'image/png', 'zip' => 'application/zip'];
$mime   = $mimes[$ext] ?? 'application/octet-stream';
$video  = str_starts_with($mime, 'video/');

/* Une vidéo se regarde dans la page, un document se garde. */
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . ($video ? 'inline' : 'attachment') . '; filename="' . addslashes($nom) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Accept-Ranges: bytes');

/* ---- La requête partielle ------------------------------------------------
   Le navigateur demande « les octets 1000 à 1999 ». On répond 206 avec ce
   morceau et l'on dit de quoi il est un morceau. Une demande qu'on ne sait pas
   lire, ou qui sort du fichier, reçoit un 416 avec la taille réelle — c'est ce
   qui permet au navigateur de se corriger tout seul. */
$debut = 0;
$fin   = $taille - 1;
$range = (string)($_SERVER['HTTP_RANGE'] ?? '');

if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
    $d = $m[1] === '' ? null : (int)$m[1];
    $f = $m[2] === '' ? null : (int)$m[2];
    if ($d === null && $f !== null) {          // « les N derniers octets »
        $debut = max(0, $taille - $f);
    } elseif ($d !== null) {
        $debut = $d;
        if ($f !== null) $fin = min($f, $taille - 1);
    }
    if ($debut > $fin || $debut >= $taille) {
        http_response_code(416);
        header('Content-Range: bytes */' . $taille);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $debut . '-' . $fin . '/' . $taille);
}

header('Content-Length: ' . ($fin - $debut + 1));

/* Par tranches, et non d'un coup : une captation d'un giga-octet lue par
   readfile() la charge en mémoire et tue le processus. */
$fh = fopen($chemin, 'rb');
if (!$fh) { http_response_code(404); exit('Not found'); }
fseek($fh, $debut);
$reste = $fin - $debut + 1;
while ($reste > 0 && !feof($fh)) {
    $bloc = fread($fh, (int)min(262144, $reste));
    if ($bloc === false || $bloc === '') break;
    echo $bloc;
    $reste -= strlen($bloc);
    if (connection_aborted()) break;   // la personne a fermé l'onglet
    @flush();
}
fclose($fh);
