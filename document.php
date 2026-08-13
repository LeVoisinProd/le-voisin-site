<?php
/**
 * Le portier des documents du CMS.        [V42-PORTIER] [13.08.2026]
 *
 * POURQUOI CE FICHIER EXISTE
 *
 * Le 11.08 les fiches techniques ont quitté la page publique d'un projet pour
 * aller derrière le mot de passe du Catalogue. On a retiré les liens, et l'on a
 * cru l'affaire close. Elle ne l'était pas : les fichiers n'ont pas bougé.
 * `/uploads/d/12/rider-fr.pdf` répondait encore 200, servi par Apache sans
 * jamais passer par PHP — donc sans jamais voir une session. Un signet, un
 * courriel d'avant le 11.08, ou une recherche Google, et le rider s'ouvrait.
 * **Le mot de passe protégeait la page, pas les fichiers.**
 *
 * LA RÈGLE, ET ELLE EST PLUS ÉTROITE QU'IL N'Y PARAÎT
 *
 * « zone = doc » ne veut pas dire privé : c'est la zone par défaut, et les
 * documents d'un ARTISTE comme ceux d'une PAGE s'affichent publiquement dans
 * cette zone (index.php:229 et :455 les demandent sans filtre). Fermer la zone
 * entière casserait les pages d'artistes.
 *
 * Ce qui est réservé, c'est exactement : **un document de PROJET en zone
 * « doc »**. C'est ce que catalog_item.php montre derrière le mot de passe, et
 * rien d'autre ne l'affiche. Tout le reste passe.
 *
 * CE QUE CE FICHIER NE FAIT PAS : deviner. Un document qu'il ne trouve pas est
 * un 404, pas un message qui expliquerait ce qui existe.
 *
 * Fichier neuf, volontairement : sur ce serveur le cache d'opcode empêche la
 * mise à jour d'index.php, et un fichier neuf compile toujours.
 */
require __DIR__ . '/app/bootstrap.php';
I18n::init();

$id  = (int)($_GET['d'] ?? 0);
$doc = $id > 0 ? DB::one('SELECT * FROM documents WHERE id = ?', [$id]) : null;

if (!$doc) { http_response_code(404); exit('Not found'); }

/* Un document « lien » n'a pas de fichier : il pointe ailleurs. */
if (trim((string)($doc['url'] ?? '')) !== '') redirect((string)$doc['url']);

$reserve = (string)($doc['owner_type'] ?? '') === 'project'
        && (string)($doc['zone'] ?? '') === 'doc';

if ($reserve) {
    /* Le bureau passe : il est déjà chez lui, et lui demander en plus le mot de
       passe du Catalogue pour relire un rider qu'il vient de déposer n'ajoute
       rien. Sinon, la porte du Catalogue, qui ramènera ici après. */
    if (!Auth::check() && !CatalogAuth::check()) {
        redirect('/catalogue.php');
    }
}

/* Docs::dir() et non le chemin écrit à la main : c'est lui qui dépose la règle
   de refus dans uploads/d/ au premier passage. Écrite seulement au dépôt, elle
   pourrait n'être jamais écrite — il suffit que personne ne dépose. Ici elle
   l'est dès la première lecture d'un document, c'est-à-dire tout de suite. */
$chemin = Docs::dir((int)$doc['id']) . '/' . (string)$doc['filename'];
if (!is_file($chemin)) { http_response_code(404); exit('Not found'); }

/* Le nom lisible plutôt que le nom de fichier : c'est celui-ci que la personne
   retrouvera dans son dossier de téléchargements. */
$nom = trim((string)($doc['title'] ?? '')) !== ''
     ? preg_replace('/[^\p{L}\p{N} ._-]+/u', '', (string)$doc['title'])
       . '.' . pathinfo((string)$doc['filename'], PATHINFO_EXTENSION)
     : (string)$doc['filename'];

/* Le type est lu du fichier quand le serveur sait le faire, et retombe sinon
   sur l'extension : un PDF servi en octet-stream se télécharge au lieu de
   s'ouvrir, ce qui est gênant sans être grave. */
$ext  = mb_strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
$mime = '';
if (function_exists('mime_content_type')) $mime = (string)@mime_content_type($chemin);
if ($mime === '') {
    $mime = ['pdf' => 'application/pdf', 'zip' => 'application/zip',
             'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
             'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
             'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
             'ppt' => 'application/vnd.ms-powerpoint',
             'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ][$ext] ?? 'application/octet-stream';
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($chemin));
header('Content-Disposition: inline; filename="' . addslashes($nom) . '"');
header('X-Content-Type-Options: nosniff');
/* Réservé : jamais dans un cache partagé, et jamais indexé. */
header($reserve ? 'Cache-Control: private, no-store' : 'Cache-Control: public, max-age=86400');
if ($reserve) header('X-Robots-Tag: noindex, nofollow');
readfile($chemin);
