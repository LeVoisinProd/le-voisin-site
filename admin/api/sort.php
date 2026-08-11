<?php
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$ids = array_values(array_filter(array_map('intval', (array)($in['ids'] ?? []))));

if (($in['mode'] ?? '') === 'pages') {
    $parent = (int)($in['parent'] ?? 0) ?: null;
    foreach ($ids as $i => $id) {
        DB::update('pages', ['parent_id' => $parent, 'sort' => $i], 'id = ?', [$id]);
    }
    Pages::reset();
    json_out(['ok' => true]);
}

$table = (string)($in['table'] ?? '');
$allowed = ['projects', 'artists', 'team_members', 'categories', 'images', 'videos', 'documents'];
if (!in_array($table, $allowed, true)) json_out(['error' => 'table'], 400);
foreach ($ids as $i => $id) {
    DB::update($table, ['sort' => $i], 'id = ?', [$id]);
}
json_out(['ok' => true]);
