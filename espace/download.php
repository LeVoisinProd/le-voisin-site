<?php
/**
 * Téléchargement sécurisé : réservé au collaborateur propriétaire du document.
 * [V10-CMS-BILINGUE] — messages d'erreur traduits.
 */
require __DIR__ . '/_inc.php';
MemberAuth::requireMember();
$m = MemberAuth::member();

$id = (int)($_GET['doc'] ?? 0);
$doc = MemberDocs::row($id);

// Contrôle de propriété — un collaborateur ne peut accéder qu'à SES documents.
if (!$doc || (int)$doc['collaborator_id'] !== (int)$m['id']) {
    http_response_code(404);
    exit(tu('sys_doc_nf'));
}

$path = MemberDocs::filePath($doc, true);
if (!is_file($path)) { http_response_code(404); exit(tu('sys_file_nf')); }

$name = $doc['signed_filename'] !== '' ? $doc['signed_filename'] : ($doc['filename'] ?: ('document.' . $doc['ext']));

// [V39-JOURNAL] Pendant une visite, c'est le bureau qui regarde, pas la
// personne : la ligne doit le dire, sinon un téléchargement du bureau
// ressemblerait à une action de la personne elle-même.
$enVisite = MemberAuth::visite();
AccessLog::ecrire(
    (int)$m['id'],
    $enVisite ? 'admin' : 'member',
    $enVisite ? ((int)($_SESSION['lv_admin_id'] ?? 0) ?: null) : null,
    'download',
    $name
);

$mime = match ($doc['ext']) {
    'pdf' => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc', 'docx' => 'application/msword',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
