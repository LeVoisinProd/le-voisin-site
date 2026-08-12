<?php
/**
 * Analyse d'un lien vidéo collé (YouTube, Vimeo, Dailymotion).
 * [V10-CMS-BILINGUE] — message d'erreur traduit.
 */
require dirname(__DIR__) . '/_inc.php';
Auth::requireAdmin(true);
Auth::requireCsrf(true);

$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($in['action'] ?? '');

switch ($action) {
    case 'add':
        $owner = explode(':', (string)($in['owner'] ?? ''));
        $type = $owner[0] ?? ''; $oid = (int)($owner[1] ?? 0);
        $allowed = array_merge(['page'], array_keys(Content::entities()));
        if (!in_array($type, $allowed, true) || $oid <= 0) json_out(['error' => 'owner'], 400);

        $url = trim((string)($in['url'] ?? ''));
        $vidInfo = null;
        if (!empty($in['vid']) && !empty($in['provider'])) {
            // depuis le flux de chaîne
            $vidInfo = ['provider' => (string)$in['provider'], 'vid' => (string)$in['vid']];
            $url = VideoLib::watchUrl($vidInfo['provider'], $vidInfo['vid']);
        } else {
            $vidInfo = VideoLib::parse($url);
        }
        if (!$vidInfo) json_out(['error' => tu('sys_vid_link')], 422);

        $meta = VideoLib::oembed($vidInfo['provider'], $vidInfo['vid'], $url);
        $sort = 1 + (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM videos WHERE owner_type=? AND owner_id=?', [$type, $oid]);
        $id = DB::insert('videos', [
            'owner_type' => $type, 'owner_id' => $oid,
            'provider' => $vidInfo['provider'], 'vid' => $vidInfo['vid'], 'url' => $url,
            'title' => mb_substr((string)($in['title'] ?? $meta['title']), 0, 250),
            'thumb' => (string)($in['thumb'] ?? $meta['thumb']),
            'sort' => $sort,
        ]);
        $row = DB::one('SELECT * FROM videos WHERE id = ?', [$id]);
        json_out(['ok' => true, 'html' => video_item_html($row)]);

    case 'delete':
        VideoLib::remove((int)($in['id'] ?? 0));
        json_out(['ok' => true]);

    /* [12.08.2026] Réservée au Catalogue, ou publique. Une seule colonne, une
       seule question, et le même aller-retour que la durée juste en dessous. */
    case 'catalog':
        $id = (int)($in['id'] ?? 0);
        if ($id <= 0) json_out(['error' => 'id'], 400);
        DB::update('videos', ['catalog_only' => !empty($in['only']) ? 1 : 0], 'id = ?', [$id]);
        json_out(['ok' => true]);

    case 'feed':
        json_out(['items' => VideoLib::youtubeFeed(setting('yt_channel_id'))]);

    case 'duration':
        $id = (int)($in['id'] ?? 0);
        if ($id <= 0) json_out(['error' => 'id'], 400);
        $secs = max(1, min(60, (int)($in['seconds'] ?? 6)));
        DB::update('videos', ['duration' => $secs], 'id = ?', [$id]);
        json_out(['ok' => true, 'seconds' => $secs]);
}
json_out(['error' => 'action'], 400);
