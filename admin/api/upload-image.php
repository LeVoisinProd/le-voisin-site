<?php
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

$owner = explode(':', (string)($_POST['owner'] ?? ''));
$type = $owner[0] ?? '';
$oid = (int)($owner[1] ?? 0);
$zone = (string)($_POST['zone'] ?? 'gallery');

$allowedTypes = array_merge(['page', 'doc', 'site'], array_keys(Content::entities()));
if (!in_array($type, $allowedTypes, true) || $oid < 0) json_out(['error' => 'owner'], 400);
if (!isset(Img::conf()['zones'][$zone])) json_out(['error' => 'zone'], 400);
if (empty($_FILES['file'])) json_out(['error' => 'file'], 400);

try {
    $row = Img::upload($_FILES['file'], $type, $oid, $zone);
} catch (Throwable $ex) {
    json_out(['error' => $ex->getMessage()], 422);
}

Img::ensure($row, 'thumb');
json_out([
    'id'     => (int)$row['id'],
    'thumb'  => Img::fileUrl($row, 'thumb', 'jpg') . '?t=' . time(),
    'width'  => (int)$row['width'],
    'height' => (int)$row['height'],
    'html'   => $zone === 'gallery' ? gallery_item_html($row) : null,
]);
