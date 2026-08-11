<?php
/**
 * Téléversement d'une vidéo auto-hébergée (bandeau d'accueil, fiches…).
 * [V10-CMS-BILINGUE] — messages d'erreur traduits.
 */
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

$owner = explode(':', (string)($_POST['owner'] ?? ''));
$type = $owner[0] ?? '';
$oid = (int)($owner[1] ?? 0);

$allowedTypes = array_merge(['page'], array_keys(Content::entities()));
if (!in_array($type, $allowedTypes, true) || $oid <= 0) json_out(['error' => 'owner'], 400);
if (empty($_FILES['file'])) json_out(['error' => tu('sys_no_file')], 400);

try {
    $row = VideoLib::storeUpload($_FILES['file'], $type, $oid);
} catch (Throwable $ex) {
    json_out(['error' => $ex->getMessage()], 422);
}

json_out(['ok' => true, 'id' => (int)$row['id'], 'html' => video_item_html($row)]);
